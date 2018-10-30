<?php

declare(strict_types=1);

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
}
