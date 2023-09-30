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

use Github\Exception\RuntimeException as GitHubRuntimeException;
use HubKit\Cli\Handler\SplitCreatedHandler;
use HubKit\Config;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Args\StringArgs;

/**
 * @internal
 */
final class SplitCreatedHandlerTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    private ObjectProphecy $git;
    private ObjectProphecy $github;
    private Config $config;

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

        $this->github->getRepoInfo('hubkit-sandbox', 'empire')->willReturn(['private' => false]);
        $this->github->createRepo(Argument::any(), Argument::any(), Argument::any(), Argument::any())->will(static fn (array $a) => throw new \InvalidArgumentException(sprintf('Creation for %s/%s was not expected', $a[0], $a[1])));

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
                                        'docs' => ['url' => false],
                                    ],
                                ],
                                '2.x' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                                    ],
                                ],
                                '3.0' => [
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                                        'noop' => ['url' => false],
                                    ],
                                ],
                            ],
                        ],
                        'hubkit-sandbox/differences' => [
                            'branches' => [
                                'master' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                                        'docs' => ['url' => 'git@github.net:hubkit-sandbox/docs.git'],
                                        'noop' => ['url' => 'git@github.net:hubkit-sandbox/noop.git'],
                                        'bloop' => ['url' => 'git@github.org:hubkit-sandbox/weep.git'],
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
                                '2.x' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => false],
                                        'src/Module/WebhostingModule' => ['url' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->config->setActiveRepository('github.com', 'hubkit-sandbox/empire');
    }

    /** @test */
    public function it_does_nothing_when_there_are_no_splits(): void
    {
        $this->github->getOrganization()->willReturn('hubkit-sandbox');
        $this->github->getRepository()->willReturn('website');

        $this->github->getRepoInfo('hubkit-sandbox', 'website')->willReturn(['private' => false]);
        $this->config->setActiveRepository('github.com', 'hubkit-sandbox/empire');

        $this->executeHandler();

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/website (branch master)',
                'No repository splits found, or splits are disabled for all branches.',
            ]
        );

        $this->assertOutputNotMatches('The main repository is private, split repositories will be created as private.');
    }

    /** @test */
    public function it_creates_split_repository_targets(): void
    {
        $this->expectRepoIsCreated('core-module');
        $this->expectRepoIsCreated('webhosting-module');
        $this->expectRepoIsCreated('docs');
        $this->expectRepoIsCreated('noop');

        $this->executeHandler();

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                'Repository https://github.com/hubkit-sandbox/core-module was created.',
                'Repository https://github.com/hubkit-sandbox/webhosting-module was created.',
                'Repository https://github.com/hubkit-sandbox/docs was created.',
                'Repository https://github.com/hubkit-sandbox/noop was created.',
                'Repository splits were created.',
            ]
        );

        $this->assertOutputNotMatches('The main repository is private, split repositories will be created as private.');
    }

    /** @test */
    public function it_creates_split_repository_targets_with_private(): void
    {
        $this->github->getRepoInfo('hubkit-sandbox', 'empire')->willReturn(['private' => true]);

        $this->expectRepoIsCreated('core-module', public: false);
        $this->expectRepoIsCreated('webhosting-module', public: false);
        $this->expectRepoIsCreated('docs', public: false);
        $this->expectRepoIsCreated('noop', public: false);

        $this->executeHandler();

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                'The main repository is private, split repositories will be created as private.',
                'Repository https://github.com/hubkit-sandbox/core-module was created.',
                'Repository https://github.com/hubkit-sandbox/webhosting-module was created.',
                'Repository https://github.com/hubkit-sandbox/docs was created.',
                'Repository https://github.com/hubkit-sandbox/noop was created.',
                'Repository splits were created.',
            ]
        );
    }

    /** @test */
    public function it_creates_split_repository_targets_and_ignores_existing(): void
    {
        $this->expectRepoIsCreated('core-module');
        $this->expectRepoExists('webhosting-module');
        $this->expectRepoIsCreated('docs');
        $this->expectRepoIsCreated('noop');

        $this->executeHandler();

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/empire (branch master)',
                'Repository https://github.com/hubkit-sandbox/core-module was created.',
                'Repository https://github.com/hubkit-sandbox/webhosting-module already exists.',
                'Repository https://github.com/hubkit-sandbox/docs was created.',
                'Repository https://github.com/hubkit-sandbox/noop was created.',
                'Repository splits were created.',
            ]
        );
        $this->assertOutputNotMatches('Repository https://github.com/hubkit-sandbox/webhosting-module was created.');
    }

    /** @test */
    public function it_creates_split_repository_targets_and_ignores_existing_for_different_host(): void
    {
        $this->github->getRepository()->willReturn('differences');
        $this->github->getRepoInfo('hubkit-sandbox', 'differences')->willReturn(['private' => false]);
        $this->config->setActiveRepository('github.com', 'hubkit-sandbox/differences');

        $this->expectRepoIsCreated('core-module');
        $this->expectRepoExists('webhosting-module');
        $this->expectForHost('github.net', ['hubkit-sandbox/docs'], ['hubkit-sandbox/noop']);
        $this->expectForHost('github.org', [], ['hubkit-sandbox/weep']);

        $this->executeHandler();

        $this->assertOutputMatches(
            [
                'Working on hubkit-sandbox/differences (branch master)',
                'Repository https://github.com/hubkit-sandbox/core-module was created.',
                'Repository https://github.com/hubkit-sandbox/webhosting-module already exists.',
                'Repository https://github.net/hubkit-sandbox/docs already exists.',
                'Repository https://github.net/hubkit-sandbox/noop was created.',
                'Repository https://github.org/hubkit-sandbox/weep was created.',
                'Repository splits were created.',
            ]
        );
        $this->assertOutputNotMatches('Repository https://github.com/hubkit-sandbox/webhosting-module was created.');
    }

    private function expectRepoIsCreated(string $name, string $organization = 'hubkit-sandbox', bool $public = true): void
    {
        $this->github->getRepoInfo('hubkit-sandbox', $name)->willThrow(new GitHubRuntimeException('Not Found', 404));
        $this->github->createRepo($organization, $name, $public, false)->willReturn(
            ['html_url' => sprintf('https://github.com/%s/%s', $organization, $name)]
        );
    }

    private function expectRepoExists(string $name, string $organization = 'hubkit-sandbox'): void
    {
        $this->github->getRepoInfo($organization, $name)->will(static fn (array $a): array => ['html_url' => 'https://github.com/' . implode('/', $a)]);
    }

    /**
     * @param array<int, string> $exists  ['org/name']
     * @param array<int, string> $created ['org/name']
     */
    private function expectForHost(string $host, array $exists = [], array $created = [], bool $public = true): void
    {
        $prophecy = $this->prophesize(GitHub::class);
        $prophecy->getHostname()->willReturn($host);

        $prophecy->getRepoInfo(Argument::any(), Argument::any())->willThrow(new GitHubRuntimeException('Not Found', 404));
        $prophecy->createRepo(Argument::any(), Argument::any(), Argument::any(), Argument::any())->will(static fn (array $a) => throw new \InvalidArgumentException(sprintf('Creation for %s/%s was not expected at host %s', $a[0], $a[1], $host)));

        foreach ($exists as $repo) {
            $prophecy->getRepoInfo(...explode('/', $repo))->willReturn(['html_url' => "https://{$host}/{$repo}"]);
        }

        foreach ($created as $repo) {
            [$org, $name] = explode('/', $repo);
            $prophecy->createRepo($org, $name, $public, false)->willReturn(['html_url' => "https://{$host}/{$repo}"]);
        }

        $this->github->createForHost($host)->willReturn($prophecy->reveal());
    }

    private function getArgs(): Args
    {
        return new Args(ArgsFormat::build()->getFormat(), new StringArgs(''));
    }

    private function executeHandler(): void
    {
        $handler = new SplitCreatedHandler(
            $this->createStyle(),
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
        );

        $handler->handle($this->getArgs());
    }
}
