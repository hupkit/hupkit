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

namespace HubKit\Tests\Helper;

use HubKit\Helper\Version;
use HubKit\Helper\VersionsValidator;
use PHPUnit\Framework\TestCase;

class VersionsValidatorTest extends TestCase
{
    public function provideExpectedHighestVersions()
    {
        return [
            'single major range' => [
                [
                    'v1.0.0-beta1',
                    'v1.0.0-beta2',
                    'v1.0.0-beta6',
                    'v1.0.0-beta7',
                    '1.0.0',
                    'v1.0.1',
                    'v1.1.0',
                ],
                [1 => Version::fromString('1.1.0')],
            ],
            'major versions' => [
                [
                    '0.1.0',
                    'v1.0.0-beta1',
                    'v1.0.0-beta2',
                    'v1.0.0-beta6',
                    'v1.0.0-beta7',
                    '1.0.0',
                    'v1.0.1',
                    'v1.1.0',
                    'v2.0.0',
                    'v3.5-beta1',
                ],
                [
                    0 => Version::fromString('0.1.0'),
                    1 => Version::fromString('1.1.0'),
                    2 => Version::fromString('2.0.0'),
                    3 => Version::fromString('3.5.0-beta1'),
                ],
            ],
            'unsupported versions' => [
                [
                    '0.1.0',
                    'v1.0.0-beta1',
                    'v1.0.0-beta2',
                    'snapshot-482848',
                    'v1.0.0-beta6',
                    'v2016-6904-2',
                    'RC-1',
                    'v1.0.0-beta7',
                ],
                [
                    0 => Version::fromString('0.1.0'),
                    1 => Version::fromString('1.0.0-beta7'),
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider provideExpectedHighestVersions
     */
    public function it_finds_highest_versions(array $tags, array $expected)
    {
        self::assertEquals($expected, VersionsValidator::getHighestVersions($tags));
    }
}
