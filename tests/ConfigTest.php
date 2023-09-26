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

namespace HubKit\Tests;

use HubKit\BranchConfig;
use HubKit\Config;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ConfigTest extends TestCase
{
    /** @test */
    public function it_gets_config_or_default(): void
    {
        $config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'sstok',
                    'api_token' => 'CHANGE-ME',
                ],
            ],
        ]);

        self::assertSame(2, $config->get('schema_version'));
        self::assertSame(2, $config->get(['schema_version']));
        self::assertNull($config->get('schemas_version'));
        self::assertSame(5, $config->get('schemas_version', 5));
        self::assertSame([
            'github.com' => [
                'username' => 'sstok',
                'api_token' => 'CHANGE-ME',
            ],
        ], $config->get('github'));
        self::assertSame('sstok', $config->get(['github', 'github.com', 'username']));

        self::assertTrue($config->has('schema_version'));
        self::assertTrue($config->has('github'));
        self::assertTrue($config->has(['github', 'github.com']));
        self::assertFalse($config->has(['github', 'githuh.com']));
    }

    /**
     * @test
     *
     * @dataProvider provideFailedConfigs
     *
     * @param array<int, string>|string $path
     */
    public function it_gets_config_or_fail(array | string $path): void
    {
        $config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'sstok',
                    'api_token' => 'CHANGE-ME',
                ],
            ],
        ]);

        self::assertSame(2, $config->getOrFail('schema_version'));
        self::assertSame([
            'github.com' => [
                'username' => 'sstok',
                'api_token' => 'CHANGE-ME',
            ],
        ], $config->getOrFail('github'));
        self::assertSame('sstok', $config->getOrFail(['github', 'github.com', 'username']));

        $this->expectExceptionObject(new \InvalidArgumentException(sprintf('Unable to find config "[%s]"', implode('][', (array) $path))));

        $config->getOrFail($path);
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    public function provideFailedConfigs(): iterable
    {
        yield ['schemas_version'];
        yield [['github', 'example.com']];
    }

    /** @test */
    public function it_gets_first_not_null(): void
    {
        $config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'sstok',
                    'api_token' => 'CHANGE-ME',
                ],
            ],
        ]);

        self::assertSame(2, $config->getFirstNotNull(['schema_version']));
        self::assertSame(2, $config->getFirstNotNull(['schemas_version', 'schema_version']));
        self::assertSame(6, $config->getFirstNotNull(['schemas_version', 'schema_versions'], 6));
        self::assertNull($config->getFirstNotNull(['schemas_version', 'schema_versions']));
        self::assertSame([
            'github.com' => [
                'username' => 'sstok',
                'api_token' => 'CHANGE-ME',
            ],
        ], $config->getFirstNotNull(['github']));

        self::assertSame('sstok', $config->getFirstNotNull([['github', 'github.com', 'username']]));
    }

    /** @test */
    public function it_gets_for_repository(): void
    {
        $config = new Config([
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
                        'hubkit-sandbox/empire' => $repoConfig = [
                            'branches' => [
                                '1.0' => [
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
                        'hubkit-sandbox/website' => $repoConfig2 = [
                            'branches' => [
                                '1.0' => [
                                    'sync-tags' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            '_local' => $localConfig = [
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

        self::assertSame($repoConfig, $config->getForRepository('github.com', 'hubkit-sandbox/empire'));
        self::assertSame($repoConfig2, $config->getForRepository('github.com', 'hubkit-sandbox/website'));
        self::assertSame(['branches' => []], $config->getForRepository('github.com', 'hubkit-sandbox/application'));

        $config->setActiveRepository('github.com', 'hubkit-sandbox/empire');

        self::assertSame($localConfig, $config->getForRepository('github.com', 'hubkit-sandbox/empire'));
        self::assertSame($repoConfig2, $config->getForRepository('github.com', 'hubkit-sandbox/website'));
        self::assertSame(['branches' => []], $config->getForRepository('github.com', 'hubkit-sandbox/application'));
    }

    /** @test */
    public function it_gets_for_branch_config(): void
    {
        $config = new Config([
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
                                '1.0' => [
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
                        'hubkit-sandbox/application' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                                    ],
                                ],
                                '1.0' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                                    ],
                                ],
                                '11.0' => [
                                    'sync-tags' => false,
                                    'ignore-default' => true,
                                    'split' => [],
                                ],
                                '1.*' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'foo' => ['url' => 'git@github.com:hubkit-sandbox/foo.git', 'sync-tags' => true],
                                    ],
                                ],
                                '/1\.*/' => [ // This should be ignored as the first pattern matches.
                                    'sync-tags' => false,
                                    'split' => [
                                        'foo2' => ['url' => 'git@github.com:hubkit-sandbox/foo2.git'],
                                    ],
                                ],
                                '2.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module3.git'],
                                        'foo' => ['url' => 'git@github.com:hubkit-sandbox/foo.git'],
                                    ],
                                ],
                                '#3.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module4.git'],
                                        'foo' => ['url' => 'git@github.com:hubkit-sandbox/foo3.git'],
                                    ],
                                ],
                                '/[3-9]\.\d+/' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'foo3' => ['url' => 'git@github.com:hubkit-sandbox/foo3.git'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            '_local' => [
                'branches' => [
                    ':default' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module2.git'],
                            'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module2.git'],
                        ],
                    ],
                    '1.*' => [
                        'sync-tags' => false,
                        'split' => [
                            'foo4' => ['url' => 'git@github.com:hubkit-sandbox/foo4.git'],
                        ],
                    ],
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

        self::assertEquals(
            new BranchConfig(
                '1.0',
                [
                    'sync-tags' => true,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                    ],
                ],
                configName: '1.0',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/empire', 'branches', '1.0'],
            ),
            $config->getBranchConfig('1.0', 'github.com', 'hubkit-sandbox/empire')
        );

        self::assertEquals(
            new BranchConfig(
                '2.0',
                [],
                configName: '2.0',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/empire', 'branches', '2.0'],
            ),
            $config->getBranchConfig('2.0', 'github.com', 'hubkit-sandbox/empire')
        );

        self::assertEquals(
            new BranchConfig(
                '#3.x',
                [],
                configName: '#3.x',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/empire', 'branches', '#3.x'],
            ),
            $config->getBranchConfig('#3.x', 'github.com', 'hubkit-sandbox/empire')
        );

        self::assertEquals(
            new BranchConfig(
                '1.0',
                [
                    'sync-tags' => false,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                    ],
                ],
                configName: '1.0',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', '1.0'],
            ),
            $config->getBranchConfig('1.0', 'github.com', 'hubkit-sandbox/application')
        );

        self::assertEquals(
            new BranchConfig(
                '1.1',
                [
                    'sync-tags' => false,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                        'foo' => ['url' => 'git@github.com:hubkit-sandbox/foo.git', 'sync-tags' => true],
                    ],
                ],
                configName: '1.*',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', '1.*'],
            ),
            $config->getBranchConfig('1.1', 'github.com', 'hubkit-sandbox/application')
        );

        self::assertEquals(
            new BranchConfig(
                '2.1',
                [
                    'sync-tags' => false,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module3.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                        'foo' => ['url' => 'git@github.com:hubkit-sandbox/foo.git'],
                    ],
                ],
                configName: '2.x',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', '2.x'],
            ),
            $config->getBranchConfig('2.1', 'github.com', 'hubkit-sandbox/application')
        );

        self::assertEquals(
            new BranchConfig(
                '4.5',
                [
                    'sync-tags' => false,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                        'foo3' => ['url' => 'git@github.com:hubkit-sandbox/foo3.git'],
                    ],
                ],
                configName: '/[3-9]\.\d+/',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', '/[3-9]\.\d+/'],
            ),
            $config->getBranchConfig('4.5', 'github.com', 'hubkit-sandbox/application')
        );

        $config->setActiveRepository('github.com', 'hubkit-sandbox/empire');

        self::assertEquals(
            new BranchConfig(
                '1.0',
                [
                    'sync-tags' => false,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module2.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module2.git'],
                        'foo4' => ['url' => 'git@github.com:hubkit-sandbox/foo4.git'],
                    ],
                ],
                configName: '1.*',
                configPath: ['_local', 'branches', '1.*'],
            ),
            $config->getBranchConfig('1.0', 'github.com', 'hubkit-sandbox/empire')
        );

        self::assertEquals(
            new BranchConfig(
                '1.0',
                [
                    'sync-tags' => false,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                        'docs' => ['url' => 'git@github.com:hubkit-sandbox/docs.git'],
                        'noop' => ['url' => 'git@github.com:hubkit-sandbox/noop.git'],
                    ],
                ],
                configName: '1.0',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', '1.0'],
            ),
            $config->getBranchConfig('1.0', 'github.com', 'hubkit-sandbox/application')
        );

        // No explicit branch found, resolved from :default
        self::assertEquals(
            new BranchConfig(
                '10.5',
                [
                    'sync-tags' => true,
                    'split' => [
                        'src/Module/CoreModule' => ['url' => 'git@github.com:hubkit-sandbox/core-module.git'],
                        'src/Module/WebhostingModule' => ['url' => 'git@github.com:hubkit-sandbox/webhosting-module.git'],
                    ],
                ],
                configName: ':default',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', ':default'],
            ),
            $config->getBranchConfig('10.5', 'github.com', 'hubkit-sandbox/application')
        );

        // No explicit branch found, default ignored
        self::assertEquals(
            new BranchConfig(
                '11.0',
                [
                    'sync-tags' => false,
                    'ignore-default' => true,
                    'split' => [],
                ],
                configName: '11.0',
                configPath: ['repositories', 'github.com', 'repos', 'hubkit-sandbox/application', 'branches', '11.0'],
            ),
            $config->getBranchConfig('11.0', 'github.com', 'hubkit-sandbox/application')
        );
    }
}
