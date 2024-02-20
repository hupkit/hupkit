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

namespace HubKit\Tests\Handler;

use HubKit\Cli\Handler\InitConfigHandler;
use HubKit\Service\Git;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem as SfFilesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class InitConfigHandlerTest extends ConfigHandlerTestCase
{
    /** @test */
    public function it_fails_with_already_existing_config_branch(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(true);
        $this->git->getRemoteDiffStatus('upstream', '_hubkit')->willReturn(Git::STATUS_UP_TO_DATE);

        $this->expectExceptionObject(new \RuntimeException('The "_hubkit" branch already exists. Run `edit-config` instead.'));

        $this->executeHandler();
    }

    /** @test */
    public function it_fails_with_already_existing_config_branch_outdated(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(true);
        $this->git->getRemoteDiffStatus('upstream', '_hubkit')->willReturn(Git::STATUS_DIVERGED);

        $this->expectExceptionObject(new \RuntimeException('The remote "_hubkit" branch and local branch have diverged. Run the "sync-config" command first.'));

        $this->executeHandler();
    }

    /** @test */
    public function it_fails_with_already_existing_remove_config_branch(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(false);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(true);

        $this->expectExceptionObject(new \RuntimeException(
            'The "_hubkit" branch exists remote, but the branch was not found locally.' . \PHP_EOL .
            'Run the "sync-config" command to pull-in the remote branch.' . \PHP_EOL
        ));

        $this->executeHandler();
    }

    /** @test */
    public function it_it_fails_when_config_file_exists_as_ignored(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(false);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(false);

        $this->process->mustRun(['git', 'checkout', '--orphan', '_hubkit'])->shouldBeCalled();
        $this->process->mustRun(['git', 'rm', '-rf', '.'])->shouldBeCalled();
        $this->filesystem->exists('./config.php')->willReturn(true);

        $this->expectExceptionObject(new \RuntimeException('The config.php file already exists, cannot safely continue, either (temporarily) move or rename this file.'));

        $this->executeHandler();
    }

    /** @test */
    public function it_it_generates_config_branch_with_repository_config(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(false);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(false);

        $this->process->mustRun(['git', 'checkout', '--orphan', '_hubkit'])->shouldBeCalled();
        $this->process->mustRun(['git', 'rm', '-rf', '.'])->shouldBeCalled();
        $this->filesystem->exists('./config.php')->willReturn(false);
        $this->filesystem->getCwd()->willReturn($cwd = ':/home/Jessie/project-name');

        $this->process->mustRun(Process::fromShellCommandline('git show master:./.gitignore > .gitignore'))->shouldBeCalled();
        $this->process->mustRun(['git', 'add', '.gitignore'])->shouldBeCalled();

        $this->tempRepository->getLocal($cwd, 'master')->willReturn($tempDirectory = ':/tmp/very-random-location-project');
        $this->filesystem->exists($tempDirectory . '/.hubkit')->willReturn(false);

        $dumpedFile = '';
        $this->filesystem->dumpFile('./config.php', Argument::any())->will(
            static function ($args) use (&$dumpedFile): void {
                $dumpedFile = $args[1];
            }
        )->shouldBeCalled();

        $this->process->mustRun(['git', 'add', 'config.php'])->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches([
            'Generating empty "_hubkit" branch.',
            'The "_hubkit" configuration branch was created, edit the config.php file with your favorite editor.',
            sprintf('After you are done run `git checkout %s`.', 'master'),
        ]);

        $this->assertOutputNotMatches('The .hubkit directory was found and it\'s files copied to the "_hubkit" configuration branch.');

        self::assertConfigFileEquals([
            'schema_version' => 2,
            'host' => 'github.com',
            'repository' => 'park-manager/hubkit',
            'branches' => [],
        ], $dumpedFile);
    }

    /** @test */
    public function it_it_generates_config_branch_with_repository_config_from_global(): void
    {
        $this->github->getRepository()->willReturn('park-manager');

        $this->git->branchExists('_hubkit')->willReturn(false);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(false);

        $this->process->mustRun(['git', 'checkout', '--orphan', '_hubkit'])->shouldBeCalled();
        $this->process->mustRun(['git', 'rm', '-rf', '.'])->shouldBeCalled();
        $this->filesystem->exists('./config.php')->willReturn(false);
        $this->filesystem->getCwd()->willReturn($cwd = ':/home/Jessie/project-name');

        $this->process->mustRun(Process::fromShellCommandline('git show master:./.gitignore > .gitignore'))->shouldBeCalled();
        $this->process->mustRun(['git', 'add', '.gitignore'])->shouldBeCalled();

        $this->tempRepository->getLocal($cwd, 'master')->willReturn($tempDirectory = ':/tmp/very-random-location-project');
        $this->filesystem->exists($tempDirectory . '/.hubkit')->willReturn(false);

        $dumpedFile = '';
        $this->filesystem->dumpFile('./config.php', Argument::any())->will(
            static function ($args) use (&$dumpedFile): void {
                $dumpedFile = $args[1];
            }
        )->shouldBeCalled();

        $this->process->mustRun(['git', 'add', 'config.php'])->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches([
            'Generating empty "_hubkit" branch.',
            'The "_hubkit" configuration branch was created, edit the config.php file with your favorite editor.',
            sprintf('After you are done run `git checkout %s`.', 'master'),
        ]);

        $this->assertOutputNotMatches('The .hubkit directory was found and it\'s files copied to the "_hubkit" configuration branch.');

        self::assertConfigFileEquals([
            'schema_version' => 2,
            'host' => 'github.com',
            'repository' => 'park-manager/park-manager',
            'sync-tags' => true,
            'branches' => [
                ':default' => [
                    'split' => [
                        'src/Component/Core' => [
                            'url' => 'git@github.com:park-manager/core.git',
                        ],
                        'src/Component/Model' => [
                            'url' => 'git@github.com:park-manager/model.git',
                        ],
                        'doc' => [
                            'url' => 'git@github.com:park-manager/doc.git',
                            'sync-tags' => false,
                        ],
                    ],
                ],
            ],
        ], $dumpedFile);
    }

    /** @test */
    public function it_it_generates_config_branch_with_repository_config_and_mirrors_files(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(false);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(false);

        $this->process->mustRun(['git', 'checkout', '--orphan', '_hubkit'])->shouldBeCalled();
        $this->process->mustRun(['git', 'rm', '-rf', '.'])->shouldBeCalled();
        $this->filesystem->exists('./config.php')->willReturn(false);
        $this->filesystem->getCwd()->willReturn($cwd = ':/home/Jessie/project-name');

        $this->process->mustRun(Process::fromShellCommandline('git show master:./.gitignore > .gitignore'))->shouldBeCalled();
        $this->process->mustRun(['git', 'add', '.gitignore'])->shouldBeCalled();

        $this->tempRepository->getLocal($cwd, 'master')->willReturn($tempDirectory = ':/tmp/very-random-location-project');
        $this->filesystem->exists($tempDirectory . '/.hubkit')->willReturn(true);

        $filesystem = $this->prophesize(SfFilesystem::class);
        $filesystem->mirror($tempDirectory . '/.hubkit', $cwd, null, ['copy_on_windows' => true])->shouldBeCalled();
        $this->filesystem->getFilesystem()->willReturn($filesystem->reveal());

        $dumpedFile = '';
        $this->filesystem->dumpFile('./config.php', Argument::any())->will(
            static function ($args) use (&$dumpedFile): void {
                $dumpedFile = $args[1];
            }
        )->shouldBeCalled();

        $this->process->mustRun(['git', 'add', 'config.php'])->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches([
            'Generating empty "_hubkit" branch.',
            'The .hubkit directory was found and it\'s files copied to the "_hubkit" configuration branch.',
            'The "_hubkit" configuration branch was created, edit the config.php file with your favorite editor.',
            sprintf('After you are done run `git checkout %s`.', 'master'),
        ]);

        self::assertConfigFileEquals([
            'schema_version' => 2,
            'host' => 'github.com',
            'repository' => 'park-manager/hubkit',
            'branches' => [],
        ], $dumpedFile);
    }

    private static function assertConfigFileEquals(array $expected, string $actual): void
    {
        self::assertStringStartsWith('<?php', $actual);
        $fileConfig = eval(mb_substr($actual, 5));

        self::assertEquals($expected, $fileConfig);
    }

    private function executeHandler(): void
    {
        $style = $this->createStyle();
        $handler = new InitConfigHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
            $this->filesystem->reveal(),
            $this->tempRepository->reveal(),
            $this->process->reveal(),
        );

        $handler->handle($this->getArgs(), $this->io);
    }
}
