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
use HubKit\Service\SplitshGit;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class UpMergeHandler extends GitBaseHandler
{
    private $process;
    private $config;
    private $splitshGit;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, CliProcess $process, Config $config, SplitshGit $splitshGit)
    {
        parent::__construct($style, $git, $github);
        $this->process = $process;
        $this->config = $config;
        $this->splitshGit = $splitshGit;
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

        if ($args->getOption('dry-run')) {
            return $this->handleDryMerge($args, $branch);
        }

        return $this->handleMerge($args, $branch);
    }

    private function mergeAllBranches(string $branch, array $splitTargets): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            return [];
        }

        $this->git->ensureBranchInSync('upstream', $branch);

        $branches[] = 'master';
        $changedBranches = [];

        for ($i = $idx + 1, $c = \count($branches); $i < $c; ++$i) {
            $this->git->checkoutRemoteBranch('upstream', $branches[$i]);
            $this->git->ensureBranchInSync('upstream', $branches[$i]);
            $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $branches[$i - 1]]);

            $this->style->note(sprintf('Merged "%s" into "%s"', $branches[$i - 1], $branches[$i]));
            $this->splitRepository($branches[$i], $splitTargets);

            $changedBranches[] = $branches[$i];
        }

        $this->git->checkout($branch);

        return $changedBranches;
    }

    private function mergeSingleBranch(string $branch, array $splitTargets): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            return [];
        }

        if ('' === ($nextVersion = $branches[$idx + 1] ?? '')) {
            $nextVersion = 'master';
        }

        $this->git->ensureBranchInSync('upstream', $branch);
        $this->git->checkoutRemoteBranch('upstream', $nextVersion);
        $this->git->ensureBranchInSync('upstream', $nextVersion);
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', $branch]);
        $this->splitRepository($nextVersion, $splitTargets);

        $this->style->note(sprintf('Merged "%s" into "%s"', $branch, $nextVersion));

        $this->git->checkout($branch);

        return [$nextVersion];
    }

    private function dryMergeAllBranches(string $branch): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            $this->style->warning(sprintf('Branch "%s" is not a supported version branch.', $branch));

            return [];
        }

        $this->git->ensureBranchInSync('upstream', $branch);

        $branches[] = 'master';
        $changedBranches = [];

        for ($i = $idx + 1, $c = \count($branches); $i < $c; ++$i) {
            $this->git->ensureBranchInSync('upstream', $branches[$i]);
            $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $branches[$i - 1], $branches[$i]));

            $changedBranches[] = $branches[$i];
        }

        return $changedBranches;
    }

    private function dryMergeSingleBranch(string $branch): array
    {
        $branches = $this->git->getVersionBranches('upstream');

        // Current is not a version branch, so ignore.
        if (false === $idx = array_search($branch, $branches, true)) {
            $this->style->warning(sprintf('Branch "%s" is not a supported version branch.', $branch));

            return [];
        }

        if ('' === ($nextVersion = $branches[$idx + 1] ?? '')) {
            $nextVersion = 'master';
        }

        $this->git->ensureBranchInSync('upstream', $branch);
        $this->git->ensureBranchInSync('upstream', $nextVersion);
        $this->style->note(sprintf('[DRY-RUN] Merged "%s" into "%s"', $branch, $nextVersion));

        return [$nextVersion];
    }

    private function handleMerge(Args $args, $branch)
    {
        try {
            if ($args->getOption('all')) {
                $changedBranches = $this->mergeAllBranches($branch, $this->getSplitTargets($args));
            } else {
                $changedBranches = $this->mergeSingleBranch($branch, $this->getSplitTargets($args));
            }

            if ([] === $changedBranches) {
                $this->style->success('Nothing to do here or not a version branch.');

                return 0;
            }

            $this->git->pushToRemote('upstream', $changedBranches, true);
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

    private function handleDryMerge(Args $args, $branch)
    {
        try {
            if ($args->getOption('all')) {
                $changedBranches = $this->dryMergeAllBranches($branch);
            } else {
                $changedBranches = $this->dryMergeSingleBranch($branch);
            }

            if ([] === $changedBranches) {
                $this->style->success(
                    'This operation would not perform anything, '.
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

    private function splitRepository(string $branch, array $splitTargets): void
    {
        if ($splitTargets === []) {
            return;
        }

        $this->style->section(sprintf('Starting repository split operation for %s', $branch));
        $this->style->progressStart(\count($splitTargets));

        foreach ($splitTargets as $prefix => $splitConfigs) {
            $url = \is_array($splitConfigs) ? $splitConfigs['url'] : $splitConfigs;

            $this->style->progressAdvance();
            $this->splitshGit->splitTo($branch, $prefix, $url);
        }
    }

    private function getSplitTargets(Args $args): array
    {
        $splitTargets = $this->config->get(['repos', $this->github->getHostname(), $this->github->getOrganization().'/'.$this->github->getRepository(), 'split']);

        if ($args->getOption('no-split') || $splitTargets === null) {
            return [];
        }

        if ($splitTargets !== null) {
            $this->splitshGit->checkPrecondition();
        }

        return $splitTargets;
    }
}
