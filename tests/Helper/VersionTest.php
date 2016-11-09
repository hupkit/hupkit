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
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    /** @test */
    public function it_creates_from_full_string()
    {
        $version = Version::fromString('1.0.0-beta-5');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(1, $version->stability);
        self::assertEquals(5, $version->metaver);

        self::assertEquals('1.0.0-BETA5', (string) $version);
    }

    /** @test */
    public function it_creates_with_explicit_stable()
    {
        $version = Version::fromString('1.0.0-stable');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(3, $version->stability);
        self::assertEquals(0, $version->metaver);

        self::assertEquals('1.0.0', (string) $version);
    }

    /** @test */
    public function it_creates_without_patch()
    {
        $version = Version::fromString('1.0');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(3, $version->stability);
        self::assertEquals(0, $version->metaver);

        self::assertEquals('1.0.0', (string) $version);
    }

    public function provideValidFormats(): array
    {
        return [
            'with prefix in lowercase' => ['v1.0.0', '1.0.0'],
            'with prefix in uppercase' => ['V1.0.0', '1.0.0'],
            'stability with hyphen' => ['1.0.0-alpha-1', '1.0.0-ALPHA1'],
            'stability with dot' => ['1.0.0-alpha.1', '1.0.0-ALPHA1'],
            'stability without patch' => ['1.0-alpha.1', '1.0.0-ALPHA1'],
            'beta' => ['1.0.0-beta1', '1.0.0-BETA1'],
            'RC' => ['1.0.0-RC1', '1.0.0-RC1'],
        ];
    }

    /**
     * @test
     * @dataProvider provideValidFormats
     */
    public function it_supports_various_formats(string $version, string $expectedOutput)
    {
        $version = Version::fromString($version);

        self::assertEquals($expectedOutput, (string) $version);
    }

    /** @test */
    public function it_compares_two_versions_are_equal()
    {
        $version = Version::fromString('1.0.0-beta-5');
        $version2 = Version::fromString('1.0.0-beta5');
        $version3 = Version::fromString('1.0.0-beta6');

        self::assertTrue($version->equalTo($version2));
        self::assertFalse($version->equalTo($version3));
    }

    /** @test */
    public function it_fails_for_invalid_format()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Unable to parse version "1.0.0-WAT"');

        Version::fromString('1.0.0-WAT');
    }

    /** @test */
    public function it_fails_with_stable_plus_metaver()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Meta version of the stability flag cannot be set for stable.');

        Version::fromString('1.0.0-stable-5');
    }

        public function provideExpectedNextVersionCandidates(): array
    {
        return [
            'alpha 0' => ['0.1.0', ['0.1.1', '0.2.0', '1.0.0-BETA1', '1.0.0']],
            'beta' => ['1.0.0-beta-5', ['1.0.0-BETA6', '1.0.0-RC1', '1.0.0']],
            'rc' => ['1.0.0-RC5', ['1.0.0-RC6', '1.0.0']],
            'stable major' => ['1.0.0', ['1.0.1', '1.1.0-BETA1', '1.1.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']],
            'stable with minor' => ['1.1.0', ['1.1.1', '1.2.0-BETA1', '1.2.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']],
            'stable with minor and patch' => ['1.1.1', ['1.1.2', '1.2.0-BETA1', '1.2.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']],
            'stable with patch' => ['1.0.1', ['1.0.2', '1.1.0-BETA1', '1.1.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']],
        ];
    }

    /**
     * @test
     * @dataProvider provideExpectedNextVersionCandidates
     */
    public function it_provides_next_version_candidates($current, $expected)
    {
        $candidates = Version::fromString($current)->getNextVersionCandidates();
        $expected = array_map([Version::class, 'fromString'], $expected);

        self::assertEquals($expected, $candidates);
    }
}
