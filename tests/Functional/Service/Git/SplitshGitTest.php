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
use HubKit\Service\Git\GitBase;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Service\SplitshGit;
use HubKit\StringUtil;
use HubKit\Tests\Functional\GitTesterTrait;
use HubKit\Tests\Functional\TestCliProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class SplitshGitTest extends TestCase
{
    use GitTesterTrait;

    private ?string $origCwd = null;
    private string $rootRepository;

    private GitBase $git;
    private Filesystem $filesystem;
    private TestCliProcess $cliProcess;
    private GitTempRepository $gitTempRepository;
    private SplitshGit $splitshGit;

    /** @before */
    public function setUpLocalRepository(): void
    {
        $this->origCwd = getcwd();
        $executable = (new ExecutableFinder())->find('splitsh-lite');

        if ($executable === null) {
            self::markTestSkipped('Could not find "splitsh-lite" in your "$PATH" environment.');
        }

        $tempDir = $this->getTempDir();
        $this->cwd = $this->rootRepository = $this->createGitDirectory($tempDir . '/root');
        chdir($this->cwd);

        $this->commitFileToRepository('lib/core/index.php', $this->rootRepository);
        $this->commitFileToRepository('lib/core/main.php', $this->rootRepository);
        $this->commitFileToRepository('lib/validator/constraints.php', $this->rootRepository);
        $this->commitFileToRepository('lib/validator/main.php', $this->rootRepository);
        $this->commitFileToRepository('docs/main.rst', $this->rootRepository);
        $this->commitFileToRepository('docs/core.rst', $this->rootRepository);

        $this->createGitDirectory($tempDir . '/split-core');
        $this->createGitDirectory($tempDir . '/split-docs');
        $this->createGitDirectory($tempDir . '/split-validator');
        $this->createGitDirectory($tempDir . '/split-doctrine');

        $this->filesystem = new Filesystem($tempDir);
        $this->cliProcess = $this->getProcessService($this->rootRepository);
        $this->cliProcess->ignoreCwdChangeWhen(static fn (string $val): bool => true);

        $this->gitTempRepository = new GitTempRepository($this->cliProcess, $this->filesystem);
        $this->git = new GitBase($this->cliProcess, $this->cwd);

        $this->splitshGit = new SplitshGit(
            $this->git,
            $this->cliProcess,
            new NullLogger(), // XXX Should use BufferingLogger
            $this->gitTempRepository,
            $executable
        );
    }

    /** @after */
    public function restoreCwd(): void
    {
        chdir($this->origCwd);

        if (isset($this->filesystem)) {
            $this->filesystem->clearTempFiles();
        }
    }

    /** @test */
    public function it_splits_at_prefix(): void
    {
        $tempDir = $this->getTempDir();

        self::assertNotNull($this->splitshGit->splitTo('master', 'lib/core', 'file://' . $tempDir . '/split-core'));
        self::assertNotNull($this->splitshGit->splitTo('master', 'lib/validator', 'file://' . $tempDir . '/split-validator'));
        self::assertNotNull($this->splitshGit->splitTo('master', 'docs', 'file://' . $tempDir . '/split-docs'));
        self::assertNull($this->splitshGit->splitTo('master', 'doctrine', 'file://' . $tempDir . '/split-doctrine'));

        // Refs were updated but not HEAD not
        $this->runCliCommand(['git', 'reset', '--hard'], $tempDir . '/split-core');
        $this->runCliCommand(['git', 'reset', '--hard'], $tempDir . '/split-validator');
        $this->runCliCommand(['git', 'reset', '--hard'], $tempDir . '/split-docs');

        self::assertFileExists($tempDir . '/split-core/index.php');
        self::assertFileExists($tempDir . '/split-core/main.php');
        self::assertFileDoesNotExist($tempDir . '/split-core/main.rst');

        self::assertFileExists($tempDir . '/split-validator/constraints.php');
        self::assertFileExists($tempDir . '/split-validator/main.php');
        self::assertFileDoesNotExist($tempDir . '/split-validator/index.php');

        self::assertFileExists($tempDir . '/split-docs/main.rst');
        self::assertFileExists($tempDir . '/split-docs/core.rst');
    }

    /** @test */
    public function it_syncs_tags(): void
    {
        $tempDir = $this->getTempDir();

        $this->assertRepositoryTagsEquals([], $tempDir . '/split-core');
        $this->assertRepositoryTagsEquals([], $tempDir . '/split-validator');
        $this->assertRepositoryTagsEquals([], $tempDir . '/split-docs');

        /** @var array<int, array{0: string, 1: string, 2: string}> $splits */
        $splits = [];
        $splits[] = $this->splitshGit->splitTo('master', 'lib/core', 'file://' . $tempDir . '/split-core');
        $splits[] = $this->splitshGit->splitTo('master', 'lib/validator', 'file://' . $tempDir . '/split-validator');
        $splits[] = $this->splitshGit->splitTo('master', 'docs', 'file://' . $tempDir . '/split-docs');

        // Refs were updated but not HEAD not
        $this->runCliCommand(['git', 'reset', '--hard'], $tempDir . '/split-core');
        $this->runCliCommand(['git', 'reset', '--hard'], $tempDir . '/split-validator');
        $this->runCliCommand(['git', 'reset', '--hard'], $tempDir . '/split-docs');

        $this->splitshGit->syncTags('1.0.0', 'master', $splits);

        $this->assertRepositoryTagsEquals(['v1.0.0'], $tempDir . '/split-core');
        $this->assertRepositoryTagsEquals(['v1.0.0'], $tempDir . '/split-validator');
        $this->assertRepositoryTagsEquals(['v1.0.0'], $tempDir . '/split-docs');
    }

    /**
     * @param string[] $expected
     */
    private function assertRepositoryTagsEquals(array $expected, string $repository): void
    {
        $tags = StringUtil::splitLines($this->cliProcess->mustRun(new Process(['git', 'tag', '--list'], $repository))->getOutput());

        self::assertEquals($expected, $tags);
    }
}
