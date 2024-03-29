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

namespace HubKit\Tests\Service;

use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Service\SplitshGit;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class SplitshGitTest extends TestCase
{
    use ProphecyTrait;

    private const SPLITSH_EXECUTABLE = '/usr/locale/bin/splitsh-lite';

    /** @test */
    public function it_splits_source_into_target(): void
    {
        $pwd = getcwd();

        try {
            chdir(__DIR__ . '/Fixtures');

            $gitTempProphecy = $this->prophesize(GitTempRepository::class);
            $gitTempProphecy->getRemote('git@github.com:park-manager/core.git', 'master')->willReturn('/tmp/hubkit/stor/_core');
            $gitTemp = $gitTempProphecy->reveal();

            $gitProphecy = $this->prophesize(Git::class);
            $gitProphecy->pushToRemote('file:///tmp/hubkit/stor/_core', '2c00338aef823d0c0916fc1b59ef49d0bb76f02f:refs/heads/master')->shouldBeCalled();
            $git = $gitProphecy->reveal();

            $processCliProphecy = $this->prophesize(CliProcess::class);
            $processCliProphecy->mustRun([self::SPLITSH_EXECUTABLE, '--prefix', 'src/Bundle/CoreBundle'])->willReturn($this->getGitSplitShResult('2c00338aef823d0c0916fc1b59ef49d0bb76f02f'));
            $processCliProphecy->mustRun(new Process(['git', 'push', 'origin', 'master:refs/heads/master'], '/tmp/hubkit/stor/_core'), 'If the destination does not exist run the `split-create` command.');
            $processCliProphecy->mustRun(new Process(['git', 'reset', '--hard'], '/tmp/hubkit/stor/_core'));
            $cliProcess = $processCliProphecy->reveal();

            $service = new SplitshGit($git, $cliProcess, $this->createMock(LoggerInterface::class), $gitTemp, self::SPLITSH_EXECUTABLE);

            self::assertEquals(
                ['2c00338aef823d0c0916fc1b59ef49d0bb76f02f', 'git@github.com:park-manager/core.git', '/tmp/hubkit/stor/_core'],
                $service->splitTo('master', 'src/Bundle/CoreBundle', 'git@github.com:park-manager/core.git')
            );
        } finally {
            chdir($pwd);
        }
    }

    /** @test */
    public function it_syncs_tag_into_target(): void
    {
        $processCliProphecy = $this->prophesize(CliProcess::class);
        $processCliProphecy->run(new Process(['git', 'tag', 'v1.0.0', '2c00338aef823d0c0916fc1b59ef49d0bb76f02f', '-s', '-m', 'Release 1.0.0'], '/tmp/hubkit/stor/_core'))->shouldBeCalledTimes(1);
        $processCliProphecy->run(new Process(['git', 'push', 'origin', 'v1.0.0'], '/tmp/hubkit/stor/_core'));
        $cliProcess = $processCliProphecy->reveal();

        $gitTempProphecy = $this->prophesize(GitTempRepository::class);
        $gitTempProphecy->getRemote('git@github.com:park-manager/core.git', '1.0')->willReturn('/tmp/hubkit/stor/_core');
        $gitTemp = $gitTempProphecy->reveal();

        $service = new SplitshGit($this->createMock(Git::class), $cliProcess, $this->createMock(LoggerInterface::class), $gitTemp, self::SPLITSH_EXECUTABLE);

        $service->syncTag(
            '1.0.0',
            'git@github.com:park-manager/core.git',
            '1.0',
            '2c00338aef823d0c0916fc1b59ef49d0bb76f02f',
        );
    }

    /** @test */
    public function it_syncs_tag_into_targets(): void
    {
        $processCliProphecy = $this->prophesize(CliProcess::class);

        $processCliProphecy->run(new Process(['git', 'tag', 'v1.0.0', '2c00338aef823d0c0916fc1b59ef49d0bb76f02f', '-s', '-m', 'Release 1.0.0'], '/tmp/hubkit/stor/_core'))->shouldBeCalledTimes(1);
        $processCliProphecy->run(new Process(['git', 'push', 'origin', 'v1.0.0'], '/tmp/hubkit/stor/_core'));

        $processCliProphecy->run(new Process(['git', 'tag', 'v1.0.0', '3eed8083737422fe9ac2da9f4348423089fceb7f', '-s', '-m', 'Release 1.0.0'], '/tmp/hubkit/stor/_user'))->shouldBeCalledTimes(1);
        $processCliProphecy->run(new Process(['git', 'push', 'origin', 'v1.0.0'], '/tmp/hubkit/stor/_user'));

        $cliProcess = $processCliProphecy->reveal();

        $gitTempProphecy = $this->prophesize(GitTempRepository::class);
        $gitTempProphecy->getRemote('git@github.com:park-manager/core.git', '1.0')->willReturn('/tmp/hubkit/stor/_core');
        $gitTempProphecy->getRemote('git@github.com:park-manager/user.git', '1.0')->willReturn('/tmp/hubkit/stor/_user');
        $gitTemp = $gitTempProphecy->reveal();

        $service = new SplitshGit($this->createMock(Git::class), $cliProcess, $this->createMock(LoggerInterface::class), $gitTemp, self::SPLITSH_EXECUTABLE);

        $service->syncTags(
            '1.0.0',
            '1.0',
            [
                '/tmp/hubkit/stor/_core' => ['2c00338aef823d0c0916fc1b59ef49d0bb76f02f', 'git@github.com:park-manager/core.git', 5],
                '/tmp/hubkit/stor/_user' => ['3eed8083737422fe9ac2da9f4348423089fceb7f', 'git@github.com:park-manager/user.git', 3],
            ]
        );
    }

    /** @test */
    public function it_checks_precondition(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(true);
        $git = $gitProphecy->reveal();

        $service = new SplitshGit(
            $git,
            $this->createMock(CliProcess::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(GitTempRepository::class),
            self::SPLITSH_EXECUTABLE
        );
        $service->checkPrecondition();
    }

    /** @test */
    public function it_requires_root_directory_as_precondition(): void
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(false);
        $git = $gitProphecy->reveal();

        $service = new SplitshGit(
            $git,
            $this->createMock(CliProcess::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(GitTempRepository::class),
            self::SPLITSH_EXECUTABLE
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to perform split operation. Requires Git root directory of the repository.');

        $service->checkPrecondition();
    }

    private function getGitSplitShResult(string $hash): Process
    {
        $processProphecy = $this->prophesize(Process::class);
        $processProphecy->getOutput()->willReturn($hash);
        $processProphecy->getErrorOutput()->willReturn("\n5 commits created, 0 commits traversed, in 7ms");

        return $processProphecy->reveal();
    }
}
