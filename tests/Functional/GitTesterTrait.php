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

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

trait GitTesterTrait
{
    protected $cwd;

    /** @var string|null */
    private $tempDir;
    /** @var BufferedOutput|null */
    private $cliOutput;

    protected function setUpTempDirectory(): void
    {
        $this->tempDir = realpath(sys_get_temp_dir()) . '/hbk-sut/' . mb_substr(hash('sha256', random_bytes(8)), 0, 10);
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

            $this->runCliCommand(['git', 'init', '-b', 'master']);
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

            $this->runCliCommand(['git', 'init', '--bare', '-b', 'master']);
        } finally {
            $this->cwd = $currentCwd;
        }

        return str_replace('\\', '/', $directory);
    }

    protected function runCliCommand(array $cmd, ?string $cwd = null): Process
    {
        $process = new Process($cmd, $cwd ?? $this->cwd);
        $process->mustRun();

        return $process;
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

    protected function commitFileToRepository(string $filename, string $repository): void
    {
        $this->runCliCommand(['touch', $filename], $repository);
        $this->runCliCommand(['git', 'add', $filename], $repository);
        $this->runCliCommand(['git', 'commit', '-m', 'I am a dwarf, I am digging a hole'], $repository);
    }

    protected function addRemote(string $remoteName, string $remoteRepository, ?string $sourceRepository = null): void
    {
        $this->runCliCommand(['git', 'remote', 'add', $remoteName, 'file://' . $remoteRepository], $sourceRepository);
    }

    protected function givenRemoteBranchesExist(iterable $branches, string $remote = 'origin'): void
    {
        foreach ($branches as $branch) {
            $this->runCliCommand(['git', 'branch', $branch]);
        }

        $this->runCliCommand(['git', 'push', $remote, '--all'], $this->localRepository);

        // Clean-up branches to ensure the remote is actually checked, and not the local version
        foreach ($branches as $branch) {
            $this->runCliCommand(['git', 'branch', '-D', $branch]);
        }
    }

    protected function setUpstreamRepository(): void
    {
        $upstreamRepos = $this->createBareGitDirectory($this->getTempDir() . '/git3');
        $this->addRemote('upstream', $upstreamRepos, $this->localRepository);
        $this->runCliCommand(['git', 'push', 'upstream', 'master'], $this->localRepository);
    }

    protected function givenLocalBranchesExist(iterable $branches): void
    {
        foreach ($branches as $branch) {
            $this->runCliCommand(['git', 'branch', $branch]);
        }
    }
}
