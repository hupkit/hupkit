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

final class UpMergeHandler extends GitBaseHandler
{
    private $process;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
    }

    public function handle(Args $args)
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        if (null === $branch = $args->getArgument('branch')) {
            $branch = $this->git->getActiveBranchName();
        } else {
            $this->git->checkoutRemoteBranch('upstream', $branch);
        }

        $this->informationHeader($branch);

        try {
            if ($args->getOption('all')) {
                $changedBranches = $this->mergeAllBranches($branch);
            } else {
                $changedBranches = $this->mergeSingleBranch($branch);
            }

            if ([] === $changedBranches) {
                $this->style->success('Nothing to do here or not a version branch.');

                return 0;
            }

            $this->git->pushToRemote('upstream', $changedBranches, true);
            $this->style->success('Branch(es) where merged.');
        } catch (ProcessFailedException $e) {
            $this->style->error(
                [
                    'Merge operation failed with conflicts, please resolve this problem manually.',
                    'Run `git add` and `git commit` after your done, and run this command again to finish.',
                    $e->getMessage(),
                ]
            );

            return 1;
        }
    }

    private function mergeAllBranches(string $branch): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            return [];
        }

        $branches[] = 'master';
        $changedBranches = [];

        for ($i = $idx + 1, $c = count($branches); $i < $c; ++$i) {
            $this->git->checkoutRemoteBranch('upstream', $branches[$i]);
            $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $branches[$i - 1]]);

            $changedBranches[] = $branches[$i];
        }

        $this->git->checkout($branch);

        return $changedBranches;
    }

    private function mergeSingleBranch(string $branch): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            return [];
        }

        // No next version exists, so use the master branch instead.
        if ('' === ($nextVersion = $branches[$idx + 1] ?? '')) {
            $this->git->checkoutRemoteBranch('upstream', 'master');
            $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $branch]);

            $this->git->checkout($branch);

            return ['master'];
        }

        $this->git->checkoutRemoteBranch('upstream', $nextVersion);
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $branch]);

        $this->git->checkout($branch);

        return [$nextVersion];
    }
}
