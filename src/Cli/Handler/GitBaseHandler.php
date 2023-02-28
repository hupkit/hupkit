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

use HubKit\Cli\RequiresGitRepository;
use HubKit\Config;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class GitBaseHandler implements RequiresGitRepository
{
    protected $style;
    protected $git;
    protected $github;

    public function __construct(SymfonyStyle $style, Git $git, GitHub $github, protected Config $config)
    {
        $this->style = $style;
        $this->git = $git;
        $this->github = $github;
    }

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
}
