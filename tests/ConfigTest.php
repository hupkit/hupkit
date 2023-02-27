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
}
