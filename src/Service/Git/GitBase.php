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

namespace HubKit\Service\Git;

use HubKit\Service\CliProcess;
use HubKit\Service\Git;

class GitBase extends Git
{
    private readonly string $cwd;

    public function __construct(CliProcess $process, string $cwd = null)
    {
        $this->process = $process;
        $this->cwd = $cwd ?? getcwd();
    }

    protected function getCwd(): string
    {
        return $this->cwd;
    }
}
