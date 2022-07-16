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

namespace HubKit\Tests\Unit\Service\Git;

use HubKit\Service\CliProcess;
use HubKit\Service\Git\GitBranch;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class GitBranchTest extends TestCase
{
    use ProphecyTrait;

    public function provideExpectedVersions(): array
    {
        return [
            [['master', '1.0', 'v1.1', '2.0', 'x.1'], ['1.0', 'v1.1', '2.0']],
            [['v1.1', 'master', '2.0', '1.0'], ['1.0', 'v1.1', '2.0']],
            [['master', 'feature-1.0', '1.0', 'v1.1', '2.0'], ['1.0', 'v1.1', '2.0']],
            [['master', '1.0', 'v1.1', '2.0', '1.x'], ['1.0', 'v1.1', '1.x', '2.0']],
            [['master', '1.0', 'v1.1', '2.0', 'v1.x'], ['1.0', 'v1.1', 'v1.x', '2.0']],

            'Duplicate version match' => [['master', '1.0', 'v1.0', 'v1.1', '2.0', 'v1.x'], ['1.0', 'v1.0', 'v1.1', 'v1.x', '2.0']],
            'Duplicate version match 2' => [['master', 'v1.0', 'v1.1', '2.0', 'v1.x', '1.0'], ['v1.0', '1.0', 'v1.1', 'v1.x', '2.0']],
        ];
    }

    /**
     * @test
     * @dataProvider provideExpectedVersions
     */
    public function it_gets_versioned_branches_in_correct_order(array $branches, array $expectedVersions): void
    {
        self::assertSame($expectedVersions, $this->createGitService($branches)->getVersionBranches('upstream'));
    }

    private function createGitService(array $branches): GitBranch
    {
        $process = $this->prophesize(Process::class);
        $process->getOutput()->willReturn(implode("\n", $branches));

        $processHelper = $this->prophesize(CliProcess::class);
        $processHelper
            ->mustRun(['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/upstream'])
            ->willReturn($process->reveal())
        ;

        return new GitBranch($processHelper->reveal(), $this->createMock(StyleInterface::class));
    }

    /** @test */
    public function it_syncs_nothing_when_diff_status_gives_up_to_date(): void
    {
        $git = $this->givenGitRemoteDiffStatus(GitBranch::STATUS_UP_TO_DATE);

        $git->ensureBranchInSync('origin', 'master', true);

        self::assertEquals(['origin', 'master'], $git->diffStatusCall);
        self::assertEquals([], $git->pullCall, 'No pull was expected');
        self::assertEquals([], $git->pushCall, 'No push was expected');
    }

    /** @test */
    public function it_syncs_pull_when_diff_status_gives_needs_pull(): void
    {
        $git = $this->givenGitRemoteDiffStatus(GitBranch::STATUS_NEED_PULL);

        $git->ensureBranchInSync('origin', 'master', true);

        self::assertEquals(['origin', 'master'], $git->diffStatusCall);
        self::assertEquals(['origin', 'master'], $git->pullCall);
        self::assertEquals([], $git->pushCall, 'No push was expected');
    }

    /** @test */
    public function it_syncs_push_when_diff_status_gives_needs_push_and_push_is_allowed(): void
    {
        $git = $this->givenGitRemoteDiffStatus(GitBranch::STATUS_NEED_PUSH);

        $git->ensureBranchInSync('origin', 'master', true);

        self::assertEquals(['origin', 'master'], $git->diffStatusCall);
        self::assertEquals([], $git->pullCall, 'No pull was expected');
        self::assertEquals([], $git->pushCall);
    }

    /** @test */
    public function it_syncs_throws_when_diff_status_gives_needs_push_but_push_is_forbidden(): void
    {
        $git = $this->givenGitRemoteDiffStatus(GitBranch::STATUS_NEED_PUSH);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Branch "master" contains commits not existing in the remote version.Push is prohibited for this operation.'
        );

        $git->ensureBranchInSync('origin', 'master', false);
    }

    /** @test */
    public function it_syncs_throws_when_diff_status_gives_diverged(): void
    {
        $git = $this->givenGitRemoteDiffStatus(GitBranch::STATUS_DIVERGED);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot safely perform the operation. ' .
            'Your local and remote version of branch "master" have differed.' .
            ' Please resolve this problem manually.'
        );

        $git->ensureBranchInSync('origin', 'master', true);
    }

    /**
     * @return GitBranch|object
     */
    private function givenGitRemoteDiffStatus(string $status)
    {
        $style = $this->createMock(StyleInterface::class);

        // Use a self-shunt to mock the return status of getRemoteDiffStatus and spy on the pushToRemote.
        //
        // Performing actual Git commands for this related test would be much slower (and not to mention difficult),
        // plus the called methods are already tested on their own. ensureBranchInSync() is merely a helper method.
        $git = new class($style) extends GitBranch {
            public $diffStatus;
            public $diffStatusCall = [];
            public $pushCall = [];
            public $pullCall = [];

            public function __construct(StyleInterface $style)
            {
                $this->style = $style;
            }

            public function getRemoteDiffStatus(string $remoteName, string $localBranch, string $remoteBranch = null): string
            {
                $this->diffStatusCall = [$remoteName, $localBranch];

                return $this->diffStatus;
            }

            public function pushToRemote(string $remote, $ref, bool $setUpstream = false, bool $force = false): void
            {
                $this->pushCall = [$remote, $ref, $setUpstream, $force];
            }

            public function pullRemote(string $remote, string $ref = null): void
            {
                $this->pullCall = [$remote, $ref];
            }
        };
        $git->diffStatus = $status;

        return $git;
    }
}
