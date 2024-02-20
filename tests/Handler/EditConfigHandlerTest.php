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

use HubKit\Cli\Handler\EditConfigHandler;
use HubKit\Config;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Service\GitHub;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Gitignore;

/**
 * @internal
 */
final class EditConfigHandlerTest extends ConfigHandlerTestCase
{
    private ObjectProphecy $finder;

    /**
     * @before
     */
    public function setupFinder(): void
    {
        $this->finder = $this->prophesize(Finder::class);
    }

    /** @test */
    public function it_does_nothing_when_already_checked_out(): void
    {
        $this->git->getActiveBranchName()->willReturn('_hubkit');
        $this->git->getRemoteDiffStatus('upstream', '_hubkit')->willReturn(Git::STATUS_UP_TO_DATE);

        $this->executeHandler();

        $this->assertOutputMatches('Configuration branch already checked out.');
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
    public function it_fails_with_non_existing_config_branch(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(false);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(false);

        $this->expectExceptionObject(new \RuntimeException('The "_hubkit" branch does not exist yet. Run `init-config` first.'));

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

    /**
     * @test
     *
     * @dataProvider provide_it_fails_overwritten_ignored_files
     *
     * @param array<string, string> $gitIgnoreFiles ['fileName' => 'contents']
     */
    public function it_fails_overwritten_ignored_files(array $gitIgnoreFiles, array $foundFiles = [], array $existingFiles = []): void
    {
        $this->git->branchExists('_hubkit')->willReturn(true);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(true);
        $this->git->getRemoteDiffStatus('upstream', '_hubkit')->willReturn(Git::STATUS_UP_TO_DATE);

        $this->filesystem->getCwd()->willReturn($cwd = ':/home/Jessie/project-name');
        $this->tempRepository->getLocal($cwd, '_hubkit')->willReturn($tempDirectory = ':/tmp/very-random-location-project');

        $this->filesystem->exists(Argument::any())->willReturn(false);

        foreach ($gitIgnoreFiles as $excludeFile => $contents) {
            $this->filesystem->exists($excludeFile)->willReturn(true);
            $this->filesystem->getFileContents($excludeFile)->willReturn($contents);
        }

        $this->finder->name(array_map(Gitignore::toRegex(...), array_values($gitIgnoreFiles)))->will(fn () => $this)->shouldBeCalled();
        $this->finder->in($tempDirectory)->will(fn () => $this)->shouldBeCalled();
        $this->finder->getIterator()->willReturn(
            new \ArrayIterator(
                array_map(
                    function (string $file) use ($tempDirectory): \SplFileInfo {
                        $m = $this->createMock(\SplFileInfo::class);
                        $m->method('getPathname')->willReturn($tempDirectory . '/' . $file);

                        return $m;
                    },
                    $foundFiles
                )
            )
        );

        foreach ($existingFiles as $filePath) {
            $this->filesystem->exists('./' . $filePath)->willReturn(true);
        }
        // End set-up

        $this->expectExceptionObject(new \RuntimeException(
            sprintf(
                "One or more git-ignored files where found in the \"_hubkit\" branch, these would be overwritten when checking out.\n" .
                "\nTemporarily move or rename these files:\n\n  * %s",
                implode("\n  * ", $existingFiles)
            )
        ));

        $this->executeHandler();
    }

    public static function provide_it_fails_overwritten_ignored_files(): iterable
    {
        yield [
            [
                './.gitignore' => <<<'GITIGNORE'
                    /vendor/
                    #/composer.lock
                    .php_cs.cache
                    /config.php
                    .phpunit.result.cache
                    .php-cs-fixer.cache
                    GITIGNORE,
            ],
            [
                'config.php',
                'vendor',
                'plugins.php',
            ],
            [
                'config.php',
                'vendor',
            ],
        ];

        yield [
            [
                './.gitignore' => <<<'GITIGNORE'
                    #/composer.lock
                    .php_cs.cache
                    /config.php
                    GITIGNORE,

                './.git/info/exclude' => <<<'GITIGNORE'
                    /vendor/
                    GITIGNORE,
            ],
            [
                'config.php',
                'vendor',
                'plugins.php',
            ],
            [
                'config.php',
                'vendor',
            ],
        ];
    }

    /** @test */
    public function it_checks_out_config_branch(): void
    {
        $this->git->branchExists('_hubkit')->willReturn(true);
        $this->git->remoteBranchExists('upstream', '_hubkit')->willReturn(true);
        $this->git->getRemoteDiffStatus('upstream', '_hubkit')->willReturn(Git::STATUS_UP_TO_DATE);

        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->checkout('_hubkit')->shouldBeCalled();

        // No existing files detected
        $this->filesystem->getCwd()->willReturn($cwd = ':/home/Jessie/project-name');
        $this->tempRepository->getLocal($cwd, '_hubkit')->willReturn($tempDirectory = ':/tmp/very-random-location-project');
        $this->filesystem->exists('./.gitignore')->willReturn(false);
        $this->filesystem->exists('./.git/info/exclude')->willReturn(false);
        $this->finder->name([])->will(fn () => $this)->shouldBeCalled();
        $this->finder->in($tempDirectory)->will(fn () => $this)->shouldBeCalled();
        $this->finder->getIterator()->willReturn(new \ArrayIterator([]));

        $this->executeHandler();

        $this->assertOutputMatches([
            'The "_hubkit" configuration branch was checked out.',
            'Make sure to add and commit once you are done.',
            sprintf('After you are done run `git checkout %s`.', 'master'),
            'And run the `sync-config` command to push the configuration to the upstream repository.',
        ]);
    }

    private function executeHandler(): void
    {
        $style = $this->createStyle();
        $handler = new class($style, $this->git->reveal(), $this->github->reveal(), $this->config, $this->filesystem->reveal(), $this->tempRepository->reveal(), $this->finder->reveal()) extends EditConfigHandler {
            public function __construct(SymfonyStyle $style, Git $git, GitHub $github, Config $config, Filesystem $filesystem, GitTempRepository $tempRepository, private Finder $finder)
            {
                parent::__construct($style, $git, $github, $config, $filesystem, $tempRepository);
            }

            protected function getFinder(array $gitIgnores, string $configRepository): Finder
            {
                $this->finder->name($gitIgnores);
                $this->finder->in($configRepository);

                return $this->finder;
            }
        };

        $handler->handle($this->getArgs(), $this->io);
    }
}
