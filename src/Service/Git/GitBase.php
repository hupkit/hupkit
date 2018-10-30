<?php

declare(strict_types=1);

namespace HubKit\Service\Git;

use HubKit\Service\CliProcess;
use HubKit\Service\Git;

class GitBase extends Git
{
    private $cwd;

    public function __construct(CliProcess $process, string $cwd)
    {
        $this->process = $process;
        $this->cwd = $cwd;
    }

    protected function getCwd(): string
    {
        return $this->cwd;
    }
}
