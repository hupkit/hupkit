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

namespace HubKit\Tests;

use HubKit\Config;
use HubKit\ConfigFactory;
use HubKit\Service\Git;
use HubKit\Service\Git\GitFileReader;
use HubKit\Tests\Handler\SymfonyStyleTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v1_local',
            '_main_branch' => 'main',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v1_local',
                __DIR__ . '/Fixtures/config/schema_v1_global/config.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGit(),
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v1_local',
            '_main_branch' => 'main',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v1_local',
                __DIR__ . '/Fixtures/config/schema_v1n_global/config.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGit(),
            ))->create()
        );
        self::assertEquals('main', $resolved->getMainBranch());

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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],

                                // Additional branch names for testing
                                'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Pattern
                                '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Regexp (without anchors and options)
                                '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '10.0' => [
                                    'sync-tags' => false,
                                    'split' => [],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => false,
                                ],

                                // Literal branch name, no pattern
                                '#11.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc2.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_global',
            '_main_branch' => 'main',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_global',
                __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGit(),
            ))->create()
        );
        self::assertEquals('main', $resolved->getMainBranch());

        $this->assertOutputMatches([
            'No "main_branch" was not set, this value will default to "main" in HuPKit v2.0.',
            'The "main_branch" is resolved as "main", set the "main_branch" option in your local configuration to change this.',
        ]);
    }

    /** @test */
    public function it_creates_for_v2_schema_with_master_as_main_branch(): void
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],

                                // Additional branch names for testing
                                'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Pattern
                                '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Regexp (without anchors and options)
                                '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '10.0' => [
                                    'sync-tags' => false,
                                    'split' => [],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => false,
                                ],

                                // Literal branch name, no pattern
                                '#11.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc2.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_global',
            '_main_branch' => 'master',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_global',
                __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGit('master'),
            ))->create()
        );
        self::assertEquals('master', $resolved->getMainBranch());

        $this->assertOutputMatches([
            'No "main_branch" was not set, this value will default to "main" in HuPKit v2.0.',
            'The "main_branch" is resolved as "master", set the "main_branch" option in your local configuration to change this.',
        ]);
    }

    /** @test */
    public function it_creates_for_v2_schema_with_a_versioned_branch_as_main_branch(): void
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],

                                // Additional branch names for testing
                                'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Pattern
                                '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Regexp (without anchors and options)
                                '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '10.0' => [
                                    'sync-tags' => false,
                                    'split' => [],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => false,
                                ],

                                // Literal branch name, no pattern
                                '#11.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc2.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_global',
            '_main_branch' => '3.0',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_global',
                __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGit(null, ['1.0', '2.0', '3.0']),
            ))->create()
        );
        self::assertEquals('3.0', $resolved->getMainBranch());

        $this->assertOutputMatches([
            'No "main_branch" was not set, this value will default to "main" in HuPKit v2.0.',
            'The "main_branch" is resolved as "3.0", set the "main_branch" option in your local configuration to change this.',
        ]);
    }

    /** @test */
    public function it_creates_for_v2_schema_without_any_known_branch_as_main_branch(): void
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],

                                // Additional branch names for testing
                                'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Pattern
                                '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Regexp (without anchors and options)
                                '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '10.0' => [
                                    'sync-tags' => false,
                                    'split' => [],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => false,
                                ],

                                // Literal branch name, no pattern
                                '#11.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc2.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_global',
            '_main_branch' => 'main',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_global',
                __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGitWithActiveExpected(),
            ))->create()
        );
        self::assertEquals('main', $resolved->getMainBranch());

        $this->assertOutputMatches([
            'No "main_branch" was not set, this value will default to "main" in HuPKit v2.0.',
            'The "main_branch" is resolved as "main", set the "main_branch" option in your local configuration to change this.',
        ]);
    }

    /** @test */
    public function it_creates_for_v2_schema_without_active_git_dir(): void
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],

                                // Additional branch names for testing
                                'main' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                'master' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '0.1' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '1.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '2.0' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Pattern
                                '3.x' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],
                                '4.*' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                // Regexp (without anchors and options)
                                '/[1-5]\.[0-9]/' => ['split' => ['doc' => ['url' => 'git@github.com:park-manager/brown.git', 'sync-tags' => null]], 'upmerge' => true, 'sync-tags' => true, 'ignore-default' => false, 'maintained' => true],

                                '10.0' => [
                                    'sync-tags' => false,
                                    'split' => [],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => false,
                                ],

                                // Literal branch name, no pattern
                                '#11.x' => [
                                    'sync-tags' => false,
                                    'split' => [
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc2.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                    'upmerge' => false,
                                    'ignore-default' => true,
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'current_dir' => __DIR__ . '/Fixtures/config/schema_v2_global',
            '_main_branch' => 'main',
        ]);

        self::assertEquals(
            $config,
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_global',
                __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
                $this->createStyle(),
                $this->getGitFileReaderWithNotExistentFile(),
                $this->getGitWithoutGitDir(),
            ))->create()
        );
        self::assertEquals('main', $resolved->getMainBranch());
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
                            'branches_alias' => [],
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
                                    'maintained' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '_local' => [
                'schema_version' => 2,
                'main_branch' => 'trunk',
                'branches_alias' => [],
                'branches' => [
                    ':default' => [
                        'upmerge' => true,
                        'sync-tags' => true,
                        'split' => [],
                        'ignore-default' => false,
                        'maintained' => true,
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
                        'maintained' => true,
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
            $resolved = (new ConfigFactory(
                __DIR__ . '/Fixtures/config/schema_v2_local',
                __DIR__ . '/Fixtures/config/schema_v2_global/config.php',
                $this->createStyle(),
                $this->getGitFileReaderWithExistentFile(__DIR__ . '/Fixtures/config/schema_v2_local/.hubkit/config.php'),
                $this->getGit(),
            ))->create()
        );
        self::assertEquals('trunk', $resolved->getMainBranch());

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
     * @dataProvider provideInvalidBranchNames
     */
    public function it_validates_branches_naming(string $branchName, string $message): void
    {
        $factory = new ConfigFactory(
            __DIR__ . '/Fixtures/config/schema_v2_local',
            __DIR__ . '/Fixtures/config/schema_v2_global/config.php',
            $this->createStyle(),
            $this->getGitFileReaderWithNotExistentFile(),
            $this->getGit(),
        );

        try {
            $factory->resolveLocalConfig([
                'schema_version' => 2,
                'branches' => [
                    $branchName => [
                        'split' => [
                            'doc' => [
                                'url' => 'git@github.com:park-manager/doc.git',
                                'sync-tags' => false,
                            ],
                        ],
                    ],
                ],
            ]);

            self::fail('Expected exception to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertEquals('Local configuration contains one or more errors. Invalid configuration for path "hubkit.branches": ' . $message, $e->getMessage());
        }
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function provideInvalidBranchNames(): iterable
    {
        // Any valid branch name, including non versioned
        // Disallow deep references branch-names, HEAD, etc.

        yield 'head ref' => ['head', 'Invalid branch-name or relative pattern "head", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot use Git ref HEAD as branch name.'];
        yield 'HEAD ref' => ['HEAD', 'Invalid branch-name or relative pattern "HEAD", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot use Git ref HEAD as branch name.'];
        yield '#HEAD ref' => ['#HEAD', 'Invalid branch-name or relative pattern "HEAD", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot use Git ref HEAD as branch name.']; // Nice try
        yield 'heads ref' => ['heads/main', 'Invalid branch-name or relative pattern "heads/main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'tags ref' => ['tags/v1.0.0', 'Invalid branch-name or relative pattern "tags/v1.0.0", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'remote ref' => ['remotes/upstream/main', 'Invalid branch-name or relative pattern "remotes/upstream/main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'notes ref' => ['notes/pull-request-metadata', 'Invalid branch-name or relative pattern "notes/pull-request-metadata", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Cannot start with Git refs (heads, tags, remotes, notes)/.'];

        yield 'double dot' => ['feature../main', 'Invalid branch-name or relative pattern "feature../main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double slash' => ['feature//main', 'Invalid branch-name or relative pattern "feature//main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'ends with slash' => ['feature/main/', 'Invalid branch-name or relative pattern "feature/main/", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'space character' => ['feature main', 'Invalid branch-name or relative pattern "feature main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash' => ['feature--main', 'Invalid branch-name or relative pattern "feature--main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash with slash' => ['feature/-main', 'Invalid branch-name or relative pattern "feature/-main", must be either "1.x" or "1.*", or "#1.x" (for an exact branch named 1.x), ":default", any valid branch-name, or a regexp like "/0.[1-9]+/". Error: Invalid name provided, must follow the Git convention for branch names.'];

        yield 'invalid regexp' => ['/[]/', 'Invalid regexp "/[]/" error: "preg_match(): Compilation failed: missing terminating ] for character class at offset 2".'];
        yield 'regexp with options' => ['/\d\.\d+/s', 'Invalid regexp "\/\\\\d\\\\.\\\\d+\/s", cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".'];
        yield 'regexp with anchors' => ['/^\d\.\d+$/', 'Invalid regexp "\/^\\\\d\\\\.\\\\d+$\/", cannot contain start/end anchor or options. Either "/[5-9]\.x/" not "/^[5-9].x$/i".'];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidMainBranches
     */
    public function it_requires_main_branch_is_valid(string $branch, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local configuration contains one or more errors. Invalid configuration for path "hubkit.main_branch": ' . $message);

        $factory = new ConfigFactory(
            __DIR__ . '/Fixtures/config/schema_v2_global',
            __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
            $this->createStyle(),
            $this->getGitFileReaderWithNotExistentFile(),
            $this->getGit(),
        );

        $factory->resolveLocalConfig(['schema_version' => 2, 'main_branch' => $branch]);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function provideInvalidMainBranches(): iterable
    {
        yield 'head ref' => ['head', 'Cannot use Git ref HEAD as branch name.'];
        yield 'HEAD ref' => ['HEAD', 'Cannot use Git ref HEAD as branch name.'];
        yield 'heads ref' => ['heads/main', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'tags ref' => ['tags/v1.0.0', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'remote ref' => ['remotes/upstream/main', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];
        yield 'notes ref' => ['notes/pull-request-metadata', 'Cannot start with Git refs (heads, tags, remotes, notes)/.'];

        yield 'double dot' => ['feature../main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double slash' => ['feature//main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'begins with slash' => ['/feature/main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'ends with slash' => ['feature/main/', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'space character' => ['feature main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash' => ['feature--main', 'Invalid name provided, must follow the Git convention for branch names.'];
        yield 'double-dash with slash' => ['feature/-main', 'Invalid name provided, must follow the Git convention for branch names.'];
    }

    /**
     * @test
     *
     * @dataProvider provideValidMainBranches
     */
    public function it_accepts_valid_main_branch_value(string $branch): void
    {
        $factory = new ConfigFactory(
            __DIR__ . '/Fixtures/config/schema_v2_global',
            __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
            $this->createStyle(),
            $this->getGitFileReaderWithNotExistentFile(),
            $this->getGit(),
        );

        $config = $factory->resolveLocalConfig(['schema_version' => 2, 'main_branch' => $branch]);

        self::assertEquals($branch, $config['main_branch']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function provideValidMainBranches(): iterable
    {
        yield 'main' => ['main'];
        yield 'HEAD-ish' => ['HEADing'];
        yield 'dash' => ['main-master'];
        yield 'underscore' => ['main_master'];
        yield 'versioned' => ['2.0'];
        yield 'relative' => ['2.x'];
        yield 'nested' => ['development/new'];
        yield 'nested deep' => ['development/remotes/new'];
        yield 'unicode' => ["\xCE\xA9"];
    }

    /** @test */
    public function it_accepts_branches_aliasing(): void
    {
        $factory = new ConfigFactory(
            __DIR__ . '/Fixtures/config/schema_v2_global',
            __DIR__ . '/Fixtures/config/schema_v2_global/config2.php',
            $this->createStyle(),
            $this->getGitFileReaderWithNotExistentFile(),
            $this->getGit(),
        );

        $config = $factory->resolveLocalConfig([
            'schema_version' => 2,
            'main_branch' => 'main',
        ]);

        self::assertEquals([
            'schema_version' => 2,
            'main_branch' => 'main',
            'branches' => [],
            'branches_alias' => [],
            'adapter' => 'github',
            'host' => null,
            'repository' => null,
        ], $config);

        $config = $factory->resolveLocalConfig([
            'schema_version' => 2,
            'main_branch' => 'main',
            'branches' => [],
            'branches_alias' => [
                'main' => '2.0',
                'dev/trunk' => '3.0',
            ],
            'adapter' => 'github',
            'host' => null,
            'repository' => null,
        ]);

        self::assertEquals([
            'schema_version' => 2,
            'main_branch' => 'main',
            'branches' => [],
            'branches_alias' => [
                'main' => '2.0-dev',
                'dev/trunk' => '3.0-dev',
            ],
            'adapter' => 'github',
            'host' => null,
            'repository' => null,
        ], $config);
    }

    /** @param array<int, string>|null $versionedBranches */
    private function getGit(?string $expectedBranch = 'main', ?array $versionedBranches = []): Git
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(true);

        $gitProphecy->branchExists(Argument::any())->willReturn(false);

        if ($expectedBranch) {
            $gitProphecy->branchExists($expectedBranch)->willReturn(true);
        }

        if ($versionedBranches) {
            $gitProphecy->getVersionBranches()->willReturn($versionedBranches);
        }

        return $gitProphecy->reveal();
    }

    private function getGitWithoutGitDir(): Git
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(false);

        return $gitProphecy->reveal();
    }

    private function getGitWithActiveExpected(string $branch = 'main'): Git
    {
        $gitProphecy = $this->prophesize(Git::class);
        $gitProphecy->isGitDir()->willReturn(true);
        $gitProphecy->branchExists(Argument::any())->willReturn(false);
        $gitProphecy->getVersionBranches()->willReturn([]);
        $gitProphecy->getActiveBranchName()->willReturn($branch);

        return $gitProphecy->reveal();
    }
}
