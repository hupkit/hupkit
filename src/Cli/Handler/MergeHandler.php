<?php

declare(strict_types=1);

/*
 * This file is part of the HubKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Cli\Handler;

use HubKit\Config;
use HubKit\Helper\BranchAliasResolver;
use HubKit\Helper\SingleLineChoiceQuestionHelper;
use HubKit\Helper\StatusTable;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\MessageValidator;
use HubKit\Service\SplitshGit;
use HubKit\StringUtil;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Adapter\ArgsInput;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class MergeHandler extends GitBaseHandler
{
    private $aliasResolver;
    private $questionHelper;
    private $config;
    private $splitshGit;

    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        BranchAliasResolver $aliasResolver,
        SingleLineChoiceQuestionHelper $questionHelper,
        Config $config,
        SplitshGit $splitshGit
    ) {
        parent::__construct($style, $git, $github);
        $this->aliasResolver = $aliasResolver;
        $this->questionHelper = $questionHelper;
        $this->config = $config;
        $this->splitshGit = $splitshGit;
    }

    public function handle(Args $args, IO $io)
    {
        if (!$io->isInteractive()) {
            throw new \RuntimeException('This command can only be run in interactive mode.');
        }

        $pr = $this->github->getPullRequest(
            $id = $args->getArgument('number'),
            true
        );

        $this->informationHeader($pr['base']['ref']);
        $this->style->writeln(
            [
                sprintf('Merging Pull Request <fg=yellow>%d: %s</>', $pr['number'], $pr['title']),
                '<fg=yellow>'.$pr['html_url'].'</>',
                '',
            ]
        );

        if ($args->getOption('squash')) {
            $this->style->note('This pull request will be squashed before being merged.');
        }

        $this->guardMergeStatus($pr);
        $this->renderStatus($pr);

        $branchLabel = $this->getBaseBranchLabel($pr['base']['ref']);
        $authors = [];

        $message = $this->getCommitMessage($pr, $authors, $branchLabel, $args->getOption('squash'));
        $title = $this->getCommitTitle($pr, $this->getCategory($pr, $args), $authors);

        $mergeHash = $this->github->mergePullRequest($id, $title, $message, $pr['head']['sha'])['sha'];

        if (!$args->getOption('no-pat')) {
            $this->patAuthor($pr, $args->getOption('pat'));
        }

        $this->style->text('<fg=yellow>Pushing notes please wait...</>');
        $this->addCommentsToMergeCommit($pr, $mergeHash);

        $this->style->success('Pull request has been merged.');

        if (!$args->getOption('no-pull') && $this->updateLocalBranch($pr['base']['ref'])) {
            $this->splitRepository($pr);
        }

        if (!$args->getOption('squash')) {
            $this->removeSourceBranch($pr);
        }
    }

    private function guardMergeStatus(array $pr)
    {
        if ('closed' === $pr['state']) {
            throw new \InvalidArgumentException('Cannot merge closed pull request.');
        }

        if (null === $pr['mergeable']) {
            throw new \InvalidArgumentException(
                'Pull request is not processed yet. Please try again in a few seconds.'
            );
        }

        if (true === $pr['mergeable']) {
            return;
        }

        throw new \InvalidArgumentException('Pull request has conflicts which need to be resolved first.');
    }

    private function renderStatus(array $pr)
    {
        $status = $this->github->getCommitStatuses(
            $pr['base']['repo']['owner']['login'],
            $pr['base']['repo']['name'],
            $pr['head']['sha']
        );

        if ('pending' === $status['state']) {
            $this->style->warning('Status checks are pending, merge with caution.');
        }

        $table = new StatusTable($this->style);

        foreach ($status['statuses'] ?? [] as $statusItem) {
            $label = explode('/', $statusItem['context']);
            $label = ucfirst($label[1] ?? $label[0]);

            $table->addRow($label, $statusItem['state'], $statusItem['description']);
        }

        $this->determineReviewStatus($pr, $table);
        $table->render();

        if ($table->hasStatus('error') || $table->hasStatus('pending') || $table->hasStatus('failure')) {
            $this->style->warning('One or more status checks did not complete or failed. Merge with caution.');
        }
    }

    private function determineReviewStatus(array $pr, StatusTable $table)
    {
        if (!count($pr['labels'])) {
            return;
        }

        $expects = [
            'ready' => 'success',
            'status: reviewed' => 'success',
            'status: ready' => 'success',
            'status: needs work' => 'failure',
            'status: needs review' => 'pending',
        ];

        foreach ($pr['labels'] as $label) {
            $name = strtolower($label['name']);

            if (isset($expects[$name])) {
                $table->addRow('Reviewed', $expects[$name], $label['name']);

                return;
            }
        }
    }

    private function getBaseBranchLabel(string $ref): string
    {
        // Only the master branch is aliased.
        if ('master' !== $ref) {
            return $ref;
        }

        // Resolve branch-alias here so it's shown before the category is asked.
        $branchLabel = $this->aliasResolver->getAlias();
        $detectedBy = $this->aliasResolver->getDetectedBy();

        $this->style->text(
            sprintf(
                '<fg=cyan>master branch is aliased</> as <fg=cyan>%s</> <fg=yellow>(detected by %s)</>',
                $branchLabel,
                $detectedBy
            )
        );

        return $branchLabel;
    }

    private function getCommitMessage(array $pr, array &$authors, string $branchLabel, bool $squash = false): string
    {
        if ($squash) {
            $message = sprintf('This PR was squashed before being merged into the %s branch.', $branchLabel)."\n";
        } else {
            $message = sprintf('This PR was merged into the %s branch.', $branchLabel)."\n";
        }

        $message .= $this->prLabelsToMergeMessage($pr['labels']);
        $message .= "Discussion\n----------\n\n";
        $message .= $pr['body'];
        $message .= "\n\nCommits\n-------\n\n";

        $commits = $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'].':'.$pr['base']['ref'],
            $pr['head']['ref']
        );

        $this->validateMessages($commits);

        foreach ($commits as $commit) {
            $authors[$commit['author']['login']] = $commit['author']['login'];
            $message .= $commit['sha'].' '.explode("\n", $commit['commit']['message'], 2)[0]."\n";
        }

        return $message;
    }

    private function getCommitTitle(array $pr, string $category, array $authors): string
    {
        return sprintf('%s #%d %s (%s)', $category, $pr['number'], $pr['title'], implode(', ', $authors));
    }

    private function getCategory(array $pr, Args $args): string
    {
        $this->style->newLine();

        // Always prompt to ensure the user has some time to check
        // the provided information.
        if ($args->getOption('security')) {
            if (!$this->style->confirm('You are merging a security patch, is this correct?', true)) {
                throw new \RuntimeException('User aborted.');
            }

            return 'security';
        }

        $guessedCat = null;
        $categories = [
            'feature' => 'feature',
            'refactor' => 'refactor',
            'bug' => 'bug',
            'minor' => 'minor',
            'style' => 'style',
        ];

        foreach ($pr['labels'] as $label) {
            $labelName = strtolower($label['name']);

            if (isset($categories[$labelName])) {
                $guessedCat = $labelName;

                break;
            }
        }

        return $this->questionHelper->ask(
            new ArgsInput($args->getRawArgs(), $args),
            $this->style,
            new ChoiceQuestion('Category', $categories, $guessedCat)
        );
    }

    private function prLabelsToMergeMessage(array $prLabels): string
    {
        $labels = [];
        $labelToMergeLabel = [
            'deprecation' => 'deprecation',
            'deprecation removal' => 'removed-deprecation',
            'bc break' => 'bc-break',
        ];

        foreach ($prLabels as $label) {
            $labelName = strtolower($label['name']);

            if (isset($labelToMergeLabel[$labelName])) {
                $labels[] = $labelToMergeLabel[$labelName];
            }
        }

        if ($labels) {
            return 'labels: '.implode(',', $labels)."\n\n";
        }

        return "\n";
    }

    private function patAuthor(array $pr, string $message = null)
    {
        if ($this->github->getAuthUsername() === $pr['user']['login']) {
            return;
        }

        $this->github->createComment($pr['number'], str_replace('@author', '@'.$pr['user']['login'], $message));
    }

    private function addCommentsToMergeCommit(array $pr, $sha)
    {
        $commentText = '';

        $commentTemplate = <<<COMMENT
---------------------------------------------------------------------------

by %s at %s

%s
\n
COMMENT;

        foreach ($this->github->getComments($pr['number']) as $comment) {
            $commentText .= sprintf(
                $commentTemplate,
                $comment['user']['login'],
                $comment['created_at'],
                $comment['body']
            );
        }

        $this->git->ensureNotesFetching('upstream');

        // Pull-request was merged remote, so to make adding notes possible
        // we need the actual reference local. Don't use pull as the working dir
        // could be stale.
        $this->git->remoteUpdate('upstream');
        $this->git->addNotes($commentText, $sha, 'github-comments');

        if ('' !== $commentText) {
            $this->git->pushToRemote('upstream', 'refs/notes/github-comments');
        }
    }

    private function updateLocalBranch(string $branch): bool
    {
        if (!$this->git->branchExists($branch)) {
            return false;
        }

        if (!$this->git->isWorkingTreeReady()) {
            $this->style->warning('The Git working tree has uncommitted changes, unable to update your local branch.');

            return false;
        }

        $this->git->checkout($branch);
        $this->git->pullRemote('upstream', $branch);

        $this->style->success(sprintf('Your local "%s" branch is updated.', $branch));

        return true;
    }

    private function splitRepository(array $pr)
    {
        $configName = ['repos', $this->github->getHostname(), $this->github->getOrganization().'/'.$this->github->getRepository()];
        $reposConfig = $this->config->get($configName);

        if (empty($reposConfig['split']) || !$this->style->confirm('Split repository now?')) {
            return;
        }

        $this->splitshGit->checkPrecondition();

        $this->style->text('Starting split operation please wait...');
        $progressBar = $this->style->createProgressBar();
        $progressBar->start(count($reposConfig['split']));

        foreach ($reposConfig['split'] as $prefix => $config) {
            $progressBar->advance();
            $this->splitshGit->splitTo($pr['base']['ref'], $prefix, is_array($config) ? $config['url'] : $config);
        }
    }

    private function removeSourceBranch(array $pr)
    {
        if (!$this->git->isWorkingTreeReady() ||
            $this->github->getAuthUsername() !== $pr['user']['login'] ||
            !$this->git->branchExists($pr['base']['ref'])
        ) {
            return;
        }

        $branch = $pr['head']['ref'];

        if (!$this->git->branchExists($branch) ||
            !$this->style->confirm(sprintf('Delete branch "%s" (origin and local)', $branch), true)
        ) {
            return;
        }

        $this->git->checkout($pr['base']['ref']);

        if ('' !== $remote = $this->git->getGitConfig('branch.'.$branch.'.remote')) {
            $this->git->deleteRemoteBranch($remote, $branch);
        } else {
            $this->style->note(sprintf('No remote configured for branch "%s", skipping deletion.', $branch));
        }

        $this->git->deleteBranch($branch, true);
        $this->style->note(sprintf('Branch "%s" was deleted.', $branch));
    }

    private function validateMessages(array $commits): void
    {
        $violations = MessageValidator::validateCommitsMessages($commits);
        $severity = MessageValidator::SEVERITY_LOW;

        if (count($violations) === 0) {
            return;
        }

        $messages = [];

        foreach ($violations as $violation) {
            $severity = max($severity, $violation[0]);
            $messages[] = "{$violation[1]}: {$violation[2]}";
        }

        $this->style->warning('On or more commits are problematic, make sure this is correct.');
        $this->style->writeln(
            array_map(function ($element) {
                return sprintf(' * <fg=yellow>%s</>', implode("\n   ", StringUtil::splitLines($element)));
            }, $messages)
        );
        $this->style->newLine();

        if ($severity === MessageValidator::SEVERITY_HIGH) {
            throw new \InvalidArgumentException('Please fix the commits contents before continuing.');
        }

        if (!$this->style->confirm('Ignore problematic commits and continue anyway?', false)) {
            throw new \InvalidArgumentException('User aborted. Please fix commits contents before continuing.');
        }
    }
}
