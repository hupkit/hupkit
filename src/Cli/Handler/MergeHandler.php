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
use HubKit\Helper\SingleLineChoiceQuestionHelper;
use HubKit\Helper\StatusTable;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Adapter\ArgsInput;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class MergeHandler extends GitBaseHandler
{
    private $config;

    public function __construct(SymfonyStyle $style, Git $git, Config $config, GitHub $github)
    {
        parent::__construct($style, $git, $github);
        $this->config = $config;
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

        // Resolve branch-alias here so it's shown before the category is asked.
        $branchLabel = $this->getBaseBranchLabel($pr['base']['ref']);
        $authors = [];

        $message = $this->getCommitMessage($pr, $authors, $branchLabel, $args->getOption('squash'));
        $title = $this->getCommitTitle($pr, $this->getCategory($args), $authors);

        $mergeHash = $this->github->mergePullRequest($id, $title, $message, $pr['head']['sha'])['sha'];

        if (!$args->getOption('no-pat')) {
            $this->patAuthor($pr, $args->getOption('pat'));
        }

        $this->style->text('<fg=yellow>Pushing notes please wait...</>');
        $this->addCommentsToMergeCommit($pr, $mergeHash);

        $this->style->success('Pull-request has been merged.');

        if (!$args->getOption('no-pull')) {
            $this->updateLocalBranch($pr);
        }

        if (!$args->getOption('squash')) {
            $this->removeSourceBranch($pr);
        }
    }

    private function guardMergeStatus(array $pr)
    {
        if ('closed' === $pr['state']) {
            throw new \InvalidArgumentException('Cannot merge closed pull-request.');
        }

        if (null === $pr['mergeable']) {
            throw new \InvalidArgumentException(
                'Pull-request is not processed yet. Please try again in a few seconds.'
            );
        }

        if (true === $pr['mergeable']) {
            return;
        }

        throw new \InvalidArgumentException('Pull-request has conflicts which need to be resolved first.');
    }

    private function renderStatus(array $pr)
    {
        $status = $this->github->getCommitStatuses(
            $pr['base']['user']['login'],
            $pr['base']['repo']['name'],
            $pr['head']['sha']
        );

        if ('pending' === $status['state']) {
            $this->style->warning('Status checks are pending, merge with caution.');

            return;
        }

        $table = new StatusTable($this->style);

        foreach ($status['statuses'] as $statusItem) {
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

    private function getBaseBranchLabel(string $ref)
    {
        // Only the master branch is aliased.
        if ('master' !== $ref) {
            return $ref;
        }

        $label = null;
        $message = '<fg=cyan>master branch is aliased</> as <fg=cyan>%s</> <fg=yellow>(detected by %s)</>';

        if (file_exists(getcwd().'/composer.json')) {
            $composer = json_decode(file_get_contents(getcwd().'/composer.json'), true);

            if (isset($composer['extra']['branch-alias']['dev-master'])) {
                $label = $composer['extra']['branch-alias']['dev-master'];

                // Unstable releases are known to change often so use `1.0-dev` as final destination
                if ('0' === $label[0]) {
                    $label = '1.0-dev';
                }

                $this->style->text(sprintf($message, $label, 'composer.json'));

                return $label;
            }
        }

        if ('' !== ($label = $this->git->getGitConfig('branch.master.alias'))) {
            $this->style->text(sprintf($message, $label, 'Git config "branch.master.alias"'));

            return $label;
        }

        $this->style->note(
            [
                'No branch-alias found for "master", please provide an alias.',
                'This should be the version the master will become.',
                'If the last release is 2.1 the next will be eg. 2.2 or 3.0.',
            ]
        );

        $label = (string) $this->style->ask('Branch alias', null, function ($value) {
            if (!preg_match('/^\d+\.\d+$/', $value)) {
                throw new \InvalidArgumentException(
                    'A branch alias consists of major and minor version without any prefix or suffix. like: 1.2'
                );
            }

            return $value.'-dev';
        });

        $this->git->setGitConfig('branch.master.alias', $label, true);
        $this->style->note(
            [
                'Branch-alias is stored for feature reference.',
                'You can change this any time using the `branch-alias` command.',
            ]
        );

        return $label;
    }

    private function getCategory(Args $args): string
    {
        $this->style->newLine();

        return (new SingleLineChoiceQuestionHelper())->ask(
            new ArgsInput($args->getRawArgs(), $args),
            $this->style,
            new ChoiceQuestion(
                'Category', [
                    'feature' => 'feature',
                    'bug' => 'bug',
                    'minor' => 'minor',
                    'style' => 'style',
                    // 'security' => 'security', // (special case needs to be handled differently)
                ]
            )
        );
    }

    private function getCommitTitle(array $pr, string $category, array $authors): string
    {
        return sprintf('%s #%d %s (%s)', $category, $pr['number'], $pr['title'], implode(', ', $authors));
    }

    private function getCommitMessage(array $pr, array &$authors, string $branchLabel, bool $squash = false): string
    {
        if ($squash) {
            $message = sprintf('This PR was squashed before being merged into the %s branch.', $branchLabel);
        } else {
            $message = sprintf('This PR was merged into the %s branch.', $branchLabel);
        }

        $message .= "\n\n";

        foreach ($this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'].':'.$pr['base']['ref'],
            $pr['head']['ref']
        ) as $commit) {
            $authors[$commit['author']['login']] = $commit['author']['login'];
            $message .= $commit['sha'].' '.explode("\n", $commit['commit']['message'], 2)[0]."\n";
        }

        return $message;
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
        $this->git->remoteUpdate('upstream');
        $this->git->addNotes($commentText, $sha, 'github-comments');
        $this->git->pushToRemote('upstream', 'refs/notes/github-comments');
    }

    private function updateLocalBranch(array $pr)
    {
        $branch = $pr['base']['ref'];

        if (!$this->git->branchExists($branch)) {
            return;
        }

        if (!$this->git->isWorkingTreeReady()) {
            $this->style->warning('The Git working tree has uncommitted changes, unable to update your local branch.');
        }

        $this->git->checkout($pr['base']['ref']);
        $this->git->pullRemote('upstream', $pr['base']['ref']);

        $this->style->success(sprintf('Your local "%s" branch is updated.', $pr['base']['ref']));
    }

    private function removeSourceBranch(array $pr)
    {
        if ($this->github->getAuthUsername() !== $pr['user']['login']) {
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
}
