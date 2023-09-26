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
     * @param string $branch The source branch to split from
     * @param string $prefix Directory prefix, relative to the root directory
     *
     * @return array{0: string, 1: string, 2: string}|null Same as {@link SplitshGit::splitTo}
     */
    public function splitAtPrefix(string $branch, string $prefix): ?array
    {
        $config = $this->getConfigForPrefix($branch, $prefix);
        $this->style->writeln(sprintf('<fg=default;bg=default> Splitting %s to %s</>', $prefix, $config['url']));

        return $this->splitshGit->splitTo($branch, $prefix, $config['url']);
    }

    private function getConfigForPrefix(string $branch, string $prefix): mixed
    {
        $this->git->ensureBranchInSync('upstream', $branch);
        $this->splitshGit->checkPrecondition();

        $branchConfig = $this->getBranchConfig($branch);

        if (! isset($branchConfig->config['split'][$prefix])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unable to split repository at prefix: No entry found for "[%s][split][%s]".',
                    implode('][', $branchConfig->configPath),
                    $prefix
                )
            );
        }

        $config = $branchConfig->config['split'][$prefix];

        if ($config['url'] === false) {
            throw new \InvalidArgumentException(
                sprintf(
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
        $branchConfig = $this->config->getBranchConfig($this->github->getHostname(), $this->github->getOrganization() . '/' . $this->github->getRepository(), $branch);

        if (empty($branchConfig->config['split'])) {
            $this->style->text(sprintf('No repository-split targets were found in config "[%s]".', implode('][', $branchConfig->configPath)));
        } elseif ($branch !== $branchConfig->configName) {
            $this->style->text(sprintf('Repository-split configuration for branch <fg=yellow>%s</> resolved from <fg=yellow>%s</>.', $branch, $branchConfig->configName));
        }

        return $branchConfig;
    }

    /**
     * Split all from the branch to their destinations, unlike splitTo()
     * this will pass when no destinations are found.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function splitBranch(string $branch): array
    {
        $splits = $this->getSplit($this->getBranchConfig($branch));

        if (\count($splits) === 0) {
            return [];
        }

        $this->git->ensureBranchInSync('upstream', $branch);
        $this->splitshGit->checkPrecondition();

        $results = [];

        $this->style->section(sprintf('Splitting from %s to %d destinations', $branch, \count($splits)));

        foreach ($splits as $prefix => $config) {
            $split = $this->splitshGit->splitTo($branch, $prefix, $config['url']);

            if ($split === null) {
                continue;
            }

            $results[$prefix] = $split;
            $this->style->writeln(sprintf('<fg=default;bg=default> Splitting %s to %s</>', $prefix, $config['url']));
        }

        return $results;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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
    public function syncTags(string $branch, string $versionStr): int
    {
        $splits = $this->splitBranch($branch);

        // Check if there are any splits to prevent duplicate messages.
        if (\count($splits) === 0) {
            return 0;
        }

        $branchConfig = $this->getBranchConfig($branch);
        $count = 0;

        foreach ($splits as $prefix => $split) {
            if (($branchConfig->config['split'][$prefix]['sync-tags'] ?? $branchConfig->config['sync-tags'] ?? true) === false) {
                $this->style->writeln(sprintf('<fg=default;bg=default> Repository-split tag synchronizing is disabled for directory %s</>', $prefix));

                continue;
            }

            $this->splitshGit->syncTag($versionStr, $split[1], $branch, $split[0]);
            ++$count;
        }

        return $count;
    }

    public function drySplitAtPrefix(string $branch, string $prefix): void
    {
        $config = $this->getConfigForPrefix($branch, $prefix);

        $this->style->writeln(sprintf('<fg=default;bg=default> [DRY-RUN] Splitting %s to %s</>', $prefix, $config['url']));
    }

    /**
     * @return int The number of splits
     */
    public function drySplitBranch(string $branch): int
    {
        $splits = $this->getSplit($this->getBranchConfig($branch));

        if (\count($splits) === 0) {
            return 0;
        }

        $this->splitshGit->checkPrecondition();

        $this->style->section(sprintf('Would be splitting branch %s to %d destinations', $branch, \count($splits)));

        foreach ($splits as $prefix => $config) {
            $this->style->writeln(sprintf('<fg=default;bg=default> [DRY-RUN] Splitting %s to %s</>', $prefix, $config['url']));
        }

        return \count($splits);
    }
}
