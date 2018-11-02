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

namespace HubKit\Service\Git;

use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use Symfony\Component\Console\Style\StyleInterface;

class GitBranch extends Git
{
    public function __construct(CliProcess $process, StyleInterface $style)
    {
        $this->process = $process;
        $this->style = $style;
    }

    public function checkoutNew(string $branch)
    {
        $this->checkout($branch, true);
    }
}
