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
    public function __construct(SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        private readonly CliProcess $process,
        private readonly BranchSplitsh $branchSplitsh
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    public function handle(Args $args): int
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        $branch = $this->getBranchName($args);

        $this->style->title('Branch Upmerge');
        $this->informationHeader($branch);

        $branches = $this->git->getVersionBranches('upstream');

        if (! \in_array($branch, $branches, true)) {
            $this->style->error(sprintf('Branch "%s" is not a supported version branch.', $branch));

            return 1;
        }

        $defaultBranch = $this->github->getDefaultBranch();

        if (! \in_array($defaultBranch, $branches, true)) {
            $branches[] = $defaultBranch;
        }

        if ($args->getOption('dry-run')) {
            return $this->handleDryMerge($args, $branch, $branches);
        }

        return $this->handleMerge($args, $branch, $branches);
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

    /**
     * @param string[] $branches
     */
    private function handleMerge(Args $args, string $branch, array $branches): int
    {
        $noSplit = $args->getOption('no-split');

        try {
            $this->git->ensureBranchInSync('upstream', $branch);

            if ($args->getOption('all')) {
                $changedBranches = $this->mergeAllBranches($branch, $branches, $noSplit);
            } else {
                $changedBranches = $this->mergeSingleBranch($branch, $branches, $noSplit);
            }

            if ($changedBranches === []) {
                $this->style->success('Nothing to do here.');

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

        return 0;
    }

    /**
     * @param string[] $branches
     *
     * @return string[]
     */
    private function mergeAllBranches(string $branch, array $branches, bool $noSplit): array
    {
        $changedBranches = [];

        for ($i = (array_search($branch, $branches, true) + 1), $c = \count($branches); $i < $c; ++$i) {
            $sourceBranch = $branches[$i - 1];
            $nextVersion = $branches[$i];

            $this->git->checkoutRemoteBranch('upstream', $nextVersion);
            $this->git->ensureBranchInSync('upstream', $nextVersion);
            $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $sourceBranch]);

            $this->style->note(sprintf('Merged "%s" into "%s"', $sourceBranch, $nextVersion));

            if (! $noSplit) {
                $this->branchSplitsh->splitBranch($nextVersion);
            }

            $changedBranches[] = $nextVersion;
        }

        $this->git->checkout($branch);

        return $changedBranches;
    }

    /**
     * @param string[] $branches
     *
     * @return string[]
     */
    private function mergeSingleBranch(string $branch, array $branches, bool $noSplit): array
    {
        $idx = array_search($branch, $branches, true);

        if (! isset($branches[$idx + 1])) {
            return []; // Already at the last possible branch
        }

        $nextVersion = $branches[$idx + 1];

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

    /**
     * @param string[] $branches
     */
    private function handleDryMerge(Args $args, string $branch, array $branches): int
    {
        $noSplit = $args->getOption('no-split');

        try {
            $this->git->ensureBranchInSync('upstream', $branch);

            if ($args->getOption('all')) {
                $changedBranches = $this->dryMergeAllBranches($branch, $branches, $noSplit);
            } else {
                $changedBranches = $this->dryMergeSingleBranch($branch, $branches, $noSplit);
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

        return 0;
    }

    /**
     * @param string[] $branches
     *
     * @return string[]
     */
    private function dryMergeAllBranches(string $branch, array $branches, bool $noSplit): array
    {
        $changedBranches = [];

        for ($i = (array_search($branch, $branches, true) + 1), $c = \count($branches); $i < $c; ++$i) {
            $this->git->ensureBranchInSync('upstream', $branches[$i]);
            $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $branches[$i - 1], $branches[$i]));
            $this->branchSplitsh->drySplitBranch($branches[$i]);

            $changedBranches[] = $branches[$i];
        }

        return $changedBranches;
    }

    /**
     * @param string[] $branches
     *
     * @return string[]
     */
    private function dryMergeSingleBranch(string $branch, array $branches, bool $noSplit): array
    {
        $idx = array_search($branch, $branches, true);

        if (! isset($branches[$idx + 1])) {
            return []; // Already at the last possible branch
        }

        $nextVersion = $branches[$idx + 1];

        $this->git->ensureBranchInSync('upstream', $nextVersion);
        $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $branch, $nextVersion));
        $this->branchSplitsh->drySplitBranch($nextVersion);

        return [$nextVersion];
    }
}
