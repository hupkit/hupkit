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
    private $config;

    public function __construct(SymfonyStyle $style, SplitshGit $splitshGit, Git $git, GitHub $github, Config $config)
    {
        parent::__construct($style, $git, $github);
        $this->splitshGit = $splitshGit;
        $this->config = $config;
    }

    public function handle(Args $args): int
    {
        $this->git->guardWorkingTreeReady();
        $this->git->remoteUpdate('upstream');

        $configName = ['repos', $this->github->getHostname(), $this->github->getOrganization().'/'.$this->github->getRepository(), 'split'];
        $config = $this->config->get($configName);

        if (null === $config) {
            $this->style->error(
                sprintf('Unable to split repository: No targets were found in config "[%s]", update the configuration file.', implode('][', $configName))
            );

            return 2;
        }

        if (null === $branch = $args->getArgument('branch')) {
            $branch = $this->git->getActiveBranchName();
        } else {
            $this->git->checkoutRemoteBranch('upstream', $branch);
        }

        $this->style->title('Repository Split');
        $this->informationHeader($branch);

        if ($args->getOption('dry-run')) {
            return $this->drySplitRepository($branch, $config);
        }

        return $this->splitRepository($branch, $config);
    }

    private function splitRepository(string $branch, array $repos): int
    {
        $this->git->ensureBranchInSync('upstream', $branch);
        $this->splitshGit->checkPrecondition();

        $this->style->section(sprintf('%s sources to split', \count($repos)));

        foreach ($repos as $prefix => $config) {
            $url = \is_array($config) ? $config['url'] : $config;

            $this->style->writeln(sprintf('<fg=default;bg=default> Splitting %s to %s</>', $prefix, $url));
            $this->splitshGit->splitTo($branch, $prefix, $url);
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
            $this->style->writeln(sprintf('<fg=default;bg=default> [DRY-RUN] Splitting %s to %s</>', $prefix,
                \is_array($config) ? $config['url'] : $config
            ));
        }

        $this->style->newLine();
        $this->style->success('[DRY-RUN] Repository directories were split into there destination.');

        return 0;
    }
}
