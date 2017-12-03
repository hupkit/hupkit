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

use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\SplitshGit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem as SfFilesystem;
use Symfony\Component\Process\Process;

class SplitshGitTest extends TestCase
{
    private const SPLITSH_EXECUTABLE = '/usr/locale/bin/splitsh-lite';

    /** @test */
    public function it_splits_source_into_target()
    {
        $pwd = getcwd();

        try {
            chdir(__DIR__.'/Fixtures');

            $gitProphecy = $this->prophesize(Git::class);
            $gitProphecy->isGitDir()->willReturn(true);
            $gitProphecy->ensureRemoteExists('_core', 'git@github.com:park-manager/core.git')->shouldBeCalled();
            $gitProphecy->pushToRemote('_core', '2c00338aef823d0c0916fc1b59ef49d0bb76f02f:master')->shouldBeCalled();
            $git = $gitProphecy->reveal();

            $processProphecy = $this->prophesize(Process::class);
            $processProphecy->getOutput()->willReturn('2c00338aef823d0c0916fc1b59ef49d0bb76f02f');
            $processProphecy->getErrorOutput()->willReturn("\n5 commits created, 0 commits traversed, in 7ms");

            $processCliProphecy = $this->prophesize(CliProcess::class);
            $processCliProphecy->mustRun([self::SPLITSH_EXECUTABLE, '--prefix', 'src/Bundle/CoreBundle'])->willReturn(
                $processProphecy->reveal()
            );
            $cliProcess = $processCliProphecy->reveal();

            $filesystemProphecy = $this->prophesize(Filesystem::class);
            $filesystem = $filesystemProphecy->reveal();

            $service = new SplitshGit($git, $cliProcess, $filesystem, self::SPLITSH_EXECUTABLE);

            self::assertEquals(
                ['_core' => ['2c00338aef823d0c0916fc1b59ef49d0bb76f02f', 'git@github.com:park-manager/core.git', 5]],
                $service->splitTo('master', 'src/Bundle/CoreBundle', 'git@github.com:park-manager/core.git')
            );
        } finally {
            chdir($pwd);
        }
    }

    /** @test */
    public function it_syncs_tag_into_targets()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->getGitConfig('remote._core.url')->willReturn('git@github.com:park-manager/core.git');
        $gitProphecy->getGitConfig('remote._user.url')->willReturn('git@github.com:park-manager/user.git');
        $gitProphecy->clone('git@github.com:park-manager/core.git', 'origin', 200)->shouldBeCalledTimes(1);
        $gitProphecy->clone('git@github.com:park-manager/user.git', 'origin', 200)->shouldBeCalledTimes(1);
        $gitProphecy->checkout('1.0')->shouldBeCalledTimes(2);

        $git = $gitProphecy->reveal();

        $processCliProphecy = $this->prophesize(CliProcess::class);
        $processCliProphecy->mustRun(['git', 'tag', 'v1.0.0', '2c00338aef823d0c0916fc1b59ef49d0bb76f02f', '-s', '-m', 'Release 1.0.0'])->shouldBeCalledTimes(1);
        $processCliProphecy->mustRun(['git', 'tag', 'v1.0.0', '3eed8083737422fe9ac2da9f4348423089fceb7f', '-s', '-m', 'Release 1.0.0'])->shouldBeCalledTimes(1);
        $processCliProphecy->run(['git', 'push', '--tags', 'origin'])->shouldBeCalledTimes(2);
        $cliProcess = $processCliProphecy->reveal();

        $sfFilesystemProphecy = $this->prophesize(SfFilesystem::class);
        $sfFilesystemProphecy->mkdir('/tmp/hubkit/split/_core')->shouldBeCalledTimes(1);
        $sfFilesystemProphecy->mkdir('/tmp/hubkit/split/_user')->shouldBeCalledTimes(1);

        $filesystemProphecy = $this->prophesize(Filesystem::class);
        $filesystemProphecy->tempDirectory('split')->willReturn('/tmp/hubkit/split');
        $filesystemProphecy->chdir('/tmp/hubkit/split/_core')->willReturn(true)->shouldBeCalledTimes(1);
        $filesystemProphecy->chdir('/tmp/hubkit/split/_user')->willReturn(true)->shouldBeCalledTimes(1);
        $filesystemProphecy->chdir(getcwd())->willReturn(true)->shouldBeCalledTimes(1);
        $filesystemProphecy->getFilesystem()->willReturn($sfFilesystemProphecy->reveal());
        $filesystem = $filesystemProphecy->reveal();

        $service = new SplitshGit($git, $cliProcess, $filesystem, self::SPLITSH_EXECUTABLE);

        $service->syncTags(
            '1.0.0',
            '1.0',
            [
                '_core' => ['2c00338aef823d0c0916fc1b59ef49d0bb76f02f', 'git@github.com:park-manager/core.git', 5],
                '_user' => ['3eed8083737422fe9ac2da9f4348423089fceb7f', 'git@github.com:park-manager/user.git', 3],
            ]
        );
    }

    /** @test */
    public function it_checks_precondition()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(true);
        $git = $gitProphecy->reveal();

        $service = new SplitshGit(
            $git,
            $this->createMock(CliProcess::class),
            $this->createMock(Filesystem::class),
            self::SPLITSH_EXECUTABLE
        );
        $service->checkPrecondition();
    }

    /** @test */
    public function it_requires_root_directory_as_precondition()
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(false);
        $git = $gitProphecy->reveal();

        $service = new SplitshGit(
            $git,
            $this->createMock(CliProcess::class),
            $this->createMock(Filesystem::class),
            self::SPLITSH_EXECUTABLE
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to perform split operation. Requires Git root directory of the repository.');

        $service->checkPrecondition();
    }
}
