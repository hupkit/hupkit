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
use HubKit\Service\GitHub;
use HubKit\Service\SplitshGit;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;

final class SplitRepoHandler extends GitBaseHandler
{
    private $splitshGit;

    public function __construct(SymfonyStyle $style, SplitshGit $splitshGit, Git $git, GitHub $github, Config $config)
    {
        parent::__construct($style, $git, $github, $config);
        $this->splitshGit = $splitshGit;
        $this->config = $config;
    }

    public function handle(Args $args): int
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        $branch = $args->getArgument('branch');

        if ($branch !== null) {
            $useCurrentBranch = false;
        } else {
            $branch = $this->git->getActiveBranchName();
            $useCurrentBranch = true;
        }

        $config = $this->config->getBranchConfig($this->github->getHostname(), $this->github->getOrganization() . '/' . $this->github->getRepository(), $branch);
        $split = $config->config['split'] ?? [];

        if ($split === []) {
            $configName = $config->configPath ?? [];

            $this->style->error(
                sprintf('Unable to split repository: No targets were found in config "[%s][split]", update the (local) configuration file.', implode('][', $configName))
            );

            return 2;
        }

        if (! $useCurrentBranch) {
            $this->git->checkoutRemoteBranch('upstream', $branch);
        }

        $this->style->title('Repository Split');
        $this->informationHeader($branch);
        $this->style->text(sprintf('Split configuration resolved from branch <fg=yellow>%s</>.', $config->configName));

        if ($args->getOption('dry-run')) {
            return $this->drySplitRepository($branch, $split);
        }

        return $this->splitRepository($branch, $split);
    }

    private function splitRepository(string $branch, array $repos): int
    {
        $this->git->ensureBranchInSync('upstream', $branch);
        $this->splitshGit->checkPrecondition();

        $this->style->section(sprintf('%s sources to split', \count($repos)));

        foreach ($repos as $prefix => $config) {
            if ($config['url'] === false) {
                continue;
            }

            $this->style->writeln(sprintf('<fg=default;bg=default> Splitting %s to %s</>', $prefix, $config['url']));
            $this->splitshGit->splitTo($branch, $prefix, $config['url']);
        }

        $this->style->success('Repository directories were split into there destination.');

        return 0;
    }

    private function drySplitRepository(string $branch, array $repos): int
    {
        $this->git->ensureBranchInSync('upstream', $branch);
        $this->splitshGit->checkPrecondition();

        $this->style->section(sprintf('%s sources to split', \count($repos)));

        foreach ($repos as $prefix => $config) {
            if ($config['url'] === false) {
                continue;
            }

            $this->style->writeln(sprintf('<fg=default;bg=default> [DRY-RUN] Splitting %s to %s</>', $prefix, $config['url']));
        }

        $this->style->newLine();
        $this->style->success('[DRY-RUN] Repository directories were split into there destination.');

        return 0;
    }
}
