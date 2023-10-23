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

namespace HubKit\Cli\Handler;

use HubKit\Cli\RequiresGitRepository;
use HubKit\Config;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class GitBaseHandler implements RequiresGitRepository
{
    public function __construct(
        protected SymfonyStyle $style,
        protected Git $git,
        protected GitHub $github,
        protected Config $config
    ) {}

    protected function informationHeader(string $branch = null): void
    {
        $hostname = $this->github->getHostname();

        $this->style->writeln(
            sprintf(
                '<fg=cyan>Working on</> <fg=yellow>%s%s/%s</> <fg=cyan>(branch</> <fg=yellow>%s</><fg=cyan>)</>',
                $hostname === GitHub::DEFAULT_HOST ? '' : $hostname . ':',
                $this->github->getOrganization(),
                $this->github->getRepository(),
                $branch ?? $this->git->getActiveBranchName()
            )
        );
        $this->style->newLine();

        if ($this->config->has('_local')) {
            $this->style->note('Using local configuration from branch "_hubkit".');
        }
    }

    protected function guardMaintained(string $branch = null): void
    {
        $branch ??= $this->git->getActiveBranchName();
        $branchConfig = $this->config->getBranchConfig($branch);

        if (($branchConfig->config['maintained'] ?? true) === false) {
            $this->style->warning(sprintf('The "%s" branch is marked as unmaintained!', $branch));

            if (! $this->style->confirm('Do you want to continue this operation anyway?', false)) {
                throw new \RuntimeException('User aborted.');
            }
        }
    }
}
