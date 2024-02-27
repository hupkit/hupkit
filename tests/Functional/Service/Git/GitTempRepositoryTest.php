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

namespace HubKit\Tests\Functional\Service\Git;

use HubKit\Service\Filesystem;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Tests\Functional\GitTesterTrait;
use HubKit\Tests\Functional\TestCliProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class GitTempRepositoryTest extends TestCase
{
    use GitTesterTrait;

    private ?string $origCwd = null;
    private string $rootRepository;

    private Filesystem $filesystem;
    private TestCliProcess $cliProcess;
    private GitTempRepository $gitTempRepository;

    /** @before */
    public function setUpLocalRepository(): void
    {
        $this->origCwd = getcwd();

        $tempDir = $this->getTempDir();
        $this->cwd = $this->rootRepository = $this->createGitDirectory($tempDir . '/root');
        $this->commitFileToRepository('foo.txt', $this->rootRepository, 'foo.txt in master on root');
        $this->runCliCommand(['git', 'checkout', '-b', '_hubkit']);
        $this->commitFileToRepository('config.php', $this->rootRepository, 'config.php in _hubkit on root');
        $this->runCliCommand(['git', 'checkout', 'master']);

        $this->cwd = $this->rootRepository;

        $this->filesystem ??= new Filesystem($tempDir);
        $this->cliProcess = $this->getProcessService($this->rootRepository);
        $this->cliProcess->ignoreCwdChangeWhen(static fn (string $val): bool => str_contains($val, '/hubkit/stor/repo_'));

        $this->gitTempRepository ??= new GitTempRepository($this->cliProcess, $this->filesystem);
    }

    /** @after */
    public function restoreCwd(): void
    {
        chdir($this->origCwd);
        $this->filesystem->clearTempFiles();
    }

    /** @test */
    public function it_creates_temporary_repository(): void
    {
        $location = $this->gitTempRepository->getLocal($this->rootRepository);

        self::assertFileExists($location . '/.git');
        // Not checked out yet, so directory is bare
        self::assertFileDoesNotExist($location . '/foo.txt');
        self::assertFileDoesNotExist($location . '/config.php');

        $location2 = $this->gitTempRepository->getLocal($this->rootRepository);

        self::assertEquals($location, $location2);
    }

    /** @test */
    public function it_creates_temporary_repository_for_specific_branch(): void
    {
        $location = $this->gitTempRepository->getLocal($this->rootRepository, 'master');

        self::assertFileExists($location . '/.git');
        self::assertFileExists($location . '/foo.txt');
        self::assertFileDoesNotExist($location . '/config.php');

        // Repository already exists, but no specific branch was given
        $location2 = $this->gitTempRepository->getLocal($this->rootRepository);

        self::assertEquals($location, $location2);
        self::assertFileExists($location . '/.git');
        self::assertFileExists($location . '/foo.txt');
    }

    /** @test */
    public function it_updates_temporary_repository_for_specific_branch(): void
    {
        $location = $this->gitTempRepository->getLocal($this->rootRepository, 'master');

        self::assertFileExists($location . '/.git');
        self::assertFileExists($location . '/foo.txt');
        self::assertFileDoesNotExist($location . '/config.php');

        $this->commitFileToRepository('config.php', $this->rootRepository, 'config.php in master on root');

        // Repository already exists, update it
        $location2 = $this->gitTempRepository->getLocal($this->rootRepository, 'master');

        self::assertEquals($location, $location2);
        self::assertFileExists($location . '/config.php');
    }

    /** @test */
    public function it_creates_temporary_repository_with_different_specific_branch(): void
    {
        $location = $this->gitTempRepository->getLocal($this->rootRepository, 'master');

        self::assertFileExists($location . '/.git');
        self::assertFileExists($location . '/foo.txt');
        self::assertFileDoesNotExist($location . '/config.php');

        // Repository already exists, but different branch was provided
        $location2 = $this->gitTempRepository->getLocal($this->rootRepository, '_hubkit');

        self::assertEquals($location, $location2);
        self::assertFileExists($location . '/.git');
        self::assertFileExists($location . '/foo.txt');
        self::assertFileExists($location . '/config.php');
    }

    /** @test */
    public function it_supports_being_pushed_to(): void
    {
        $location = $this->gitTempRepository->getLocal($this->rootRepository, 'master');
        $this->commitFileToRepository('foo2.txt', $this->rootRepository, 'foo2.txt in master on root');

        self::assertFileDoesNotExist($location . '/foo2.txt');

        $this->cliProcess->mustRun(new Process(['git', 'push', $location, 'master:refs/heads/master'], $this->rootRepository));
        $this->cliProcess->mustRun(new Process(['git', 'reset', '--hard'], $location));

        self::assertFileExists($location . '/foo2.txt');
    }
}
