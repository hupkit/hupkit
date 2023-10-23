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

use HubKit\Exception\GitFileNotFound;
use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git\GitBranch;
use HubKit\Service\Git\GitConfig;
use HubKit\Service\Git\GitFileReader;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Tests\Functional\GitTesterTrait;
use HubKit\Tests\Functional\TestCliProcess;
use HubKit\Tests\Handler\SymfonyStyleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @internal
 */
final class GitFileReaderTest extends TestCase
{
    use GitTesterTrait;
    use SymfonyStyleTrait;

    private ?string $origCwd = null;
    private string $rootRepository;
    private string $secondRepository;

    private Filesystem $filesystem;
    private TestCliProcess $cliProcess;
    private GitTempRepository $gitTempRepository;
    private readonly StyleInterface $style;

    /** @before */
    public function setUpLocalRepository(): void
    {
        $this->origCwd = getcwd();

        // Set-up
        //
        // - Create a local repository (known as the root)
        // - Has a branch named 'master' and one named '_hubkit'
        //   - Branch _hubkit has a file named 'config.php'
        //   - Branch master has a file named 'foo3.txt' (added after the pull in second)
        //
        // - Create secondary repository (with remote origin to root)
        //   - Branch master has file named foo2.txt

        $this->cwd = $this->rootRepository = $this->createGitDirectory($this->getTempDir() . '/root');
        $this->commitFileToRepository('foo.txt', $this->rootRepository, 'foo.txt in master on root');
        $this->runCliCommand(['git', 'checkout', '-b', '_hubkit']);
        $this->commitFileToRepository('config.php', $this->rootRepository, 'config.php in _hubkit on root');
        $this->runCliCommand(['git', 'checkout', 'master']);

        $this->cwd = $this->secondRepository = $this->createGitDirectory($this->getTempDir() . '/second');
        $this->addRemote('origin', $this->rootRepository);
        $this->runCliCommand(['git', 'pull', 'origin', 'master']);
        $this->commitFileToRepository('foo.txt', $this->secondRepository, 'foo.txt in master on second');
        $this->commitFileToRepository('foo2.txt', $this->secondRepository, 'foo2.txt in master on second');
        $this->commitFileToRepository('foo3.txt', $this->rootRepository, 'foo3.txt in master on root'); // File is added after pull and therefor shouldn't exist or second

        $this->cwd = $this->rootRepository;

        $this->filesystem ??= new Filesystem();
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
    public function it_return_whether_file_exists(): void
    {
        $reader = $this->getGitFileReader();

        self::assertFalse($reader->fileExists('master', 'config.php'));
        self::assertFalse($reader->fileExists('_hubkit', 'config2.php'));
        self::assertFalse($reader->fileExists('_main', 'config2.php')); // Branch doesn't exists, but shouldn't fail
        self::assertFalse($reader->fileExists('master', 'foo2.txt'));

        self::assertTrue($reader->fileExists('master', 'foo.txt'));
        self::assertTrue($reader->fileExists('master', 'foo3.txt'));
        self::assertTrue($reader->fileExists('_hubkit', 'config.php'));
    }

    /** @test */
    public function it_return_whether_remote_file_exists(): void
    {
        $reader = $this->getGitFileReader($this->secondRepository);

        self::assertFalse($reader->fileExists('master', 'config.php'));
        self::assertFalse($reader->fileExists('master', 'foo3.txt'));
        self::assertFalse($reader->fileExists('_hubkit', 'config.php')); // Branch doesn't exists, but shouldn't fail

        self::assertTrue($reader->fileExists('master', 'foo.txt'));
        self::assertTrue($reader->fileExists('master', 'foo2.txt'));

        // Remote
        self::assertTrue($reader->fileExistsAtRemote('origin', '_hubkit', 'config.php'));
        self::assertTrue($reader->fileExistsAtRemote('origin', 'master', 'foo.txt'));
        self::assertTrue($reader->fileExistsAtRemote('origin', 'master', 'foo3.txt'));
        self::assertFalse($reader->fileExistsAtRemote('origin', 'master', 'foo2.txt'));
    }

    private function getGitFileReader(string $repository = null): GitFileReader
    {
        $repository ??= $this->rootRepository;

        $style = $this->createStyle(output: $this->getCliOutput());
        $this->cliProcess->setCwd($repository);

        return new GitFileReader(
            new class($this->cliProcess, $style, $repository) extends GitBranch {
                public function __construct(CliProcess $process, StyleInterface $style, private readonly string $gitDir)
                {
                    parent::__construct($process, $style);
                }

                public function getGitDirectory(): string
                {
                    return $this->gitDir . '/.git';
                }

                public function getCwd(): string
                {
                    return $this->gitDir;
                }
            },
            new GitConfig($this->cliProcess, $style),
            $this->cliProcess,
            $this->gitTempRepository
        );
    }

    /**
     * @test
     */
    public function it_fails_to_get_file_when_not_existent(): void
    {
        $reader = $this->getGitFileReader($this->secondRepository);

        $this->expectExceptionObject(GitFileNotFound::atBranch('master', 'foo3.txt'));

        $reader->getFile('master', 'foo3.txt');
    }

    /**
     * @test
     */
    public function it_fails_to_get_remote_file_when_not_existent(): void
    {
        $reader = $this->getGitFileReader($this->secondRepository);

        $this->expectExceptionObject(GitFileNotFound::atRemote('origin', 'master', 'foo2.txt'));

        $reader->getFileAtRemote('origin', 'master', 'foo2.txt');
    }

    /** @test */
    public function it_gets_file_from_branch(): void
    {
        $reader1 = $this->getGitFileReader($this->rootRepository);

        $this->assertGitFileEquals($reader1, 'master', 'foo.txt', 'foo.txt in master on root');
        $this->assertGitFileEquals($reader1, 'master', 'foo3.txt', 'foo3.txt in master on root');
        $this->assertGitFileEquals($reader1, '_hubkit', 'config.php', 'config.php in _hubkit on root');

        $reader2 = $this->getGitFileReader($this->secondRepository);
        $this->assertGitFileEquals($reader2, 'master', 'foo2.txt', 'foo2.txt in master on second');
        $this->assertGitFileEquals($reader2, 'master', 'foo.txt', 'foo.txt in master on second');
        $this->assertRemoteGitFileEquals($reader2, 'origin', 'master', 'foo.txt', 'foo.txt in master on root');
        $this->assertRemoteGitFileEquals($reader2, 'origin', '_hubkit', 'config.php', 'config.php in _hubkit on root');

        // Change the files after asserting, the temp repository should be be up-to-date.
        $this->runCliCommand(['git', 'checkout', 'master'], $this->rootRepository);
        $this->commitFileToRepository('foo.txt', $this->rootRepository, 'foo.txt in master on root (2)');
        $this->commitFileToRepository('foo3.txt', $this->rootRepository, 'foo3.txt in master on root (2)');
        $this->runCliCommand(['git', 'checkout', '_hubkit'], $this->rootRepository);
        $this->commitFileToRepository('config.php', $this->rootRepository, 'config.php in _hubkit on root (2)');

        $this->runCliCommand(['git', 'checkout', 'master'], $this->secondRepository);
        $this->commitFileToRepository('foo.txt', $this->secondRepository, 'foo.txt in master on second (2)');
        $this->commitFileToRepository('foo2.txt', $this->secondRepository, 'foo2.txt in master on second (2)');
        // ...

        $this->cliProcess->setCwd($this->rootRepository);
        $this->assertGitFileEquals($reader1, 'master', 'foo.txt', 'foo.txt in master on root (2)');
        $this->assertGitFileEquals($reader1, 'master', 'foo3.txt', 'foo3.txt in master on root (2)');
        $this->assertGitFileEquals($reader1, '_hubkit', 'config.php', 'config.php in _hubkit on root (2)');

        $this->cliProcess->setCwd($this->secondRepository);
        $this->assertGitFileEquals($reader2, 'master', 'foo2.txt', 'foo2.txt in master on second (2)');
        $this->assertGitFileEquals($reader2, 'master', 'foo.txt', 'foo.txt in master on second (2)');
        $this->assertRemoteGitFileEquals($reader2, 'origin', 'master', 'foo.txt', 'foo.txt in master on root (2)');
        $this->assertRemoteGitFileEquals($reader2, 'origin', '_hubkit', 'config.php', 'config.php in _hubkit on root (2)');
    }

    private function assertGitFileEquals(GitFileReader $reader, string $branch, string $path, string $contents): void
    {
        $file = $reader->getFile($branch, $path);

        self::assertFileExists($file);
        self::assertStringEndsWith('/' . $path, $file);
        self::assertSame($contents, file_get_contents($file));
    }

    private function assertRemoteGitFileEquals(GitFileReader $reader, string $remote, string $branch, string $path, string $contents): void
    {
        $file = $reader->getFileAtRemote($remote, $branch, $path);

        self::assertFileExists($file);
        self::assertStringEndsWith('/' . $path, $file);
        self::assertSame($contents, file_get_contents($file));
    }
}
