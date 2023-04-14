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

namespace HubKit\Tests\Functional;

use HubKit\Service\CliProcess;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class TestCliProcess extends CliProcess
{
    private $cwd;

    /**
     * @var callable|null
     */
    private $ignoreCwdChange;

    public function setCwd(?string $cwd): self
    {
        $this->cwd = $cwd;

        return $this;
    }

    public function ignoreCwdChangeWhen(?callable $fn): self
    {
        $this->ignoreCwdChange = $fn;

        return $this;
    }

    public function run($cmd, $error = null, callable $callback = null, $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE): Process
    {
        return parent::run($this->wrapProcessorForCmd($cmd), $error, $callback, $verbosity);
    }

    public function mustRun(array | Process $cmd, string $error = null, callable $callback = null): Process
    {
        return parent::mustRun($this->wrapProcessorForCmd($cmd), $error, $callback);
    }

    private function wrapProcessorForCmd($cmd): Process
    {
        if (! $cmd instanceof Process) {
            return new Process($cmd, $this->cwd);
        }

        if ($this->ignoreCwdChange === null || ! ($this->ignoreCwdChange)($cmd->getWorkingDirectory())) {
            $cmd->setWorkingDirectory($this->cwd);
        }

        return $cmd;
    }
}
