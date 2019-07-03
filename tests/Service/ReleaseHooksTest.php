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
use HubKit\Service\ReleaseHooks;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Rollerworks\Component\Version\Version;

final class ReleaseHooksTest extends TestCase
{
    /** @test */
    public function it_does_nothing_when_there_are_hooks()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('File {script} was not found. pre-release script will not be executed.', Argument::any())->shouldBeCalled();
        $loggerProphecy->debug('File {script} was not found. post-release script will not be executed.', Argument::any())->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks($this->createMock(ContainerInterface::class), $git, $logger, __DIR__.'/Fixtures/project-without-hooks');

        $version = Version::fromString('1.0');

        self::assertEmpty($hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEmpty($hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /** @test */
    public function it_executes_pre_hook()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('File {script} was not found. pre-release script will not be executed.', Argument::any())->shouldNotBeCalled();
        $loggerProphecy->debug('File {script} was not found. post-release script will not be executed.', Argument::any())->shouldBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks($this->createMock(ContainerInterface::class), $git, $logger, __DIR__.'/Fixtures/project-pre-release');

        $version = Version::fromString('1.0');

        self::assertEquals('executed-pre', $hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEmpty($hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /** @test */
    public function it_executes_post_hook()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('File {script} was not found. pre-release script will not be executed.', Argument::any())->shouldBeCalled();
        $loggerProphecy->debug('File {script} was not found. post-release script will not be executed.', Argument::any())->shouldNotBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks($this->createMock(ContainerInterface::class), $git, $logger, __DIR__.'/Fixtures/project-post-release');

        $version = Version::fromString('1.0');

        self::assertEmpty($hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEquals('executed-post', $hooks->postRelease($version, 'master', null, 'Something changed.'));
    }

    /** @test */
    public function it_executes_pre_post_hook()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isWorkingTreeReady()->willReturn(true);
        $git = $gitProphecy->reveal();

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $loggerProphecy->debug('File {script} was not found. pre-release script will not be executed.', Argument::any())->shouldNotBeCalled();
        $loggerProphecy->debug('File {script} was not found. post-release script will not be executed.', Argument::any())->shouldNotBeCalled();
        $logger = $loggerProphecy->reveal();

        $hooks = new ReleaseHooks($this->createMock(ContainerInterface::class), $git, $logger, __DIR__.'/Fixtures/project-pre-post-release');

        $version = Version::fromString('1.0');

        self::assertEquals('executed-pre', $hooks->preRelease($version, 'master', null, 'Something changed.'));
        self::assertEquals('executed-post', $hooks->postRelease($version, 'master', null, 'Something changed.'));
    }
}
