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

namespace HubKit\Tests\Service;

use HubKit\Service\Git;
use HubKit\Service\Git\GitFileReader;
use HubKit\Service\ReleaseHooks;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Rollerworks\Component\Version\Version;

/**
 * @internal
 */
final class ReleaseHooksTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_does_nothing_when_there_are_no_hooks(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug(Argument::any(), Argument::any())->shouldNotBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/project-without-hooks'
        );

        $version = Version::fromString('1.0');

        self::assertEmpty($hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEmpty($hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /** @test */
    public function it_executes_pre_hook(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('Hook script {name}.php was found in the "_hubkit" branch.', ['name' => 'pre-release'])->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(pre: __DIR__ . '/Fixtures/project-pre-release/.hubkit/pre-release.php'),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/empty-project'
        );

        $version = Version::fromString('1.0');

        self::assertEquals('executed-pre', $hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEmpty($hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /** @test */
    public function it_executes_post_hook(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('Hook script {name}.php was found in the "_hubkit" branch.', ['name' => 'post-release'])->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(post: __DIR__ . '/Fixtures/project-post-release/.hubkit/post-release.php'),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/empty-project'
        );

        $version = Version::fromString('1.0');

        self::assertEmpty($hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEquals('executed-post', $hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /** @test */
    public function it_executes_pre_post_hook(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('Hook script {name}.php was found in the "_hubkit" branch.', ['name' => 'pre-release'])->shouldBeCalled();
        $loggerProphecy->debug('Hook script {name}.php was found in the "_hubkit" branch.', ['name' => 'post-release'])->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(
                pre: __DIR__ . '/Fixtures/project-pre-post-release/.hubkit/pre-release.php',
                post: __DIR__ . '/Fixtures/project-pre-post-release/.hubkit/post-release.php'
            ),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/project-pre-post-release'
        );

        $version = Version::fromString('1.0');

        self::assertEquals('executed-pre', $hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEquals('executed-post', $hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_executes_pre_hook_legacy(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->warning('Hook script {name}.php was found at "{script}". Move this file to the "_hubkit" configuration branch instead.', ['name' => 'pre-release', 'script' => __DIR__ . '/Fixtures/project-pre-release/.hubkit'])->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/project-pre-release'
        );

        $version = Version::fromString('1.0');

        self::assertEquals('executed-pre', $hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEmpty($hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_executes_post_hook_legacy(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->warning('Hook script {name}.php was found at "{script}". Move this file to the "_hubkit" configuration branch instead.', ['name' => 'post-release', 'script' => __DIR__ . '/Fixtures/project-post-release/.hubkit'])->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/project-post-release'
        );

        $version = Version::fromString('1.0');

        self::assertEmpty($hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEquals('executed-post', $hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_executes_pre_post_hook_legacy(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->warning('Hook script {name}.php was found at "{script}". Move this file to the "_hubkit" configuration branch instead.', ['name' => 'pre-release', 'script' => __DIR__ . '/Fixtures/project-pre-post-release/.hubkit'])->shouldBeCalled();
        $loggerProphecy->warning('Hook script {name}.php was found at "{script}". Move this file to the "_hubkit" configuration branch instead.', ['name' => 'post-release', 'script' => __DIR__ . '/Fixtures/project-pre-post-release/.hubkit'])->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks(
            $this->getGitFileReader(),
            $logger,
            $this->createMock(ContainerInterface::class),
            $git,
            __DIR__ . '/Fixtures/project-pre-post-release'
        );

        $version = Version::fromString('1.0');

        self::assertEquals('executed-pre', $hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEquals('executed-post', $hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /**
     * @param non-empty-string|null $pre
     * @param non-empty-string|null $post
     */
    private function getGitFileReader(string $pre = null, string $post = null): GitFileReader
    {
        $gitFileReaderProphecy = $this->prophesize(GitFileReader::class);

        $gitFileReaderProphecy->fileExists('_hubkit', 'pre-release.php')->willReturn($pre !== null);
        $gitFileReaderProphecy->fileExists('_hubkit', 'post-release.php')->willReturn($post !== null);

        if ($pre !== null) {
            $gitFileReaderProphecy->getFile('_hubkit', 'pre-release.php')->willReturn($pre);
        }

        if ($post !== null) {
            $gitFileReaderProphecy->getFile('_hubkit', 'post-release.php')->willReturn($post);
        }

        return $gitFileReaderProphecy->reveal();
    }
}
