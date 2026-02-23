<?php

declare(strict_types=1);

/*
 * This file is part of the HuPKit package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace HubKit\Cli\Handler;

use HubKit\Service\Git;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class SynchronizeConfigHandler extends GitBaseHandler
{
    public function handle(Args $args, IO $io): int
    {
        $this->git->guardWorkingTreeReady();
        $this->informationHeader();

        $this->style->title('Configuration Synchronizer');

        if (! $this->git->remoteBranchExists(REMOTE_MAIN, '_hubkit')) {
            if (! $this->git->branchExists('_hubkit')) {
                $this->style->success('HuPKit configuration is not set-up yet, run `init-config` first.');

                return 1;
            }

            $this->git->pushToRemote(REMOTE_MAIN, '_hubkit:_hubkit');

            $this->style->success('Successfully pushed the _hubkit branch.');

            return 0;
        }

        switch ($this->git->getRemoteDiffStatus(REMOTE_MAIN, '_hubkit')) {
            case Git::STATUS_UP_TO_DATE:
                $this->style->info('Already up-to-date with remote repository.');

                return 0;

            case Git::STATUS_NEED_BRANCH:
                $this->style->info('Remote "_hubkit" branch exists, missing locally.');
                $this->setupRemoteBranch();
                $this->style->success('Successfully created local branch "_hubkit".');

            case Git::STATUS_NEED_PULL:
                $this->style->note('Pulling changes.');
                $this->git->fetchRemote(REMOTE_MAIN, '_hubkit:_hubkit');
                $this->style->success('Updated your local _hubkit branch with remote repository.');

                return 0;

            case Git::STATUS_NEED_PUSH:
                $this->style->text('Local version is ahead with remote.');
                $this->git->pushToRemote(REMOTE_MAIN, '_hubkit:_hubkit');
                $this->style->success('Pushed changes to remote repository.');

                return 0;
        }

        throw new \RuntimeException('The remote "_hubkit" branch and local branch have diverged. Cannot safely continue, resolve this problem manually.');
    }

    private function setupRemoteBranch(): void
    {
        $currentBranch = $this->git->getActiveBranchName();

        try {
            $this->git->checkoutRemoteBranch(REMOTE_MAIN, '_hubkit');
            $this->git->trackRemoteBranch(REMOTE_MAIN, '_hubkit');
        } finally {
            $this->git->checkout($currentBranch);
        }
    }
}
