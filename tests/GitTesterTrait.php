<?php

declare(strict_types=1);

namespace HubKit\Tests;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

trait GitTesterTrait
{
    protected $cwd;

    /** @var string|null */
    private $tempDir;
    /** @var BufferedOutput|null */
    private $cliOutput;

    protected function setUpTempDirectory()
    {
        $this->tempDir = realpath(sys_get_temp_dir()).'/hbk-sut/'.substr(hash('sha256', (\random_bytes(8))), 0, 10);
        mkdir($this->tempDir, 0777, true);
    }

    public function getTempDir(): string
    {
        if ($this->tempDir === null) {
            $this->setUpTempDirectory();
        }

        return $this->tempDir;
    }

    protected function createGitDirectory(string $directory): string
    {
        $currentCwd = $this->cwd;

        try {
            mkdir($directory, 0777, true);
            $this->cwd = $directory;

            $this->runCliCommand(['git', 'init']);
        } finally {
            $this->cwd = $currentCwd;
        }

        return str_replace('\\', '/', $directory);
    }

    protected function createBareGitDirectory(string $directory): string
    {
        $currentCwd = $this->cwd;

        try {
            mkdir($directory, 0777, true);
            $this->cwd = $directory;

            $this->runCliCommand(['git', 'init', '--bare']);
        } finally {
            $this->cwd = $currentCwd;
        }

        return str_replace('\\', '/', $directory);
    }

    protected function runCliCommand(array $cmd, ?string $cwd = null): void
    {
        $process = new Process($cmd, $cwd ?? $this->cwd);
        $process->mustRun();
    }

    protected function getProcessService(?string $cwd = null): TestCliProcess
    {
        return (new TestCliProcess($this->getCliOutput()))->setCwd($cwd ?? $this->cwd);
    }

    protected function getCliOutput(): BufferedOutput
    {
        if ($this->cliOutput === null) {
            $this->cliOutput = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);
        }

        return $this->cliOutput;
    }
}
