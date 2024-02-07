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

use HubKit\Cli\Handler\SwitchBaseHandler;
use HubKit\Config;
use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Tests\Functional\GitTesterTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument as ProphecyArgument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Promise\PromiseInterface;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Filesystem as SfFilesystem;
use Symfony\Component\Process\Process;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Args\StringArgs;

/**
 * @internal
 */
final class SwitchBaseHandlerTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;
    use GitTesterTrait;

    private ObjectProphecy $git;
    private ObjectProphecy $github;
    private ObjectProphecy $process;
    private ObjectProphecy $sfFilesystem;
    private ObjectProphecy $filesystem;
    private Config $config;

    /** @before */
    public function setUpCommandHandler(): void
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->getActiveBranchName()->willReturn('master');

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');
        $this->github->getAuthUsername()->willReturn('Jonny');

        $this->process = $this->prophesize(CliProcess::class);

        $this->sfFilesystem = $this->prophesize(SfFilesystem::class);
        $this->filesystem = $this->prophesize(Filesystem::class);
        $this->filesystem->getFilesystem()->willReturn($this->sfFilesystem);

        $this->config = new Config([]);
        $this->config->setActiveRepository('github.com', 'park-manager/hubkit');

        TrackedPromise::$calls = [];
    }

    /** @test */
    public function it_requires_pr_is_open(): void
    {
        $this->github->getPullRequest(12)->willReturn([
            'state' => 'merged',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot switch base of closed/merged pull-request.');

        $this->executeHandler($this->getArgs(12, 'main'));
    }

    /** @test */
    public function it_does_not_switch_to_same_base(): void
    {
        $this->github->getPullRequest(12)->willReturn([
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ]
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot switch base, current base is already "main".');

        $this->executeHandler($this->getArgs(12, 'main'));
    }

    /** @test */
    public function it_does_not_switch_to_non_existent_base(): void
    {
        $this->git->remoteBranchExists('upstream', '2.0')->willReturn(false);
        $this->github->getPullRequest(12)->willReturn([
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ]
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot switch base, base branch "2.0" does not exists.');

        $this->executeHandler($this->getArgs(12, '2.0'));
    }

    /** @test */
    public function it_does_not_switch_when_wc_is_not_ready(): void
    {
        $this->git->isWorkingTreeReady()->willReturn(false);
        $this->git->remoteBranchExists('upstream', '2.0')->willReturn(true);
        $this->github->getPullRequest(12)->willReturn([
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ]
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The Git working tree is not ready. There are uncommitted changes or a rebase is in progress.' .
            "\n" .
            'If there were conflicts during the switch run `git rebase --continue` and run the `switch-base` command again.'
        );

        $this->executeHandler($this->getArgs(12, '2.0'));
    }

    /** @test */
    public function it_switches_base(): void
    {
        $tmpBranch = '_temp/sstok--bug/new-feature-1--2.0';

        $this->github->getPullRequest(12)->willReturn([
            'number' => 12,
            'html_url' => 'https://github.com/sstok/hupkit/pull/12',
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ],
            'head' => [
                'ref' => $prBranch = 'bug/new-feature-1',
                'user' => ['login' => 'sstok'],
                'repo' => ['ssh_url' => 'git://github.com/sstok/hupkit.git'],
            ],
            'user' => ['login' => 'sstok'],
        ]);

        $this->expectWorkingTreeReady();
        $this->git->getActiveBranchName()->willReturn('main');

        $this->git->remoteBranchExists('upstream', '2.0')->will(self::trackReturn(Git::class, true));
        $this->git->ensureRemoteExists('sstok', 'git://github.com/sstok/hupkit.git')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('sstok')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getGitDirectory()->willReturn($gitDir = '/:local');
        $this->filesystem->fileExists($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, false));

        // Remove temp branch (always)
        $this->process->run(['git', 'branch', '-D', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->sfFilesystem->remove($gitDir . '/.hubkit-switch')->will(self::trackNoReturn(SfFilesystem::class));

        // Start rebase process
        $this->git->checkoutRemoteBranch('sstok', $prBranch, false)->will(self::trackNoReturn(Git::class));
        $this->git->checkout($tmpBranch, true)->will(self::trackNoReturn(Git::class));
        $this->filesystem->dumpFile($gitDir . '/.hubkit-switch', $tmpBranch)->will(self::trackNoReturn(Filesystem::class));

        $this->process->mustRun(['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->git->checkout('main')->will(self::trackNoReturn(Git::class));

        // Apply changes
        $this->process->mustRun(['git', 'push', '--force', 'sstok', $tmpBranch . ':' . $prBranch], 'Push failed (access disabled?)')->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        // Delete temp branch (again, should be recorded twice)

        $this->github->updatePullRequest(12, ['base' => '2.0'])->will(self::trackNoReturn(GitHub::class));
        $this->github->createComment(12, ProphecyArgument::any())->will(self::trackNoReturn(GitHub::class));
        $this->git->branchExists($prBranch)->will(self::trackReturn(Git::class, false));

        $this->executeHandler($this->getArgs(12, '2.0'));

        // Ensure all calls where made in the correct order.
        self::assertSame([
            [Git::class, 'remoteBranchExists', ['upstream', '2.0']],
            [Git::class, 'ensureRemoteExists', ['sstok', 'git://github.com/sstok/hupkit.git']],
            [Git::class, 'remoteUpdate', ['sstok']],
            [Filesystem::class, 'fileExists', ['/:local/.hubkit-switch']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [Git::class, 'checkoutRemoteBranch', ['sstok', 'bug/new-feature-1', false]],
            [Git::class, 'checkout', ['_temp/sstok--bug/new-feature-1--2.0', true]],
            [Filesystem::class, 'dumpFile', ['/:local/.hubkit-switch', '_temp/sstok--bug/new-feature-1--2.0']],
            [CliProcess::class, 'mustRun',[['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', '_temp/sstok--bug/new-feature-1--2.0']]],
            [Git::class, 'checkout', ['main']],
            [CliProcess::class, 'mustRun', [['git', 'push', '--force', 'sstok', '_temp/sstok--bug/new-feature-1--2.0:bug/new-feature-1'], 'Push failed (access disabled?)']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [GitHub::class, 'updatePullRequest', [12, ['base' => '2.0']]],
            [
                'HubKit\\Service\\GitHub',
                'createComment',
                [
                    12,
                    <<<MESSAGE
                    The base of this pull-request was changed, you need fetch and reset your local branch
                    if you want to add new commits to this pull request. **Reset before you pull, else commits
                    may become messed-up.**

                    Unless you added new commits (to this branch) locally that you did not push yet,
                    execute `git fetch origin && git reset "bug/new-feature-1"` to update your local branch.

                    Feel free to ask for assistance when you get stuck :+1:
                    MESSAGE,
                ],
            ],
            [Git::class, 'branchExists', ['bug/new-feature-1']],
        ], TrackedPromise::$calls);
    }

    /** @test */
    public function it_recovers_previous_rebase_and_user_aborts(): void
    {
        $tmpBranch = '_temp/sstok--bug/new-feature-1--2.0';

        $this->github->getPullRequest(12)->willReturn([
            'number' => 12,
            'html_url' => 'https://github.com/sstok/hupkit/pull/12',
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ],
            'head' => [
                'ref' => $prBranch = 'bug/new-feature-1',
                'user' => ['login' => 'sstok'],
                'repo' => ['ssh_url' => 'git://github.com/sstok/hupkit.git'],
            ],
            'user' => ['login' => 'sstok'],
        ]);

        $this->expectWorkingTreeReady();
        $this->git->getActiveBranchName()->willReturn('main');

        $this->git->remoteBranchExists('upstream', '2.0')->will(self::trackReturn(Git::class, true));
        $this->git->ensureRemoteExists('sstok', 'git://github.com/sstok/hupkit.git')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('sstok')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getGitDirectory()->willReturn($gitDir = '/:local');
        $this->filesystem->fileExists($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, true));
        $this->filesystem->getFileContents($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, $tmpBranch));

        try {
            // Don't abort previous and don't continue previous
            $this->executeHandler($this->getArgs(12, '2.0'), ['no', 'no']);

            self::fail('Expected exception.');
        } catch (\RuntimeException $e) {
            self::assertEquals('Failed! Cannot perform switch while another operation is still pending. Please abort previous operation first.', $e->getMessage());
            $this->assertOutputMatches(sprintf('Another switch operation was already in process for "%s"!', $tmpBranch));
        }
    }

    /** @test */
    public function it_recovers_previous_rebase_continue_previous(): void
    {
        $tmpBranch = '_temp/sstok--bug/new-feature-1--2.0';

        $this->github->getPullRequest(12)->willReturn([
            'number' => 12,
            'html_url' => 'https://github.com/sstok/hupkit/pull/12',
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ],
            'head' => [
                'ref' => $prBranch = 'bug/new-feature-1',
                'user' => ['login' => 'sstok'],
                'repo' => ['ssh_url' => 'git://github.com/sstok/hupkit.git'],
            ],
            'user' => ['login' => 'sstok'],
        ]);

        $this->expectWorkingTreeReady();
        $this->git->getActiveBranchName()->willReturn('main');

        $this->git->remoteBranchExists('upstream', '2.0')->will(self::trackReturn(Git::class, true));
        $this->git->ensureRemoteExists('sstok', 'git://github.com/sstok/hupkit.git')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('sstok')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getGitDirectory()->willReturn($gitDir = '/:local');
        $this->filesystem->fileExists($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, true));
        $this->filesystem->getFileContents($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, $tmpBranch));

        // Remove temp branch (always)
        $this->process->run(['git', 'branch', '-D', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->sfFilesystem->remove($gitDir . '/.hubkit-switch')->will(self::trackNoReturn(SfFilesystem::class));

        // Start rebase process
        $this->git->checkoutRemoteBranch('sstok', $prBranch, false)->will(self::trackNoReturn(Git::class));
        $this->git->checkout($tmpBranch, true)->will(self::trackNoReturn(Git::class));
        $this->filesystem->dumpFile($gitDir . '/.hubkit-switch', $tmpBranch)->will(self::trackNoReturn(Filesystem::class));

        $this->process->mustRun(['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->git->checkout('main')->will(self::trackNoReturn(Git::class));

        // Apply changes
        $this->process->mustRun(['git', 'push', '--force', 'sstok', $tmpBranch . ':' . $prBranch], 'Push failed (access disabled?)')->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        // Delete temp branch (again, should be recorded twice)

        $this->github->updatePullRequest(12, ['base' => '2.0'])->will(self::trackNoReturn(GitHub::class));
        $this->github->createComment(12, ProphecyArgument::any())->will(self::trackNoReturn(GitHub::class));
        $this->git->branchExists($prBranch)->will(self::trackReturn(Git::class, false));

        $this->executeHandler($this->getArgs(12, '2.0'), ['yes']);

        $this->assertOutputMatches([
            sprintf('Another switch operation was already in process for "%s"!', $tmpBranch),
            'Do you want to abort the previous operation?',
            'Pull request https://github.com/sstok/hupkit/pull/12 base was switched from "main" to "2.0".'
        ]);
        $this->assertOutputNotMatches('Do you want to continue the previous operation?');

        // Ensure all calls where made in the correct order.
        self::assertSame([
            [Git::class, 'remoteBranchExists', ['upstream', '2.0']],
            [Git::class, 'ensureRemoteExists', ['sstok', 'git://github.com/sstok/hupkit.git']],
            [Git::class, 'remoteUpdate', ['sstok']],
            [Filesystem::class, 'fileExists', ['/:local/.hubkit-switch']],
            [Filesystem::class, 'getFileContents', ['/:local/.hubkit-switch']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [Git::class, 'checkoutRemoteBranch', ['sstok', 'bug/new-feature-1', false]],
            [Git::class, 'checkout', ['_temp/sstok--bug/new-feature-1--2.0', true]],
            [Filesystem::class, 'dumpFile', ['/:local/.hubkit-switch', '_temp/sstok--bug/new-feature-1--2.0']],
            [CliProcess::class, 'mustRun',[['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', '_temp/sstok--bug/new-feature-1--2.0']]],
            [Git::class, 'checkout', ['main']],
            [CliProcess::class, 'mustRun', [['git', 'push', '--force', 'sstok', '_temp/sstok--bug/new-feature-1--2.0:bug/new-feature-1'], 'Push failed (access disabled?)']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [GitHub::class, 'updatePullRequest', [12, ['base' => '2.0']]],
            [
                'HubKit\\Service\\GitHub',
                'createComment',
                [
                    12,
                    <<<MESSAGE
                    The base of this pull-request was changed, you need fetch and reset your local branch
                    if you want to add new commits to this pull request. **Reset before you pull, else commits
                    may become messed-up.**

                    Unless you added new commits (to this branch) locally that you did not push yet,
                    execute `git fetch origin && git reset "bug/new-feature-1"` to update your local branch.

                    Feel free to ask for assistance when you get stuck :+1:
                    MESSAGE,
                ],
            ],
            [Git::class, 'branchExists', ['bug/new-feature-1']],
        ], TrackedPromise::$calls);
    }

    /** @test */
    public function it_recovers_previous_rebase_abort_previous(): void
    {
        $tmpBranch = '_temp/sstok--bug/new-feature-1--2.0';

        $this->github->getPullRequest(12)->willReturn([
            'number' => 12,
            'html_url' => 'https://github.com/sstok/hupkit/pull/12',
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ],
            'head' => [
                'ref' => $prBranch = 'bug/new-feature-1',
                'user' => ['login' => 'sstok'],
                'repo' => ['ssh_url' => 'git://github.com/sstok/hupkit.git'],
            ],
            'user' => ['login' => 'sstok'],
        ]);

        $this->expectWorkingTreeReady();
        $this->git->getActiveBranchName()->willReturn('main');

        $this->git->remoteBranchExists('upstream', '2.0')->will(self::trackReturn(Git::class, true));
        $this->git->ensureRemoteExists('sstok', 'git://github.com/sstok/hupkit.git')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('sstok')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getGitDirectory()->willReturn($gitDir = '/:local');
        $this->filesystem->fileExists($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, true));
        $this->filesystem->getFileContents($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, $tmpBranch));

        $this->git->getRemoteDiffStatus('sstok', $tmpBranch, $prBranch)->will(self::trackReturn(Git::class, Git::STATUS_UP_TO_DATE));
        $this->git->checkout($tmpBranch)->will(self::trackNoReturn(Git::class));

        // Remove temp branch (always)
        $this->process->run(['git', 'branch', '-D', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->sfFilesystem->remove($gitDir . '/.hubkit-switch')->will(self::trackNoReturn(SfFilesystem::class));

        // Start rebase process
        $this->git->checkoutRemoteBranch('sstok', $prBranch, false)->will(self::trackNoReturn(Git::class));
        $this->git->checkout($tmpBranch, true)->will(self::trackNoReturn(Git::class));
        $this->filesystem->dumpFile($gitDir . '/.hubkit-switch', $tmpBranch)->will(self::trackNoReturn(Filesystem::class));

        $this->process->mustRun(['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->git->checkout('main')->will(self::trackNoReturn(Git::class));

        // Apply changes
        $this->process->mustRun(['git', 'push', '--force', 'sstok', $tmpBranch . ':' . $prBranch], 'Push failed (access disabled?)')->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        // Delete temp branch (again, should be recorded twice)

        $this->github->updatePullRequest(12, ['base' => '2.0'])->will(self::trackNoReturn(GitHub::class));
        $this->github->createComment(12, ProphecyArgument::any())->will(self::trackNoReturn(GitHub::class));
        $this->git->branchExists($prBranch)->will(self::trackReturn(Git::class, false));

        $this->executeHandler($this->getArgs(12, '2.0'), ['no', 'yes']);

        $this->assertOutputMatches([
            sprintf('Another switch operation was already in process for "%s"!', $tmpBranch),
            'Do you want to abort the previous operation?',
            'Do you want to continue the previous operation?',
            'Pull request https://github.com/sstok/hupkit/pull/12 base was switched from "main" to "2.0".'
        ]);

        // Ensure all calls where made in the correct order.
        self::assertSame([
            [Git::class, 'remoteBranchExists', ['upstream', '2.0']],
            [Git::class, 'ensureRemoteExists', ['sstok', 'git://github.com/sstok/hupkit.git']],
            [Git::class, 'remoteUpdate', ['sstok']],
            [Filesystem::class, 'fileExists', ['/:local/.hubkit-switch']],
            [Filesystem::class, 'getFileContents', ['/:local/.hubkit-switch']],
            [Git::class, 'checkout', ['_temp/sstok--bug/new-feature-1--2.0']],
            [Git::class, 'getRemoteDiffStatus', ['sstok', '_temp/sstok--bug/new-feature-1--2.0', 'bug/new-feature-1']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [Git::class, 'checkoutRemoteBranch', ['sstok', 'bug/new-feature-1', false]],
            [Git::class, 'checkout', ['_temp/sstok--bug/new-feature-1--2.0', true]],
            [Filesystem::class, 'dumpFile', ['/:local/.hubkit-switch', '_temp/sstok--bug/new-feature-1--2.0']],
            [CliProcess::class, 'mustRun',[['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', '_temp/sstok--bug/new-feature-1--2.0']]],
            [Git::class, 'checkout', ['main']],
            [CliProcess::class, 'mustRun', [['git', 'push', '--force', 'sstok', '_temp/sstok--bug/new-feature-1--2.0:bug/new-feature-1'], 'Push failed (access disabled?)']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [GitHub::class, 'updatePullRequest', [12, ['base' => '2.0']]],
            [
                'HubKit\\Service\\GitHub',
                'createComment',
                [
                    12,
                    <<<MESSAGE
                    The base of this pull-request was changed, you need fetch and reset your local branch
                    if you want to add new commits to this pull request. **Reset before you pull, else commits
                    may become messed-up.**

                    Unless you added new commits (to this branch) locally that you did not push yet,
                    execute `git fetch origin && git reset "bug/new-feature-1"` to update your local branch.

                    Feel free to ask for assistance when you get stuck :+1:
                    MESSAGE,
                ],
            ],
            [Git::class, 'branchExists', ['bug/new-feature-1']],
        ], TrackedPromise::$calls);
    }

    /** @test */
    public function it_recovers_previous_rebase_abort_previous_diverged(): void
    {
        $tmpBranch = '_temp/sstok--bug/new-feature-1--2.0';

        $this->github->getPullRequest(12)->willReturn([
            'number' => 12,
            'html_url' => 'https://github.com/sstok/hupkit/pull/12',
            'state' => 'open',
            'base' => [
                'ref' => 'main',
            ],
            'head' => [
                'ref' => $prBranch = 'bug/new-feature-1',
                'user' => ['login' => 'sstok'],
                'repo' => ['ssh_url' => 'git://github.com/sstok/hupkit.git'],
            ],
            'user' => ['login' => 'sstok'],
        ]);

        $this->expectWorkingTreeReady();
        $this->git->getActiveBranchName()->willReturn('main');

        $this->git->remoteBranchExists('upstream', '2.0')->will(self::trackReturn(Git::class, true));
        $this->git->ensureRemoteExists('sstok', 'git://github.com/sstok/hupkit.git')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('sstok')->will(self::trackNoReturn(Git::class));
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getGitDirectory()->willReturn($gitDir = '/:local');
        $this->filesystem->fileExists($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, true));
        $this->filesystem->getFileContents($gitDir . '/.hubkit-switch')->will(self::trackReturn(Filesystem::class, $tmpBranch));

        $this->git->getRemoteDiffStatus('sstok', $tmpBranch, $prBranch)->will(self::trackReturn(Git::class, Git::STATUS_DIVERGED));
        $this->git->checkout($tmpBranch)->will(self::trackNoReturn(Git::class));

        // Remove temp branch (always)
        $this->process->run(['git', 'branch', '-D', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->sfFilesystem->remove($gitDir . '/.hubkit-switch')->will(self::trackNoReturn(SfFilesystem::class));

        // Start rebase process
        $this->git->checkoutRemoteBranch('sstok', $prBranch, false)->will(self::trackNoReturn(Git::class));
        $this->git->checkout($tmpBranch, true)->will(self::trackNoReturn(Git::class));
        $this->filesystem->dumpFile($gitDir . '/.hubkit-switch', $tmpBranch)->will(self::trackNoReturn(Filesystem::class));

        $this->process->mustRun(['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', $tmpBranch])->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        $this->git->checkout('main')->will(self::trackNoReturn(Git::class));

        // Apply changes
        $this->process->mustRun(['git', 'push', '--force', 'sstok', $tmpBranch . ':' . $prBranch], 'Push failed (access disabled?)')->will(self::trackReturn(CliProcess::class, $this->createMock(Process::class)));
        // Delete temp branch (again, should be recorded twice)

        $this->github->updatePullRequest(12, ['base' => '2.0'])->will(self::trackNoReturn(GitHub::class));
        $this->github->createComment(12, ProphecyArgument::any())->will(self::trackNoReturn(GitHub::class));
        $this->git->branchExists($prBranch)->will(self::trackReturn(Git::class, false));

        $this->executeHandler($this->getArgs(12, '2.0'), ['no', 'yes']);

        $this->assertOutputMatches([
            sprintf('Another switch operation was already in process for "%s"!', $tmpBranch),
            'Do you want to abort the previous operation?',
            'Do you want to continue the previous operation?',
            'Pull request https://github.com/sstok/hupkit/pull/12 base was switched from "main" to "2.0".'
        ]);

        // Ensure all calls where made in the correct order.
        self::assertSame([
            [Git::class, 'remoteBranchExists', ['upstream', '2.0']],
            [Git::class, 'ensureRemoteExists', ['sstok', 'git://github.com/sstok/hupkit.git']],
            [Git::class, 'remoteUpdate', ['sstok']],
            [Filesystem::class, 'fileExists', ['/:local/.hubkit-switch']],
            [Filesystem::class, 'getFileContents', ['/:local/.hubkit-switch']],
            [Git::class, 'checkout', ['_temp/sstok--bug/new-feature-1--2.0']],

            [Git::class, 'getRemoteDiffStatus', ['sstok', '_temp/sstok--bug/new-feature-1--2.0', 'bug/new-feature-1']],
            [CliProcess::class, 'mustRun', [['git', 'push', '--force', 'sstok', '_temp/sstok--bug/new-feature-1--2.0:bug/new-feature-1'], 'Push failed (access disabled?)']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [Git::class, 'checkoutRemoteBranch', ['sstok', 'bug/new-feature-1', false]],
            [Git::class, 'checkout', ['_temp/sstok--bug/new-feature-1--2.0', true]],
            [Filesystem::class, 'dumpFile', ['/:local/.hubkit-switch', '_temp/sstok--bug/new-feature-1--2.0']],
            [CliProcess::class, 'mustRun',[['git', 'rebase', '--onto', 'upstream/2.0', 'upstream/main', '_temp/sstok--bug/new-feature-1--2.0']]],
            [Git::class, 'checkout', ['main']],
            [CliProcess::class, 'mustRun', [['git', 'push', '--force', 'sstok', '_temp/sstok--bug/new-feature-1--2.0:bug/new-feature-1'], 'Push failed (access disabled?)']],
            [CliProcess::class, 'run', [['git', 'branch', '-D', '_temp/sstok--bug/new-feature-1--2.0']]],
            [SfFilesystem::class, 'remove', ['/:local/.hubkit-switch']],
            [GitHub::class, 'updatePullRequest', [12, ['base' => '2.0']]],
            [
                'HubKit\\Service\\GitHub',
                'createComment',
                [
                    12,
                    <<<MESSAGE
                    The base of this pull-request was changed, you need fetch and reset your local branch
                    if you want to add new commits to this pull request. **Reset before you pull, else commits
                    may become messed-up.**

                    Unless you added new commits (to this branch) locally that you did not push yet,
                    execute `git fetch origin && git reset "bug/new-feature-1"` to update your local branch.

                    Feel free to ask for assistance when you get stuck :+1:
                    MESSAGE,
                ],
            ],
            [Git::class, 'branchExists', ['bug/new-feature-1']],
        ], TrackedPromise::$calls);
    }

    private function getArgs(int $number, string $newBase): Args
    {
        $format = ArgsFormat::build()
            ->addOption(new Option('skip-help', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addArgument(new Argument('number', Argument::REQUIRED | Argument::INTEGER))
            ->addArgument(new Argument('new-base', Argument::REQUIRED | Argument::STRING))
            ->getFormat()
        ;

        $args = new Args($format, new StringArgs(''));
        $args->setArgument('number', $number);
        $args->setArgument('new-base', $newBase);

        return $args;
    }

    private function executeHandler(Args $args, array $input = []): void
    {
        $style = $this->createStyle($input);
        $handler = new SwitchBaseHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
            $this->process->reveal(),
            $this->filesystem->reveal(),
        );

        $handler->handle($args);
    }

    private function expectWorkingTreeReady(): void
    {
        $this->git->isWorkingTreeReady()->willReturn(true);
    }

    private static function trackReturn(string $class, mixed $value): TrackedPromise
    {
        return TrackedPromise::with($class, $value);
    }

    private static function trackNoReturn(string $class): TrackedPromise
    {
        return TrackedPromise::withoutReturn($class);
    }
}

/**
 * @internal
 */
final class TrackedPromise implements PromiseInterface
{
    public static $calls = [];

    private function __construct(
        private string $class,
        private mixed $returnValue,
        private bool $hasReturn = true
    ) {
    }

    public static function with(string $class, mixed $value): self
    {
        return new self($class, $value);
    }

    public static function withoutReturn(string $class): self
    {
        return new self($class, null, false);
    }

    public function execute(array $args, ObjectProphecy $object, MethodProphecy $method)
    {
        self::$calls[] = [$this->class, $method->getMethodName(), $args];

        if ($this->returnValue instanceof PromiseInterface) {
            return $this->returnValue->execute($args, $object, $method);
        }

        if ($this->hasReturn) {
            return $this->returnValue;
        }
    }
}
