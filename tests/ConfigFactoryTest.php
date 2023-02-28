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

use HubKit\Config;
use HubKit\ConfigFactory;
use HubKit\Service\Git\GitFileReader;
use HubKit\Tests\Handler\SymfonyStyleTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
final class ConfigFactoryTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    /** @test */
    public function it_creates_for_v1_schema(): void
    {
        $config = new Config([
            'schema_version' => 1,
            'github' => [
                'github.com' => [
                    'username' => 'test',
                    'api_token' => 'test-token',
                ],
            ],
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'park-manager/park-manager' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => null],
                                        'src/Bundle/UserBundle' => ['url' => 'git@github.com:park-manager/user-bundle.git', 'sync-tags' => null],
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => true,
                                    'ignore-default' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v1_local',
        ]);

        self::assertEquals(
            $config,
            (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v1_local',
                __DIR__ . '/Fixtures/config/schema_v1_global/config.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile()
            ))->create()
        );

        $this->assertOutputMatches('Hubkit "schema_version" 1 in configuration is deprecated and will no longer work in v2.0.');
    }

    private function getGitFileReaderWithNotExistentFile(): GitFileReader
    {
        $gitFileReaderProphecy = $this->prophesize(GitFileReader::class);
        $gitFileReaderProphecy->fileExists('_hubkit', 'config.php')->willReturn(false);

        return $gitFileReaderProphecy->reveal();
    }

    /** @test */
    public function it_creates_for_v1n_schema(): void
    {
        $config = new Config([
            'schema_version' => 1, // Schema v1 new
            'github' => [
                'github.com' => [
                    'username' => 'test',
                    'api_token' => 'test-token',
                ],
            ],
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'park-manager/park-manager' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => null],
                                        'src/Bundle/UserBundle' => ['url' => 'git@github.com:park-manager/user-bundle.git', 'sync-tags' => false], // false as null as actual value is not accepted
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => true,
                                    'ignore-default' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v1_local',
        ]);

        self::assertEquals(
            $config,
            (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v1_local',
                __DIR__ . '/Fixtures/config/schema_v1n_global/config.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile()
            ))->create()
        );

        $this->assertOutputMatches('Hubkit "schema_version" 1 in configuration is deprecated and will no longer work in v2.0.');
    }

    /** @test */
    public function it_creates_for_v2_schema(): void
    {
        $config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'test',
                    'api_token' => 'test-token',
                ],
            ],
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'park-manager/park-manager' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => null],
                                        'src/Bundle/UserBundle' => ['url' => 'git@github.com:park-manager/user-bundle.git', 'sync-tags' => null],
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => true,
                                    'ignore-default' => false,
                                ],

                                // Additional branch names for testing
                                'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],
                                'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],

                                '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],
                                '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],
                                '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],

                                // Pattern
                                '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],
                                '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],

                                // Regexp (without anchors and options)
                                '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false],

                                '10.0' => [
                                    'sync-tags' => true,
                                    'split' => [],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_global',
        ]);

        self::assertEquals(
            $config,
            (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_global',
                __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile()
            ))->create()
        );

        $this->assertNoOutput();
    }

    /** @test */
    public function it_creates_with_local_config_file(): void
    {
        $config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'test',
                    'api_token' => 'test-token',
                ],
            ],
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'park-manager/park-manager' => [
                            'branches' => [
                                ':default' => [
                                    'sync-tags' => true,
                                    'split' => [
                                        'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => null],
                                        'src/Bundle/UserBundle' => ['url' => 'git@github.com:park-manager/user-bundle.git', 'sync-tags' => false], // false as null as actual value is not accepted
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => true,
                                    'ignore-default' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '_local' => [
                'branches' => [
                    ':default' => [
                        'upmerge' => true,
                        'sync-tags' => true,
                        'split' => [],
                        'ignore-default' => false,
                    ],
                    '2.0' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Bundle/CoreBundle' => [
                                'url' => 'git@github.com:park-manager/core-bundle.git',
                                'sync-tags' => null,
                            ],
                            'src/Bundle/UserBundle' => [
                                'url' => 'git@github.com:park-manager/user-bundle.git',
                                'sync-tags' => null,
                            ],
                            'doc' => [
                                'url' => 'git@github.com:park-manager/doc.git',
                                'sync-tags' => false,
                            ],
                        ],
                        'upmerge' => true,
                        'ignore-default' => false,
                    ],
                ],
                'adapter' => 'github',
                'host' => null,
                'repository' => null,
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_local',
        ]);

        self::assertEquals(
            $config,
            (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_local',
                __DIR__ . '/Fixtures/config/schema_v2_global/config.php',
                $this->createStyle(),
                $this->getGitFileReaderWithExistentFile(__DIR__ . '/Fixtures/config/schema_v2_local/.hubkit/config.php')
            ))->create()
        );

        $this->assertNoOutput();
    }

    private function getGitFileReaderWithExistentFile(string $fileLocation): GitFileReader
    {
        $gitFileReaderProphecy = $this->prophesize(GitFileReader::class);
        $gitFileReaderProphecy->fileExists('_hubkit', 'config.php')->willReturn(true);
        $gitFileReaderProphecy->getFile('_hubkit', 'config.php')->willReturn($fileLocation);

        return $gitFileReaderProphecy->reveal();
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidConfigs
     */
    public function it_validates_configuration(string $configFile, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration contains one or more errors: ' . $message);

        (new ConfigFactory(
            __DIR__ . '/Fixtures/config/schema_v2_global',
            __DIR__ . '/Fixtures/config/invalid/' . $configFile,
            $this->createStyle(),
            $this->getGitFileReaderWithNotExistentFile()
        ))->create();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public function provideInvalidConfigs(): iterable
    {
        yield 'branches: non versioned branch' => ['invalid_branch_name.php', 'Invalid configuration for path "hubkit.repositories.github.com.repos.park-manager/park-manager.branches": Invalid minor version or relative pattern "nee", must be either: 1.x, 1.*, main, master, ":default" or a regexp like "/0.[1-9]+/".'];
        yield 'branches: v prefix' => ['invalid_branch_pattern.php', 'Invalid configuration for path "hubkit.repositories.github.com.repos.park-manager/park-manager.branches": Invalid minor version or relative pattern "v2.0", must be either: 1.x, 1.*, main, master, ":default" or a regexp like "/0.[1-9]+/".'];
        yield 'branches: wildcard for major' => ['invalid_branch_pattern2.php', 'Invalid configuration for path "hubkit.repositories.github.com.repos.park-manager/park-manager.branches": Invalid minor version or relative pattern "x.0", must be either: 1.x, 1.*, main, master, ":default" or a regexp like "/0.[1-9]+/".'];
        yield 'branches: invalid regexp' => ['invalid_branch_regexp.php', 'Invalid configuration for path "hubkit.repositories.github.com.repos.park-manager/park-manager.branches": Invalid regexp "\/[]\/" error: "preg_match(): Compilation failed: missing terminating ] for character class at offset 2".'];
        yield 'branches: regexp with options' => ['invalid_branch_regexp2.php', 'Invalid configuration for path "hubkit.repositories.github.com.repos.park-manager/park-manager.branches": Invalid regexp "\/\\\\d\\\\.\\\\d+\/s", cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".'];
        yield 'branches: regexp with anchors' => ['invalid_branch_regexp3.php', 'Invalid configuration for path "hubkit.repositories.github.com.repos.park-manager/park-manager.branches": Invalid regexp "\/^\\\\d\\\\.\\\\d+$\/", cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".'];
    }
}
