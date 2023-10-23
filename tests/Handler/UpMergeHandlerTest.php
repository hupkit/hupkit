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

use HubKit\Cli\Handler\UpMergeHandler;
use HubKit\Config;
use HubKit\Service\BranchSplitsh;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument as ProphecyArgument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;

/**
 * @internal
 */
final class UpMergeHandlerTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    private ObjectProphecy $process;
    private ObjectProphecy $git;
    private ObjectProphecy $github;
    private ObjectProphecy $branchSplitsh;

    private Config $config;

    /** @before */
    public function setUpCommandHandler(): void
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->guardWorkingTreeReady()->will(static function (): void {});
        $this->git->getPrimaryBranch()->willReturn('master');

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');

        $this->github->getDefaultBranch()->willReturn('master');

        $this->process = $this->prophesize(CliProcess::class);

        $this->expectConfigHasSplits();

        $this->config = new Config([
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'park-manager/park-manager' => [],
                    ],
                ],
            ],
        ]);
        $this->config->setActiveRepository('github.com', 'park-manager/hubkit');

        $this->branchSplitsh = $this->prophesize(BranchSplitsh::class);
        $this->branchSplitsh->splitAtPrefix(ProphecyArgument::any(), ProphecyArgument::any())->willReturn([])->shouldNotBeCalled();
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branch(): void
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5'])->shouldBeCalled();

        $this->branchSplitsh->splitBranch('2.5')->willReturn([]);

        $this->executeHandler();

        $this->assertOutputMatches('Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branch_and_skips_splitting_if_option_is_provided(): void
    {
        $this->expectConfigHasSplits();

        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5'])->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('no-split', true));

        $this->assertOutputMatches('Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_merges_current_branch_into_next_relative_version_branch(): void
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.x'])->shouldBeCalled();

        $this->branchSplitsh->splitBranch('2.x')->willReturn([]);

        $this->executeHandler();

        $this->assertOutputMatches('Merged "2.3" into "2.x"');
    }

    /** @test */
    public function it_merges_to_master_when_current_branch_is_last_version(): void
    {
        $this->git->getActiveBranchName()->willReturn('2.6');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkout('2.6')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['master'])->shouldBeCalled();

        $this->branchSplitsh->splitBranch('master')->willReturn([]);

        $this->executeHandler();

        $this->assertOutputMatches('Merged "2.6" into "master"');
    }

    /** @test */
    public function it_does_nothing_when_current_branch_is_not_a_version(): void
    {
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->executeHandler();
    }

    /** @test */
    public function it_merges_custom_branch_into_next_version_branch(): void
    {
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);
        $this->git->checkoutRemoteBranch('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5'])->shouldBeCalled();

        $this->branchSplitsh->splitBranch('2.5')->willReturn([]);

        $this->executeHandler($this->getArgs()->setArgument('branch', '2.3'));

        $this->branchSplitsh->splitBranch('2.5')->willReturn([]);

        $this->assertOutputMatches('Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branches(): void
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.5'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.x'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5', '2.6', '2.x', 'master'])->shouldBeCalled();

        foreach (['2.5', '2.6', '2.x', 'master'] as $branchTarget) {
            $this->branchSplitsh->splitBranch($branchTarget)->willReturn([]);
        }

        $this->executeHandler($this->getArgs()->setOption('all', true));

        $this->assertOutputMatches([
            'Merged "2.3" into "2.5"',
            'Merged "2.5" into "2.6"',
            'Merged "2.6" into "2.x"',
            'Merged "2.x" into "master"',
        ]);
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branches_and_updates_split(): void
    {
        $this->expectConfigHasSplits();

        foreach (['2.5', '2.6', '2.x', 'master'] as $branchTarget) {
            $this->branchSplitsh->splitBranch($branchTarget)->willReturn([]);
        }

        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.5'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.x'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5', '2.6', '2.x', 'master'])->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('all', true));

        $this->assertOutputMatches([
            'Merged "2.3" into "2.5"',
            'Merged "2.5" into "2.6"',
            'Merged "2.6" into "2.x"',
            'Merged "2.x" into "master"',
        ]);
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branches_and_skips_splitting_if_option_is_provided(): void
    {
        $this->expectConfigHasSplits();

        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.5'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.x'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5', '2.6', '2.x', 'master'])->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('all', true)->setOption('no-split', true));

        $this->assertOutputMatches([
            'Merged "2.3" into "2.5"',
            'Merged "2.5" into "2.6"',
            'Merged "2.6" into "2.x"',
            'Merged "2.x" into "master"',
        ]);
    }

    /** @test */
    public function it_does_nothing_with_all_when_current_branch_is_not_a_version(): void
    {
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        self::assertSame(1, $this->executeHandler($this->getArgs()->setOption('all', true)));

        $this->assertOutputMatches('Branch "master" is not a supported version branch.');
    }

    /** @test */
    public function it_does_nothing_when_current_is_last_branch(): void
    {
        $this->github->getDefaultBranch()->willReturn('2.3');

        $this->git->getActiveBranchName()->willReturn('2.6');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        $this->executeHandler();

        $this->assertOutputMatches('Nothing to do here, no branches to upmerge.');
    }

    /** @test */
    public function error_message_contains_original_exception_message(): void
    {
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);
        $this->git->checkoutRemoteBranch('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.3')->willThrow(
            new \RuntimeException('Local branch is not up-to-date.')
        );

        $this->executeHandler($this->getArgs()->setArgument('branch', '2.3'));
        $this->assertOutputMatches('Local branch is not up-to-date.');
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branches_skipping_upmerge_disabled_branches(): void
    {
        $this->expectConfigHasUpmergeDisabled();

        $this->git->checkout('1.3')->shouldBeCalled();

        $this->git->getActiveBranchName()->willReturn('1.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['1.3', '2.2', '2.3', '2.5', '2.6', '2.x', '3.0']);
        $this->git->ensureBranchInSync('upstream', '1.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '3.0')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '3.0')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '1.3'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '3.0'])->shouldBeCalled();

        $this->git->pushToRemote('upstream', ['3.0', 'master'])->shouldBeCalled();

        foreach (['3.0', 'master'] as $branchTarget) {
            $this->branchSplitsh->splitBranch($branchTarget)->willReturn([]);
        }

        $this->executeHandler($this->getArgs()->setOption('all', true));

        $this->assertOutputMatches([
            'Branch "2.2" has upmerge disabled by "2.x", and will be skipped.',
            'Branch "2.3" has upmerge disabled by "2.x", and will be skipped.',
            'Branch "2.6" has upmerge disabled by "2.x", and will be skipped.',
            'Branch "2.x" has upmerge disabled by "2.x", and will be skipped.',
            'Merged "1.3" into "3.0"',
            'Merged "3.0" into "master"',
        ]);
    }

    /** @test */
    public function it_does_nothing_when_current_branch_has_upmerge_disabled(): void
    {
        $this->expectConfigHasUpmergeDisabled();

        $this->git->getActiveBranchName()->willReturn('2.6');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);

        self::assertSame(1, $this->executeHandler());

        $this->assertOutputMatches('Branch "2.6" has upmerge disabled by "2.x".');
    }

    /** @test */
    public function it_dry_merges_current_branch_into_next_version_branch(): void
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6']);
        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();

        $this->branchSplitsh->drySplitBranch('2.5')->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('dry-run', true));

        $this->assertOutputMatches('[DRY-RUN] Merged "2.3" into "2.5"');
    }

    /** @test */
    public function it_dry_merges_current_branch_into_next_version_branches(): void
    {
        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);
        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();

        $this->branchSplitsh->drySplitBranch('2.5')->shouldBeCalled();
        $this->branchSplitsh->drySplitBranch('2.6')->shouldBeCalled();
        $this->branchSplitsh->drySplitBranch('2.x')->shouldBeCalled();
        $this->branchSplitsh->drySplitBranch('master')->shouldBeCalled();

        $this->executeHandler($this->getArgs()->setOption('all', true)->setOption('dry-run', true));

        $this->assertOutputMatches([
            '[DRY-RUN] Merged "2.3" into "2.5"',
            '[DRY-RUN] Merged "2.5" into "2.6"',
            '[DRY-RUN] Merged "2.6" into "2.x"',
            '[DRY-RUN] Merged "2.x" into "master"',
        ]);
    }

    /** @test */
    public function it_merges_current_branch_into_next_version_branches_without_master_branch(): void
    {
        $this->github->getDefaultBranch()->willReturn('2.x');

        $this->git->getActiveBranchName()->willReturn('2.3');
        $this->git->remoteUpdate('upstream')->shouldBeCalled();

        $this->git->getVersionBranches('upstream')->willReturn(['2.2', '2.3', '2.5', '2.6', '2.x']);

        $this->git->ensureBranchInSync('upstream', '2.3')->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.5')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.5')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.3'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.6')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.6')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.5'])->shouldBeCalled();

        $this->git->checkoutRemoteBranch('upstream', '2.x')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.x')->shouldBeCalled();
        $this->process->mustRun(['git', 'merge', '--no-ff', '--log', '2.6'])->shouldBeCalled();

        $this->git->checkout('2.3')->shouldBeCalled();
        $this->git->pushToRemote('upstream', ['2.5', '2.6', '2.x'])->shouldBeCalled();

        $this->branchSplitsh->splitBranch('2.5')->willReturn([]);
        $this->branchSplitsh->splitBranch('2.6')->willReturn([]);
        $this->branchSplitsh->splitBranch('2.x')->willReturn([]);

        $this->executeHandler($this->getArgs()->setOption('all', true));

        $this->assertOutputMatches([
            'Merged "2.3" into "2.5"',
            'Merged "2.5" into "2.6"',
            'Merged "2.6" into "2.x"',
        ]);
    }

    private function getArgs(): Args
    {
        $format = ArgsFormat::build()
            ->addOption(new Option('all', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addOption(new Option('dry-run', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addOption(new Option('no-split', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addArgument(new Argument('branch', Argument::OPTIONAL | Argument::STRING))
            ->getFormat()
        ;

        return new Args($format);
    }

    private function executeHandler(Args $args = null): int
    {
        $handler = new UpMergeHandler(
            $this->createStyle(),
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
            $this->process->reveal(),
            $this->branchSplitsh->reveal()
        );

        return $handler->handle($args ?? $this->getArgs());
    }

    private function expectConfigHasSplits(): void
    {
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
                        'park-manager/hubkit' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'src/Component/Core' => ['url' => 'git@github.com:park-manager/core.git', 'sync-tags' => null],
                                        'src/Component/Model' => ['url' => 'git@github.com:park-manager/model.git', 'sync-tags' => null],
                                        'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                                    ],
                                ],
                                'master' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'docs' => ['url' => 'git@github.com:park-manager/docs.git'],
                                        'noop' => ['url' => 'git@github.com:park-manager/noop.git', 'sync-tags' => false],
                                    ],
                                ],
                                '1.x' => [
                                    'sync-tags' => true,
                                    'upmerge' => true,
                                    'split' => [],
                                ],
                                '2.x' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'lobster' => ['url' => 'git@github.com:park-manager/pinchy.git'],
                                    ],
                                ],
                                '3.x' => [
                                    'sync-tags' => true,
                                    'ignore-default' => true,
                                    'split' => [],
                                ],
                            ],
                        ],
                        'park-manager/website' => [
                            'branches' => [
                                '1.0' => [
                                    'sync-tags' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->config->setActiveRepository('github.com', 'park-manager/hubkit');
    }

    private function expectConfigHasUpmergeDisabled(): void
    {
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
                        'park-manager/hubkit' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'src/Component/Core' => ['url' => 'git@github.com:park-manager/core.git', 'sync-tags' => null],
                                        'src/Component/Model' => ['url' => 'git@github.com:park-manager/model.git', 'sync-tags' => null],
                                        'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                                    ],
                                ],
                                'master' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'docs' => ['url' => 'git@github.com:park-manager/docs.git'],
                                        'noop' => ['url' => 'git@github.com:park-manager/noop.git', 'sync-tags' => false],
                                    ],
                                ],
                                '1.x' => [
                                    'sync-tags' => true,
                                    'split' => [],
                                ],
                                '2.x' => [
                                    'sync-tags' => true,
                                    'upmerge' => false,
                                    'split' => [
                                        'lobster' => ['url' => 'git@github.com:park-manager/pinchy.git'],
                                    ],
                                ],
                                '3.x' => [
                                    'sync-tags' => true,
                                    'ignore-default' => true,
                                    'split' => [],
                                ],
                                '4.x' => [
                                    'sync-tags' => true,
                                    'ignore-default' => true,
                                    'split' => [],
                                ],
                            ],
                        ],
                        'park-manager/website' => [
                            'branches' => [
                                '1.0' => [
                                    'sync-tags' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->config->setActiveRepository('github.com', 'park-manager/hubkit');
    }
}
