<?php

declare(strict_types=1);

namespace HubKit\Tests\Functional;

use HubKit\Service\CliProcess;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class TestCliProcess extends CliProcess
{
    private $cwd;

    public function setCwd(?string $cwd): self
    {
        $this->cwd = $cwd;

        return $this;
    }

    public function run($cmd, $error = null, callable $callback = null, $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE)
    {
        return parent::run($this->wrapProcessorForCmd($cmd), $error, $callback, $verbosity);
    }

    public function mustRun($cmd, $error = null, callable $callback = null)
    {
        return parent::mustRun($this->wrapProcessorForCmd($cmd), $error, $callback);
    }

    private function wrapProcessorForCmd($cmd): Process
    {
        if ($cmd instanceof Process) {
            $cmd->setWorkingDirectory($this->cwd);
        } else {
            $cmd = new Process($cmd, $this->cwd);
        }

        return $cmd;
    }
}
