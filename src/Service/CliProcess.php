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

namespace HubKit\Service;

use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class CliProcess
{
    /**
     * @var ProcessHelper
     */
    private $processHelper;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $helperSet = new HelperSet([new DebugFormatterHelper(), new ProcessHelper()]);
        $this->processHelper = $helperSet->get('process');
        $this->output = $output;
    }

    /**
     * Runs an external process.
     *
     * @param string|array|Process $cmd       An instance of Process or an array of arguments to escape and run or a command to run
     * @param string|null          $error     An error message that must be displayed if something went wrong
     * @param callable|null        $callback  A PHP callback to run whenever there is some
     *                                        output available on STDOUT or STDERR
     * @param int                  $verbosity The threshold for verbosity
     *
     * @return Process The process that ran
     */
    public function run($cmd, $error = null, callable $callback = null, $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE)
    {
        return $this->processHelper->run($this->output, $cmd, $error, $callback, $verbosity);
    }

    /**
     * Runs the process.
     *
     * This is identical to run() except that an exception is thrown if the process
     * exits with a non-zero exit code.
     *
     * @param string|Process $cmd      An instance of Process or a command to run
     * @param string|null    $error    An error message that must be displayed if something went wrong
     * @param callable|null  $callback A PHP callback to run whenever there is some
     *                                 output available on STDOUT or STDERR
     *
     * @throws ProcessFailedException
     *
     * @return Process The process that ran
     *
     * @see run()
     */
    public function mustRun($cmd, $error = null, callable $callback = null)
    {
        return $this->processHelper->mustRun($this->output, $cmd, $error, $callback);
    }

    /**
     * Wraps a Process callback to add debugging output.
     *
     * @param Process       $process  The Process
     * @param callable|null $callback A PHP callable
     *
     * @return callable
     */
    public function wrapCallback(Process $process, callable $callback = null)
    {
        return $this->processHelper->wrapCallback($this->output, $process, $callback);
    }
}
