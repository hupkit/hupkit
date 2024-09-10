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

namespace HubKit\Service;

use HubKit\BranchConfig;
use HubKit\Config;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * BranchSplitsh wraps around the SplitshGit service to the
 * branch configuration for performing operations.
 */
class BranchSplitsh
{
    public function __construct(
        private readonly SplitshGit $splitshGit,
        private readonly GitHub $github,
        private readonly Config $config,
        private readonly SymfonyStyle $style,
        private readonly Git $git
    ) {}

    /**
     * Split the prefix directory into another repository.
     *
     * Target configuration and whether this branch should be split et all
     * is automatically resolved from the configuration.
     *
     * @param string   $branch      The source branch to split from
     * @param string   $prefix      Directory prefix, relative to the root directory
     * @param string[] $filterFiles An optional list of files to check if any matches in the $prefix.
     *                              When passed, only when the files are matched in the prefix the
     *                              split is performed, null is returned otherwise
     *
     * @return array{0: string, 1: string, 2: string}|null Same as {@link SplitshGit::splitTo}
     */
    public function splitAtPrefix(string $branch, string $prefix, array $filterFiles = []): ?array
    {
        $config = $this->getConfigForPrefix($branch, $prefix);

        if ($filterFiles && ! SplitshGit::isPrefixInChangedFiles($prefix, $filterFiles)) {
            $this->style->note(\sprintf('No changed files where matched for "%s". And the split was ignored.', $prefix));

            return null;
        }

        $this->style->writeln(\sprintf('<fg=default;bg=default> Splitting %s to %s</>', $prefix, $config['url']));

        return $this->splitshGit->splitTo($branch, $prefix, $config['url']);
    }

    private function getConfigForPrefix(string $branch, string $prefix): mixed
    {
        $this->git->ensureBranchInSync(REMOTE_MAIN, $branch);
        $this->splitshGit->checkPrecondition();

        $branchConfig = $this->getBranchConfig($branch);

        if (! isset($branchConfig->config['split'][$prefix])) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unable to split repository at prefix: No entry found for "[%s][split][%s]".',
                    implode('][', $branchConfig->configPath),
                    $prefix
                )
            );
        }

        $config = $branchConfig->config['split'][$prefix];

        if ($config['url'] === false) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unable to split repository at prefix: Entry is disabled for "[%s][split][%s]".',
                    implode('][', $branchConfig->configPath),
                    $prefix
                )
            );
        }

        return $config;
    }

    private function getBranchConfig(string $branch): BranchConfig
    {
        $branchConfig = $this->config->getBranchConfig(
            $branch,
            $this->github->getHostname(),
            $this->github->getOrganization() . '/' . $this->github->getRepository()
        );

        if (empty($branchConfig->config['split'])) {
            $this->style->text(\sprintf('No repository-split targets were found in config "[%s]".', implode('][', $branchConfig->configPath)));
        } elseif ($branch !== $branchConfig->configName) {
            $this->style->text(\sprintf('Repository-split configuration for branch <fg=yellow>%s</> resolved from <fg=yellow>%s</>.', $branch, $branchConfig->configName));
        }

        return $branchConfig;
    }

    /**
     * Split all from the branch to their destinations, unlike splitTo()
     * this will pass when no destinations are found.
     *
     * @param string[] $filterFiles An optional list of files to check if any matches in the prefixes.
     *                              When passed, only prefixes matched in the files list are split
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function splitBranch(string $branch, array $filterFiles = []): array
    {
        $splits = $this->getSplit($this->getBranchConfig($branch));

        if (\count($splits) === 0) {
            return [];
        }

        $this->git->ensureBranchInSync(REMOTE_MAIN, $branch);
        $this->splitshGit->checkPrecondition();

        $ignored = [];
        $results = [];

        $splits = SplitshGit::filterOnlyChangedPrefixes($splits, $filterFiles, $ignored);

        $this->style->section(\sprintf('Splitting from %s to %d destinations', $branch, \count($splits)));

        foreach ($splits as $prefix => $config) {
            $split = $this->splitshGit->splitTo($branch, $prefix, $config['url']);

            if ($split === null) {
                continue;
            }

            $results[$prefix] = $split;
            $this->style->writeln(\sprintf('<fg=default;bg=default> Splitting %s to %s</>', $prefix, $config['url']));
        }

        if ($ignored) {
            $this->style->note('No changed files where matched for the following listed prefixes. And the splits have been ignored.');
            $this->style->listing(array_keys($ignored));
        }

        return $results;
    }

    /** @return array<string, array<string, mixed>> */
    private function getSplit(BranchConfig $branchConfig): array
    {
        return array_filter($branchConfig->config['split'] ?? [], static fn ($v): bool => $v['url'] !== false);
    }

    /**
     * Synchronize the source tag to split repositories.
     *
     * This method re-uses the information provided by splitTo().
     * Existing tags are silently ignored.
     *
     * @param string $versionStr Version (without prefix) for the tag name
     *
     * @return int The number of tags synchronized
     */
    public function syncTags(string $branch, string $versionStr, ?string $onlyChangesSince = null): int
    {
        if ($onlyChangesSince !== null) {
            $changedFiles = $this->git->getFileChangesBetween($onlyChangesSince, $branch);
        }

        $splits = $this->splitBranch($branch, $changedFiles ?? []);

        // Check if there are any splits to prevent duplicate messages.
        if (\count($splits) === 0) {
            return 0;
        }

        $branchConfig = $this->getBranchConfig($branch);
        $count = 0;

        foreach ($splits as $prefix => $split) {
            if (($branchConfig->config['split'][$prefix]['sync-tags'] ?? $branchConfig->config['sync-tags'] ?? true) === false) {
                $this->style->writeln(\sprintf('<fg=default;bg=default> Repository-split tag synchronizing is disabled for directory %s</>', $prefix));

                continue;
            }

            $this->style->writeln(\sprintf('<fg=default;bg=default> Tagging split release for directory %s</>', $prefix));

            $this->splitshGit->syncTag($versionStr, $split[1], $branch, $split[0]);
            ++$count;
        }

        return $count;
    }

    /**
     * Simulate (dry-run) splitting the prefix directory into another repository.
     *
     * Target configuration and whether this branch should be split et all
     * is automatically resolved from the configuration.
     *
     * @param string   $branch      The source branch to split from
     * @param string   $prefix      Directory prefix, relative to the root directory
     * @param string[] $filterFiles An optional list of files to check if any matches in the $prefix.
     *                              When passed, only when the files are matched in the prefix the
     *                              split is performed, null is returned otherwise
     */
    public function drySplitAtPrefix(string $branch, string $prefix, array $filterFiles = []): void
    {
        $config = $this->getConfigForPrefix($branch, $prefix);

        if ($filterFiles && SplitshGit::isPrefixInChangedFiles($prefix, $filterFiles)) {
            $this->style->note(\sprintf('No changed files where matched for "%s". And the split would have been ignored.', $prefix));

            return;
        }

        $this->style->writeln(\sprintf('<fg=default;bg=default> [DRY-RUN] Splitting %s to %s</>', $prefix, $config['url']));
    }

    /**
     * @param string[] $filterFiles An optional list of files to check if any matches in the prefixes.
     *                              When passed, only prefixes matched in the files list are split
     *
     * @return int The number of splits
     */
    public function drySplitBranch(string $branch, array $filterFiles = []): int
    {
        $splits = $this->getSplit($this->getBranchConfig($branch));

        if (\count($splits) === 0) {
            return 0;
        }

        $this->splitshGit->checkPrecondition();

        $ignored = [];
        $splits = SplitshGit::filterOnlyChangedPrefixes($splits, $filterFiles, $ignored);

        $this->style->section(\sprintf('Would be splitting branch %s to %d destinations', $branch, \count($splits)));

        foreach ($splits as $prefix => $config) {
            $this->style->writeln(\sprintf('<fg=default;bg=default> [DRY-RUN] Splitting %s to %s</>', $prefix, $config['url']));
        }

        if ($ignored) {
            $this->style->note('No changed files where matched for the following listed prefixes. And the splits would bee ignored.');
            $this->style->listing(array_keys($ignored));
        }

        return \count($splits);
    }
}
