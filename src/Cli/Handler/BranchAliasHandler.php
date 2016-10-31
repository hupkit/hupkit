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
use HubKit\Service\Git;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class BranchAliasHandler implements RequiresGitRepository
{
    private $git;

    public function __construct(Git $git)
    {
        $this->git = $git;
    }

    public function handle(Args $args, IO $io)
    {
        $alias = $this->git->getGitConfig('branch.master.alias');

        if (null !== $newAlias = $args->getArgument('alias')) {
            if (!preg_match('/^\d+\.\d+$/', $newAlias)) {
                throw new \InvalidArgumentException(
                    'A branch alias consists of major and minor version without any prefix or suffix. like: 1.2'
                );
            }

            $alias = $newAlias.'-dev';
            $this->git->setGitConfig('branch.master.alias', $alias, true);
        }

        $io->writeLine($alias);
    }
}
