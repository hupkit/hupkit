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

        $branchConfig = $this->config->getBranchConfig($branch);

        if (! ($branchConfig->config['upmerge'] ?? true)) {
            $this->style->error(sprintf('Branch "%s" has upmerge disabled by "%s".', $branch, $branchConfig->configName));

            return 1;
        }

        $branches = $this->getBranches($branch, $branches);

        if ($branches === []) {
            $this->style->success('Nothing to do here, no branches to upmerge.');

            return 0;
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
     * @param array<int, string> $branches
     *
     * @return array<int, string>
     */
    private function getBranches(string $branch, array $branches): array
    {
        $defaultBranch = $this->github->getDefaultBranch();

        if (! \in_array($defaultBranch, $branches, true)) {
            $branches[] = $defaultBranch;
        }

        $finalBranches = [];

        for ($i = (array_search($branch, $branches, true) + 1), $c = \count($branches); $i < $c; ++$i) {
            $branchName = $branches[$i];
            $branchConfig = $this->config->getBranchConfig($branchName);

            if (! ($branchConfig->config['upmerge'] ?? true)) {
                $this->style->note(sprintf('Branch "%s" has upmerge disabled by "%s", and will be skipped.', $branchName, $branchConfig->configName));

                continue;
            }

            $finalBranches[] = $branchName;
        }

        return $finalBranches;
    }

    /**
     * @param string[] $branches
     */
    private function handleMerge(Args $args, string $branch, array $branches): int
    {
        $noSplit = $args->getOption('no-split');

        try {
            $this->git->ensureBranchInSync('upstream', $branch);
            $changedBranches = $this->mergeBranches($branch, $args->getOption('all') ? $branches : [reset($branches)], $noSplit);
            $this->git->pushToRemote('upstream', $changedBranches);
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

        $this->style->success('Branch(es) where merged.');

        return 0;
    }

    /**
     * @param string[] $branches
     *
     * @return string[]
     */
    private function mergeBranches(string $branch, array $branches, bool $noSplit): array
    {
        $sourceBranch = $branch;

        foreach ($branches as $destBranch) {
            $this->git->checkoutRemoteBranch('upstream', $destBranch);
            $this->git->ensureBranchInSync('upstream', $destBranch);
            $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $sourceBranch]);

            $this->style->note(sprintf('Merged "%s" into "%s"', $sourceBranch, $destBranch));

            if (! $noSplit) {
                $this->branchSplitsh->splitBranch($destBranch);
            }

            $sourceBranch = $destBranch;
        }

        $this->git->checkout($branch);

        return $branches;
    }

    /**
     * @param string[] $branches
     */
    private function handleDryMerge(Args $args, string $branch, array $branches): int
    {
        try {
            $this->git->ensureBranchInSync('upstream', $branch);
            $this->dryMergeBranches($branch, $args->getOption('all') ? $branches : [reset($branches)]);
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

        $this->style->success('[DRY-RUN] Branch(es) where merged.');

        return 0;
    }

    /**
     * @param string[] $branches
     */
    private function dryMergeBranches(string $branch, array $branches): void
    {
        $sourceBranch = $branch;

        foreach ($branches as $destBranch) {
            $this->git->ensureBranchInSync('upstream', $destBranch);
            $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $sourceBranch, $destBranch));
            $this->branchSplitsh->drySplitBranch($destBranch);

            $sourceBranch = $destBranch;
        }
    }
}
