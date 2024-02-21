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

namespace HubKit\Service;

use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CliProcess
{
    private readonly ProcessHelper $processHelper;

    public function __construct(private readonly OutputInterface $output)
    {
        $helperSet = new HelperSet([new DebugFormatterHelper(), new ProcessHelper()]);
        $this->processHelper = $helperSet->get('process');
    }

    /**
     * Runs an external process and waits until it is terminated.
     *
     * @throws ProcessFailedException
     */
    public function startAndWait(Process $process): void
    {
        $process->start();
        $process->wait();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Runs an external process.
     *
     * @param array<int, string|int>|Process $cmd       An instance of Process or an array of arguments to escape and run or a command to run
     * @param string|null                    $error     An error message that must be displayed if something went wrong
     * @param callable|null                  $callback  A PHP callback to run whenever there is some
     *                                                  output available on STDOUT or STDERR
     * @param int                            $verbosity The threshold for verbosity
     *
     * @return Process The process that ran
     */
    public function run(array | Process $cmd, string $error = null, callable $callback = null, int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE): Process
    {
        return $this->processHelper->run($this->output, $cmd, $error, $callback, $verbosity);
    }

    /**
     * Runs the process.
     *
     * This is identical to run() except that an exception is thrown if the process
     * exits with a non-zero exit code.
     *
     * @param array<int, string|int>|Process $cmd      An instance of Process or an array of arguments to escape and run or a command to run
     * @param string|null                    $error    An error message that must be displayed if something went wrong
     * @param callable|null                  $callback A PHP callback to run whenever there is some
     *                                                 output available on STDOUT or STDERR
     *
     * @return Process The process that ran
     *
     * @throws ProcessFailedException
     *
     * @see run()
     */
    public function mustRun(array | Process $cmd, string $error = null, callable $callback = null): Process
    {
        return $this->processHelper->mustRun($this->output, $cmd, $error, $callback);
    }

    /**
     * Wraps a Process callback to add debugging output.
     *
     * @param Process       $process  The Process
     * @param callable|null $callback A PHP callable
     */
    public function wrapCallback(Process $process, callable $callback = null): callable
    {
        return $this->processHelper->wrapCallback($this->output, $process, $callback);
    }
}
