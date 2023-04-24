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
use HubKit\Service\BranchSplitsh;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class UpMergeHandler extends GitBaseHandler
{
    private $process;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process, Config $config, private BranchSplitsh $branchSplitsh)
    {
        parent::__construct($style, $git, $github, $config);
        $this->process = $process;
    }

    public function handle(Args $args)
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        $branch = $this->getBranchName($args);

        $this->style->title('Branch Upmerge');
        $this->informationHeader($branch);

        if ($args->getOption('dry-run')) {
            return $this->handleDryMerge($args, $branch);
        }

        return $this->handleMerge($args, $branch);
    }

    private function getBranchName(Args $args): string
    {
        $branch = $args->getArgument('branch');

        if ($branch === null) {
            return $this->git->getActiveBranchName();
        }

        $this->git->checkoutRemoteBranch('upstream', $branch);

        return $branch;
    }

    private function handleMerge(Args $args, string $branch)
    {
        $noSplit = $args->getOption('no-split');

        try {
            if ($args->getOption('all')) {
                $changedBranches = $this->mergeAllBranches($branch, $this->github->getDefaultBranch(), $noSplit);
            } else {
                $changedBranches = $this->mergeSingleBranch($branch, $noSplit);
            }

            if ($changedBranches === []) {
                $this->style->success('Nothing to do here or not a version branch.');

                return 0;
            }

            $this->git->pushToRemote('upstream', $changedBranches);
            $this->style->success('Branch(es) where merged.');
        } catch (\Exception $e) {
            $this->style->error(
                [
                    'Operation failed, please resolve this problem manually.',
                    'In the case of a conflict. Run `git add` and `git commit` after your done.',
                    'And run this command again to finish.',
                    '',
                    $e->getMessage(),
                ]
            );

            return 1;
        }
    }

    private function mergeAllBranches(string $branch, string $defaultBranch, bool $noSplit): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            return [];
        }

        $this->git->ensureBranchInSync('upstream', $branch);

        if (! \in_array($defaultBranch, $branches, true)) {
            $branches[] = $defaultBranch;
        }
        $changedBranches = [];

        for ($i = $idx + 1, $c = \count($branches); $i < $c; ++$i) {
            $sourceBranch = $branches[$i - 1];
            $destBranch = $branches[$i];

            $this->git->checkoutRemoteBranch('upstream', $destBranch);
            $this->git->ensureBranchInSync('upstream', $destBranch);
            $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $sourceBranch]);

            $this->style->note(sprintf('Merged "%s" into "%s"', $sourceBranch, $destBranch));

            if (! $noSplit) {
                $this->branchSplitsh->splitBranch($destBranch);
            }

            $changedBranches[] = $destBranch;
        }

        $this->git->checkout($branch);

        return $changedBranches;
    }

    private function mergeSingleBranch(string $branch, bool $noSplit): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            return [];
        }

        if ('' === ($nextVersion = $branches[$idx + 1] ?? '')) {
            $nextVersion = $this->github->getDefaultBranch();
        }

        $this->git->ensureBranchInSync('upstream', $branch);
        $this->git->checkoutRemoteBranch('upstream', $nextVersion);
        $this->git->ensureBranchInSync('upstream', $nextVersion);
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $branch]);

        $this->style->note(sprintf('Merged "%s" into "%s"', $branch, $nextVersion));

        if (! $noSplit) {
            $this->branchSplitsh->splitBranch($nextVersion);
        }

        $this->git->checkout($branch);

        return [$nextVersion];
    }

    private function handleDryMerge(Args $args, string $branch)
    {
        $noSplit = $args->getOption('no-split');

        try {
            if ($args->getOption('all')) {
                $defaultBranch = $this->github->getDefaultBranch();

                $changedBranches = $this->dryMergeAllBranches($branch, $defaultBranch, $noSplit);
            } else {
                $changedBranches = $this->dryMergeSingleBranch($branch, $noSplit);
            }

            if ($changedBranches === []) {
                $this->style->success(
                    'This operation would not perform anything, ' .
                    'everything is up-to-date or current branch is not a version branch.'
                );

                return 0;
            }

            $this->style->success('[DRY-RUN] Branch(es) where merged.');
        } catch (\Exception $e) {
            $this->style->error(
                [
                    'Operation would have failed, you need to resolve these problems manually.',
                    '',
                    $e->getMessage(),
                ]
            );

            return 1;
        }
    }

    private function dryMergeAllBranches(string $branch, string $defaultBranch, bool $noSplit): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            $this->style->warning(sprintf('Branch "%s" is not a supported version branch.', $branch));

            return [];
        }

        $this->git->ensureBranchInSync('upstream', $branch);

        if (! \in_array($defaultBranch, $branches, true)) {
            $branches[] = $defaultBranch;
        }
        $changedBranches = [];

        for ($i = $idx + 1, $c = \count($branches); $i < $c; ++$i) {
            $this->git->ensureBranchInSync('upstream', $branches[$i]);
            $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $branches[$i - 1], $branches[$i]));
            $this->branchSplitsh->drySplitBranch($branches[$i]);

            $changedBranches[] = $branches[$i];
        }

        return $changedBranches;
    }

    private function dryMergeSingleBranch(string $branch, bool $noSplit): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            $this->style->warning(sprintf('Branch "%s" is not a supported version branch.', $branch));

            return [];
        }

        if ('' === ($nextVersion = $branches[$idx + 1] ?? '')) {
            $nextVersion = $this->github->getDefaultBranch();
        }

        $this->git->ensureBranchInSync('upstream', $branch);
        $this->git->ensureBranchInSync('upstream', $nextVersion);
        $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $branch, $nextVersion));
        $this->branchSplitsh->drySplitBranch($nextVersion);

        return [$nextVersion];
    }
}
