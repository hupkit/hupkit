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
use HubKit\Service\Git;
use HubKit\Service\Git\GitFileReader;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class SynchronizeConfigHandler extends GitBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        private readonly GitFileReader $gitFileReader
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args, IO $io): int
    {
        $this->git->guardWorkingTreeReady();
        $this->informationHeader();

        $this->style->title('Configuration Synchronizer');

        if (! $this->gitFileReader->fileExistsAtRemote('upstream', '_hubkit', 'config.php')) {
            $this->style->error('The _hubkit branch does not exist at upstream. Run the `init-config` command first.');

            return 1;
        }

        $activeBranch = $this->git->getActiveBranchName();

        if (! $this->git->branchExists('_hubkit')) {
            try {
                $this->git->checkoutRemoteBranch('upstream', '_hubkit');
            } finally {
                $this->git->checkout($activeBranch);
            }

            $this->style->success('Successfully pulled the _hubkit branch.');

            return 0;
        }

        $status = $this->git->getRemoteDiffStatus('upstream', '_hubkit');

        switch ($status) {
            case Git::STATUS_UP_TO_DATE:
                $this->style->info('Already up-to-date.');

                return 0;

            case Git::STATUS_NEED_PULL:
                $this->style->note('Pulling changes.');

                try {
                    $this->git->checkout('_hubkit');
                    $this->git->pullRemote('upstream', '_hubkit');
                } finally {
                    $this->git->checkout($activeBranch);
                }

                $this->style->success('Updated your local _hubkit branch.');

                return 0;

            case Git::STATUS_NEED_PUSH:
                $this->style->text('Local version is ahead with remote upstream.');

                if ($this->style->confirm('Push changes now?', true)) {
                    $this->git->pushToRemote('upstream', '_hubkit:_hubkit');
                    $this->style->success('Pushed changes to upstream.');
                }

                return 0;
        }

        $this->git->checkout('_hubkit');

        try {
            // At least try to update.
            $this->git->pullRemote('upstream', '_hubkit');
            $this->style->success('Updated your local _hubkit branch.');
        } catch (\Exception $e) {
            throw new \RuntimeException('The remote "_hubkit" branch and local branch have diverged. Cannot safely continue, resolve this problem manually.', 1, $e);
        }

        if ($this->git->getRemoteDiffStatus('upstream', '_hubkit') === Git::STATUS_NEED_PUSH) {
            $this->style->text('Local version is ahead with remote upstream.');

            if ($this->style->confirm('Push changes now?', true)) {
                $this->git->pushToRemote('upstream', '_hubkit:_hubkit');
                $this->style->success('Pushed changes to upstream.');
            }
        }

        $this->git->checkout($activeBranch);

        return 0;
    }
}
