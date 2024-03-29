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

namespace HubKit\Tests\Functional;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\Process;

trait GitTesterTrait
{
    protected string $localRepository;
    protected null | string $cwd = null;

    private ?string $tempDir = null;
    private ?OutputInterface $cliOutput = null;

    protected function setUpTempDirectory(): void
    {
        $this->tempDir = realpath(sys_get_temp_dir()) . '/hbk-sut/' . mb_substr(hash('sha256', random_bytes(14)), 0, 16);
        mkdir($this->tempDir, 0777, true);
    }

    public function getTempDir(): string
    {
        if ($this->tempDir === null) {
            $this->setUpTempDirectory();
        }

        return $this->tempDir;
    }

    protected function createGitDirectory(string $directory, bool $allowPush = true): string
    {
        mkdir($directory, 0777, true);
        $this->runCliCommand(['git', 'init', '-b', 'master'], $directory);

        if ($allowPush) {
            (new Process(['git', 'config', '--local', '--unset', 'receive.denyCurrentBranch'], $directory))->run();
            (new Process(['git', 'config', '--local', 'receive.denyCurrentBranch', 'ignore'], $directory))->mustRun();
        }

        return str_replace('\\', '/', $directory);
    }

    protected function createBareGitDirectory(string $directory): string
    {
        mkdir($directory, 0777, true);
        $this->runCliCommand(['git', 'init', '--bare', '-b', 'master'], $directory);

        return str_replace('\\', '/', $directory);
    }

    /**
     * @param array<int, string> $cmd
     */
    protected function runCliCommand(array $cmd, string $cwd = null): Process
    {
        $process = new Process($cmd, $cwd ?? $this->cwd);
        $process->mustRun();

        return $process;
    }

    protected function getProcessService(string $cwd = null): TestCliProcess
    {
        return (new TestCliProcess($this->getCliOutput()))->setCwd($cwd ?? $this->cwd);
    }

    protected function getCliOutput(): OutputInterface
    {
        $this->cliOutput ??= new StreamOutput(fopen('php://memory', 'w'), BufferedOutput::VERBOSITY_VERBOSE, false);

        return $this->cliOutput;
    }

    protected function commitFileToRepository(string $filename, string $repository, string $contents = '[Empty]'): void
    {
        $filePath = \dirname($repository . '/' . $filename);

        if (! file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }

        file_put_contents($repository . '/' . $filename, $contents);
        $this->runCliCommand(['git', 'add', $filename], $repository);
        $this->runCliCommand(['git', 'commit', '-m', 'I am a dwarf, I am digging a hole'], $repository);
    }

    protected function addRemote(string $remoteName, string $remoteRepository, string $sourceRepository = null): void
    {
        $this->runCliCommand(['git', 'remote', 'add', $remoteName, 'file://' . $remoteRepository], $sourceRepository);
    }

    /**
     * @param iterable<string> $branches
     */
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
