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

use HubKit\Config;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Service\GitHub;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ConfigBaseHandler extends GitBaseHandler
{
    public function __construct(
        SymfonyStyle $style,
        Git $git,
        GitHub $github,
        Config $config,
        protected readonly Filesystem $filesystem,
        protected readonly GitTempRepository $tempRepository,
    ) {
        parent::__construct($style, $git, $github, $config);
    }

    protected function ensureRemoteIsNotDiverged(): void
    {
        $diffStatus = $this->git->getRemoteDiffStatus('upstream', '_hubkit');

        if ($diffStatus !== Git::STATUS_UP_TO_DATE) {
            throw new \RuntimeException(
                'The remote "_hubkit" branch and local branch have diverged. Run the "sync-config" command first.'
            );
        }
    }
}
