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

use HubKit\Config;
use HubKit\Service\BranchSplitsh;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\SplitshGit;
use HubKit\Tests\Handler\SymfonyStyleTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @internal
 */
final class BranchSplitshTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    /** @var Git&ObjectProphecy */
    private $git;
    /** @var GitHub&ObjectProphecy */
    private $github;
    private Config $config;
    /** @var SplitshGit&ObjectProphecy */
    private $splitshGit;

    /** @before */
    public function setUpServices(): void
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->getActiveBranchName()->willReturn('master');

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('hubkit-sandbox');
        $this->github->getRepository()->willReturn('empire');

        $this->config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'sstok',
                    'api_token' => 'CHANGE-ME',
                ],
            ],
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'hubkit-sandbox/empire' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git', 'sync-tags' => null],
                                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git', 'sync-tags' => true],
                                    ],
                                ],
                                'master' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git', 'sync-tags' => false],
                                    ],
                                ],
                                '2.x' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'lobster' => ['url' => 'git@github.com:hubkit-sandbox/pinchy.git'],
                                    ],
                                ],
                                '3.x' => [
                                    'sync-tags' => true,
                                    'ignore-default' => true,
                                    'split' => [],
                                ],
                                '6.x' => [
                                    'sync-tags' => true,
                                    'ignore-default' => true,
                                    'split' => [
                                        'src/Module/WebhostingModule' => ['url' => false, 'sync-tags' => true],
                                    ],
                                ],
                            ],
                        ],
                        'hubkit-sandbox/website' => [
                            'branches' => [
                                '1.0' => [
                                    'sync-tags' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '_local' => [
                'branches' => [
                    'v1.1' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                            'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                            'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs2.git'],
                            'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop2.git'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->splitshGit = $this->prophesize(SplitshGit::class);
        $this->splitshGit->checkPrecondition()->shouldBeCalled();
    }

    /** @test */
    public function splits_prefix_to_destinations_with_no_explicit_config(): void
    {
        $this->git->ensureBranchInSync('upstream', '4.0')->shouldBeCalled();
        $this->expectGitSplit('src/Module/CoreModule', 'git@github.com:hubkit-sandbox/core-module.git', 'cc1', '4.0');

        self::assertEquals(
            [
                '/tmp/hubkit/stor/src/Module/CoreModule' => ['cc1', 'git@github.com:hubkit-sandbox/core-module.git'],
            ],
            $this->getBranchSplitsh()->splitAtPrefix('4.0', 'src/Module/CoreModule')
        );

        $this->assertOutputMatches([
            'Repository-split configuration for branch 4.0 resolved from :default.',
            'Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
        ]);
    }

    private function expectGitSplit(string $prefix, string $url, string $sha, string $branch = 'master', bool $success = true): void
    {
        $this->splitshGit->splitTo($branch, $prefix, $url)->willReturn($success ? ['/tmp/hubkit/stor/' . $prefix => [$sha, $url]] : null);
    }

    private function getBranchSplitsh(): BranchSplitsh
    {
        return new BranchSplitsh(
            $this->splitshGit->reveal(),
            $this->github->reveal(),
            $this->config,
            $this->createStyle(),
            $this->git->reveal()
        );
    }

    /** @test */
    public function splits_prefix_to_destinations_with_explicit_config(): void
    {
        $this->git->ensureBranchInSync('upstream', '2.1')->shouldBeCalled();
        $this->expectGitSplit('lobster', 'git@github.com:hubkit-sandbox/pinchy.git', 'cc1', '2.1');

        self::assertEquals(
            [
                '/tmp/hubkit/stor/lobster' => ['cc1', 'git@github.com:hubkit-sandbox/pinchy.git'],
            ],
            $this->getBranchSplitsh()->splitAtPrefix('2.1', 'lobster')
        );

        $this->assertOutputMatches([
            'Repository-split configuration for branch 2.1 resolved from 2.x.',
            'Splitting lobster to git@github.com:hubkit-sandbox/pinchy.git',
        ]);
    }

    /** @test */
    public function split_prefix_to_destinations_fails_for_missing_prefix_configuration(): void
    {
        $this->git->ensureBranchInSync('upstream', '4.1')->shouldBeCalled();

        $this->expectExceptionObject(new \InvalidArgumentException(
            'Unable to split repository at prefix: No entry found for "[repositories][github.com][repos][hubkit-sandbox/empire][branches][:default][split][pinchy]".'
        ));

        $this->getBranchSplitsh()->splitAtPrefix('4.1', 'pinchy');
    }

    /** @test */
    public function splits_branch_to_destinations_with_no_explicit_config(): void
    {
        $this->git->ensureBranchInSync('upstream', '4.0')->shouldBeCalled();
        $this->expectGitSplit('src/Module/CoreModule', 'git@github.com:hubkit-sandbox/core-module.git', 'cc1', '4.0');
        $this->expectGitSplit('src/Module/WebhostingModule', 'git@github.com:hubkit-sandbox/webhosting-module.git', 'cc2', '4.0');

        self::assertEquals(
            [
                'src/Module/CoreModule' => ['/tmp/hubkit/stor/src/Module/CoreModule' => ['cc1', 'git@github.com:hubkit-sandbox/core-module.git']],
                'src/Module/WebhostingModule' => ['/tmp/hubkit/stor/src/Module/WebhostingModule' => ['cc2', 'git@github.com:hubkit-sandbox/webhosting-module.git']],
            ],
            $this->getBranchSplitsh()->splitBranch('4.0')
        );

        $this->assertOutputMatches([
            'Repository-split configuration for branch 4.0 resolved from :default.',
            'Splitting from 4.0 to 2 destinations',
            'Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
            'Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
        ]);
    }

    /** @test */
    public function splits_branch_to_destinations_with_explicit_config(): void
    {
        $this->git->ensureBranchInSync('upstream', '2.1')->shouldBeCalled();
        $this->expectGitSplit('src/Module/CoreModule', 'git@github.com:hubkit-sandbox/core-module.git', 'cc1', '2.1');
        $this->expectGitSplit('src/Module/WebhostingModule', 'git@github.com:hubkit-sandbox/webhosting-module.git', 'cc2', '2.1');
        $this->expectGitSplit('lobster', 'git@github.com:hubkit-sandbox/pinchy.git', 'cc3', '2.1');

        self::assertEquals(
            [
                'src/Module/CoreModule' => ['/tmp/hubkit/stor/src/Module/CoreModule' => ['cc1', 'git@github.com:hubkit-sandbox/core-module.git']],
                'src/Module/WebhostingModule' => ['/tmp/hubkit/stor/src/Module/WebhostingModule' => ['cc2', 'git@github.com:hubkit-sandbox/webhosting-module.git']],
                'lobster' => ['/tmp/hubkit/stor/lobster' => ['cc3', 'git@github.com:hubkit-sandbox/pinchy.git']],
            ],
            $this->getBranchSplitsh()->splitBranch('2.1')
        );

        $this->assertOutputMatches([
            'Repository-split configuration for branch 2.1 resolved from 2.x.',
            'Splitting from 2.1 to 3 destinations',
            'Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
            'Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
            'Splitting lobster to git@github.com:hubkit-sandbox/pinchy.git',
        ]);
    }

    /** @test */
    public function splits_branch_to_destinations_with_explicit_config2(): void
    {
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->expectGitSplit('src/Module/CoreModule', 'git@github.com:hubkit-sandbox/core-module.git', 'cc1');
        $this->expectGitSplit('src/Module/WebhostingModule', 'git@github.com:hubkit-sandbox/webhosting-module.git', 'cc2');
        $this->expectGitSplit('docs', 'git@github.com:hubkit-sandbox/docs.git', 'cc3');
        $this->expectGitSplit('noop', 'git@github.com:hubkit-sandbox/noop.git', 'cc4');

        self::assertEquals(
            [
                'src/Module/CoreModule' => ['/tmp/hubkit/stor/src/Module/CoreModule' => ['cc1', 'git@github.com:hubkit-sandbox/core-module.git']],
                'src/Module/WebhostingModule' => ['/tmp/hubkit/stor/src/Module/WebhostingModule' => ['cc2', 'git@github.com:hubkit-sandbox/webhosting-module.git']],
                'docs' => ['/tmp/hubkit/stor/docs' => ['cc3', 'git@github.com:hubkit-sandbox/docs.git']],
                'noop' => ['/tmp/hubkit/stor/noop' => ['cc4', 'git@github.com:hubkit-sandbox/noop.git']],
            ],
            $this->getBranchSplitsh()->splitBranch('master')
        );

        $this->assertOutputMatches([
            'Splitting from master to 4 destinations',
            'Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
            'Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
            'Splitting docs to git@github.com:hubkit-sandbox/docs.git',
            'Splitting noop to git@github.com:hubkit-sandbox/noop.git',
        ]);

        // No special configuration resolved, so no need to show this information
        $this->assertOutputNotMatches('Repository-split configuration for branch master');
    }

    /** @test */
    public function splits_branch_to_destinations_is_ignored_when_no_splits_are_configured(): void
    {
        $this->expectNoSplitPerformed();
        $this->splitshGit->checkPrecondition()->shouldNotBeCalled();

        self::assertEquals(
            [],
            $this->getBranchSplitsh()->splitBranch('3.2')
        );

        $this->assertOutputMatches('No repository-split targets were found in config "[repositories][github.com][repos][hubkit-sandbox/empire][branches][3.x]".');
        $this->assertOutputNotMatches('Splitting from branch 3.2 to');
    }

    private function expectNoSplitPerformed(): void
    {
        $this->splitshGit->splitTo(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();
    }

    // Dry-run

    /** @test */
    public function dry_splits_prefix_to_destinations_with_no_explicit_config(): void
    {
        $this->git->ensureBranchInSync('upstream', '4.0')->shouldBeCalled();
        $this->expectNoSplitPerformed();

        $this->getBranchSplitsh()->drySplitAtPrefix('4.0', 'src/Module/CoreModule');

        $this->assertOutputMatches([
            'Repository-split configuration for branch 4.0 resolved from :default.',
            '[DRY-RUN] Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
        ]);
    }

    /** @test */
    public function dry_splits_prefix_to_destinations_with_explicit_config(): void
    {
        $this->git->ensureBranchInSync('upstream', '2.1')->shouldBeCalled();
        $this->expectNoSplitPerformed();

        $this->getBranchSplitsh()->drySplitAtPrefix('2.1', 'lobster');

        $this->assertOutputMatches([
            'Repository-split configuration for branch 2.1 resolved from 2.x.',
            '[DRY-RUN] Splitting lobster to git@github.com:hubkit-sandbox/pinchy.git',
        ]);
    }

    /** @test */
    public function dry_split_prefix_to_destinations_fails_for_missing_prefix_configuration(): void
    {
        $this->git->ensureBranchInSync('upstream', '2.1')->shouldBeCalled();

        $this->expectExceptionObject(new \InvalidArgumentException(
            'Unable to split repository at prefix: No entry found for "[repositories][github.com][repos][hubkit-sandbox/empire][branches][2.x][split][pinchy]".'
        ));

        $this->getBranchSplitsh()->drySplitAtPrefix('2.1', 'pinchy');
    }

    /** @test */
    public function dry_split_prefix_to_destinations_fails_for_disabled_prefix_configuration(): void
    {
        $this->git->ensureBranchInSync('upstream', '6.1')->shouldBeCalled();

        $this->expectExceptionObject(new \InvalidArgumentException(
            'Unable to split repository at prefix: Entry is disabled for "[repositories][github.com][repos][hubkit-sandbox/empire][branches][6.x][split][src/Module/WebhostingModule]".'
        ));

        $this->getBranchSplitsh()->drySplitAtPrefix('6.1', 'src/Module/WebhostingModule');
    }

    /** @test */
    public function dry_splits_branch_to_destinations_with_no_explicit_config(): void
    {
        $this->expectNoSplitPerformed();

        $this->getBranchSplitsh()->drySplitBranch('4.0');

        $this->assertOutputMatches([
            'Repository-split configuration for branch 4.0 resolved from :default.',
            'Would be splitting branch 4.0 to 2 destinations',
            '[DRY-RUN] Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
            '[DRY-RUN] Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
        ]);
    }

    /** @test */
    public function dry_splits_branch_to_destinations_with_explicit_config(): void
    {
        $this->expectNoSplitPerformed();

        $this->getBranchSplitsh()->drySplitBranch('2.1');

        $this->assertOutputMatches([
            'Repository-split configuration for branch 2.1 resolved from 2.x.',
            'Would be splitting branch 2.1 to 3 destinations',
            '[DRY-RUN] Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
            '[DRY-RUN] Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
            '[DRY-RUN] Splitting lobster to git@github.com:hubkit-sandbox/pinchy.git',
        ]);
    }
}
