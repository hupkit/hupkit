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

use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Webmozart\Console\Api\Args\Args;

final class SwitchBaseHandler extends GitBaseHandler
{
    private $process;

    public function __construct(SymfonyStyle $style, Git $git, CliProcess $process, GitHub $github)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
    }

    public function handle(Args $args): void
    {
        $this->informationHeader();

        $pullRequest = $this->github->getPullRequest($args->getArgument('number'));
        $newBase = $args->getArgument('new-base');

        $this->guardNotClosed($pullRequest['state']);
        $this->guardValidBase($newBase, $pullRequest['base']['ref']);
        $this->guardWorkingTreeIsReady();

        $branch = $pullRequest['head']['ref'];
        $remote = $pullRequest['head']['user']['login'];
        $tmpBranch = '_temp_' . $remote . '--' . $branch . '--' . $newBase;

        $this->git->ensureRemoteExists($remote, $pullRequest['head']['repo']['ssh_url']);
        $this->git->remoteUpdate($remote);
        $this->git->remoteUpdate('upstream');

        // Operation was already in progress (but gave a conflict).
        if (file_exists($this->git->getGitDirectory() . '/.hubkit-switch')) {
            $this->handleIncompleteOperation($remote, $tmpBranch, $branch);
        }

        $this->switchBranchBase($remote, $branch, 'upstream/' . $pullRequest['base']['ref'], $newBase, $tmpBranch);
        $this->pushToRemote($remote, $tmpBranch, $branch);
        $this->deleteTempBranch($tmpBranch);

        $this->github->updatePullRequest($pullRequest['number'], ['base' => $newBase]);

        if (! $args->getOption('skip-help')) {
            $this->postHelpComment($pullRequest, $branch);
        }

        if ($this->git->branchExists($branch)) {
            $this->style->note(
                [
                    sprintf('A local branch "%s" exists but was not updated.', $branch),
                    sprintf('If you want to update your local branch run: git reset --hard %s/%s', $remote, $branch),
                ]
            );
        }

        $this->style->success(sprintf('Pull request %s base was switched from "%s" to "%s"',
            $pullRequest['html_url'],
            $pullRequest['base']['ref'],
            $newBase
        ));
    }

    private function handleIncompleteOperation(string $remote, string $tmpBranch, string $branch): void
    {
        $gitDir = $this->git->getGitDirectory();
        $tmpWorkingBranch = trim(file_get_contents($gitDir . '/.hubkit-switch'));

        // If branch was switched, assume the operation is aborted completely (and start over from scratch).
        // If branch name equals, assume a resolve - check if there are actual changes (before we push with force)
        if ($tmpWorkingBranch !== $this->git->getActiveBranchName()) {
            $this->style->warning(
                [
                    sprintf('Another switch operation was already in process for "%s"!', $tmpWorkingBranch),
                    'You can continue with the previous operation or abort it (this cannot be undone).',
                    'By aborting the previous operation you will loose all work in that temp working-branch!',
                ]
            );

            if ($this->style->confirm('Do you want to abort the previous operation?')) {
                return; // Delete always performed in switchBranchBase()
            }

            if (! $this->style->confirm('Do you want to continue the previous operation?')) {
                throw new \RuntimeException(
                    'Failed! Cannot perform switch while another operation is still pending. Please abort previous operation first.'
                );
            }

            $this->git->checkout($tmpWorkingBranch);
        }

        // Operation was continued, now check if the branches have in fact diverged.
        if ($this->git->getRemoteDiffStatus($remote, $tmpBranch, $branch) === $this->git::STATUS_DIVERGED) {
            $this->pushToRemote($remote, $tmpBranch, $branch);
            $this->deleteTempBranch($tmpBranch);
        }
    }

    private function switchBranchBase(string $remote, string $branch, string $currentBase, string $newBase, string $tmpBranch): void
    {
        $activeBranch = $this->git->getActiveBranchName();

        if ($activeBranch[0] === '_') {
            $activeBranch = 'master';
        }

        // Always (re)start the rebase process from scratch in case something went horrible wrong.
        $this->deleteTempBranch($tmpBranch);
        $this->git->checkout($remote . '/' . $branch);
        $this->git->checkout($tmpBranch, true);

        file_put_contents($this->git->getGitDirectory() . '/.hubkit-switch', $tmpBranch);

        try {
            $this->process->mustRun(['git', 'rebase', '--onto', 'upstream/' . $newBase, $currentBase, $tmpBranch]);
            $this->git->checkout($activeBranch);
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException(
                <<<MESSAGE
                    Git rebase operation failed: {$e->getMessage()}

                    -------------------------------------------------------------
                    Please resolve the conflicts manually, `git add` the resolved
                    files AND execute `git rebase --continue` afterwards.

                    Then run the operation again.

                    DO NOT PUSH THE CHANGES MANUALLY!
                    MESSAGE
                , 0, $e
            );
        }
    }

    private function guardWorkingTreeIsReady(): void
    {
        if (! $this->git->isWorkingTreeReady()) {
            throw new \RuntimeException(
                'The Git working tree is not ready. There are uncommitted changes or a rebase is in progress.' .
                "\n" .
                'If there were conflicts during the switch run `git rebase --continue` and run the `switch-base` command again.'
            );
        }
    }

    private function deleteTempBranch(string $tmpBranch): void
    {
        $this->process->run(['git', 'branch', '-D', $tmpBranch]);
        @unlink($this->git->getGitDirectory() . '/.hubkit-switch');
    }

    private function pushToRemote(string $remote, string $tmpBranch, string $branch): void
    {
        $this->process->mustRun(['git', 'push', '--force', $remote, $tmpBranch . ':' . $branch], 'Push failed (access disabled?)');
    }

    private function guardNotClosed(string $state): void
    {
        if ($state !== 'open') {
            throw new \InvalidArgumentException('Cannot switch base of closed/merged pull-request.');
        }
    }

    private function guardValidBase(string $newBase, string $current): void
    {
        if ($newBase === $current) {
            throw new \InvalidArgumentException(sprintf('Cannot switch base, current base is already "%s".', $newBase));
        }

        if (! $this->git->remoteBranchExists('upstream', $newBase)) {
            throw new \InvalidArgumentException(sprintf('Cannot switch base, base branch "%s" does not exists.', $newBase));
        }
    }

    private function postHelpComment(array $pullRequest, string $branch): void
    {
        if ($pullRequest['user']['login'] === $this->github->getAuthUsername()) {
            return;
        }

        $this->github->createComment(
            $pullRequest['number'],
            <<<MESSAGE
                The base of this pull-request was changed, you need fetch and reset your local branch
                if you want to add new commits to this pull request. **Reset before you pull, else commits
                may become messed-up.**

                Unless you added new commits (to this branch) locally that you did not push yet,
                execute `git fetch origin && git reset "{$branch}"` to update your local branch.

                Feel free to ask for assistance when you get stuck :+1:
                MESSAGE
        );
    }
}
