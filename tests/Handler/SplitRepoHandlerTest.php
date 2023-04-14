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

namespace HubKit\Tests\Handler;

use HubKit\Cli\Handler\SplitRepoHandler;
use HubKit\Config;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\SplitshGit;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Args\StringArgs;

/**
 * @internal
 */
final class SplitRepoHandlerTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    private const PR_NUMBER = 42;
    private const PR_BRANCH = 'feature-something';
    private const HEAD_SHA = '1b04532c8a09d9084abce36f8d9daf675f89eacc';
    private const MERGE_SHA = '52a6bb3aeb7e08e8b641cfa679e4416096bf8439';

    /** @var ObjectProphecy&Git */
    private $git;
    /** @var ObjectProphecy&GitHub */
    private $github;
    private Config $config;
    /** @var ObjectProphecy&SplitshGit */
    private $splitshGit;

    /** @before */
    public function setUpCommandHandler(): void
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->guardWorkingTreeReady()->shouldBeCalled();
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->getActiveBranchName()->willReturn('master');

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('hubkit-sandbox');
        $this->github->getRepository()->willReturn('empire');
        $this->github->getAuthUsername()->willReturn('sstok');

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
                                'master' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                                    ],
                                ],
                                '2.x' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
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
                    '1.1' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                            'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                            'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                            'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->splitshGit = $this->prophesize(SplitshGit::class);
        $this->splitshGit->checkPrecondition()->shouldBeCalled();
    }

    /** @test */
    public function it_splits_with_current_branch(): void
    {
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldNotBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();

        $this->expectGitSplit('src/Module/CoreModule', 'git@github.com:hubkit-sandbox/core-module.git');
        $this->expectGitSplit('src/Module/WebhostingModule', 'git@github.com:hubkit-sandbox/webhosting-module.git');
        $this->expectGitSplit('docs', 'git@github.com:hubkit-sandbox/docs.git');
        $this->expectGitSplit('noop', 'git@github.com:hubkit-sandbox/noop.git');

        $args = $this->getArgs();
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                'Split configuration resolved from branch master',
                '4 sources to split',
                'Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
                'Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
                'Splitting docs to git@github.com:hubkit-sandbox/docs.git',
                'Splitting noop to git@github.com:hubkit-sandbox/noop.git',
                'Repository directories were split into there destination.',
            ]
        );
    }

    /** @test */
    public function it_dry_splits_with_current_branch(): void
    {
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldNotBeCalled();
        $this->git->ensureBranchInSync('upstream', 'master')->shouldBeCalled();

        $args = $this->getArgs();
        $args->setOption('dry-run', true);

        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                'Split configuration resolved from branch master',
                '4 sources to split',
                '[DRY-RUN] Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
                '[DRY-RUN] Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
                '[DRY-RUN] Splitting docs to git@github.com:hubkit-sandbox/docs.git',
                '[DRY-RUN] Splitting noop to git@github.com:hubkit-sandbox/noop.git',
                '[DRY-RUN] Repository directories were split into there destination.',
            ]
        );
    }

    private function expectGitSplit(string $prefix, string $url, $targetBranch = 'master'): void
    {
        $this->splitshGit->splitTo($targetBranch, $prefix, $url)->shouldBeCalled();
    }

    private function getArgs(): Args
    {
        $format = ArgsFormat::build()
            ->addOption(new Option('dry-run', null, Option::BOOLEAN))
            ->addArgument(new Argument('branch', Argument::OPTIONAL | Argument::STRING))
            ->getFormat()
        ;

        return new Args($format, new StringArgs(''));
    }

    private function executeHandler(?Args $args = null, array $input = []): int
    {
        $style = $this->createStyle($input);
        $handler = new SplitRepoHandler(
            $style,
            $this->splitshGit->reveal(),
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
        );

        return $handler->handle($args ?? $this->getArgs());
    }

    /** @test */
    public function it_splits_specific_branch(): void
    {
        $this->git->checkoutRemoteBranch('upstream', '2.0')->shouldBeCalled();
        $this->git->ensureBranchInSync('upstream', '2.0')->shouldBeCalled();

        $this->expectGitSplit('src/Module/CoreModule', 'git@github.com:hubkit-sandbox/core-module.git', '2.0');
        $this->expectGitSplit('src/Module/WebhostingModule', 'git@github.com:hubkit-sandbox/webhosting-module.git', '2.0');

        $args = $this->getArgs();
        $args->setArgument('branch', '2.0');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch 2.0)',
                'Split configuration resolved from branch 2.x',
                '2 sources to split',
                'Splitting src/Module/CoreModule to git@github.com:hubkit-sandbox/core-module.git',
                'Splitting src/Module/WebhostingModule to git@github.com:hubkit-sandbox/webhosting-module.git',
                'Repository directories were split into there destination.',
            ]
        );
    }

    /** @test */
    public function it_rejects_splitting_when_no_config_exists(): void
    {
        $this->splitshGit->checkPrecondition()->shouldNotBeCalled();
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldNotBeCalled();

        $args = $this->getArgs();
        $args->setArgument('branch', '3.0');

        self::assertEquals(2, $this->executeHandler($args));

        $this->assertOutputMatches(
            'Unable to split repository: No targets were found in config "[repositories][github.com][repos][hubkit-sandbox/empire][branches][3.0][split]", update the (local) configuration file.',
        );
    }
}
