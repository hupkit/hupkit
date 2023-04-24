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
use HubKit\Service\BranchSplitsh;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
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
    /** @var ObjectProphecy&BranchSplitsh */
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

        $this->splitshGit = $this->prophesize(BranchSplitsh::class);
    }

    /** @test */
    public function it_splits_with_current_branch(): void
    {
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldNotBeCalled();
        $this->splitshGit->splitBranch('master')->willReturn(['core' => ['42431142', 'url']]);

        $args = $this->getArgs();
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                'Repository directories were split into there destination.',
            ]
        );
    }

    /** @test */
    public function it_dry_splits_with_current_branch(): void
    {
        $this->git->checkoutRemoteBranch('upstream', 'master')->shouldNotBeCalled();
        $this->splitshGit->drySplitBranch('master')->willReturn(2);

        $args = $this->getArgs();
        $args->setOption('dry-run', true);

        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                '[DRY-RUN] Repository directories were split into there destination.',
            ]
        );
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

    private function executeHandler(?Args $args = null, array $input = []): void
    {
        $style = $this->createStyle($input);
        $handler = new SplitRepoHandler(
            $style,
            $this->splitshGit->reveal(),
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
        );

        $handler->handle($args ?? $this->getArgs());
    }

    /** @test */
    public function it_splits_specific_branch(): void
    {
        $this->git->checkoutRemoteBranch('upstream', '2.0')->shouldBeCalled();
        $this->splitshGit->splitBranch('2.0')->willReturn(['core' => ['42431142', 'url']]);

        $args = $this->getArgs();
        $args->setArgument('branch', '2.0');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch 2.0)',
                'Repository directories were split into there destination.',
            ]
        );
    }
}
