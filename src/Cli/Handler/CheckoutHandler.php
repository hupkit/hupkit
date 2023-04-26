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
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class CheckoutHandler extends GitBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        private readonly CliProcess $process
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args): void
    {
        $this->informationHeader();

        $pullRequest = $this->github->getPullRequest($args->getArgument('number'));

        if ($pullRequest['state'] !== 'open') {
            throw new \InvalidArgumentException('Cannot checkout closed/merged pull-request.');
        }

        $remote = $pullRequest['head']['user']['login'];
        $branch = $remote . '--' . $pullRequest['head']['ref'];

        $this->git->guardWorkingTreeReady();
        $this->git->ensureRemoteExists($remote, $pullRequest['head']['repo']['ssh_url']);
        $this->git->remoteUpdate($remote);

        if ($this->git->branchExists($branch)) {
            $this->style->note('This pull request was already checked out locally, updating your local version.');

            $this->git->checkout($branch);
            $this->ensureBranchInSync($remote, $branch, $pullRequest['head']['ref']);
        } else {
            $this->git->checkout($remote . '/' . $pullRequest['head']['ref']);
            $this->git->checkout($branch, true);
            $this->process->run(['git', 'branch', '--set-upstream-to', $remote . '/' . $pullRequest['head']['ref'], $branch]);
        }

        $this->style->success(sprintf('Pull request %s is checked out!', $pullRequest['html_url']));
    }

    private function ensureBranchInSync(string $remote, string $branch, string $remoteBranch): void
    {
        $status = $this->git->getRemoteDiffStatus($remote, $branch, $remoteBranch);

        if ($this->git::STATUS_NEED_PULL === $status) {
            $this->style->note(
                sprintf('Your local branch "%s" is outdated, running git pull.', $branch)
            );

            $this->git->pullRemote($remote);
        } elseif ($this->git::STATUS_DIVERGED === $status) {
            throw new \RuntimeException(
                'Your local branch and the remote version have differed. Please resolve this problem manually.'
            );
        }
    }
}
