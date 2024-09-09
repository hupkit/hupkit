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

use HubKit\RemotesConfigParser;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class RemotesConfigParserTest extends TestCase
{
    /** @test */
    public function it_parses_a_config_file(): void
    {
        self::assertEquals([], RemotesConfigParser::parse(''));
        self::assertEquals([], RemotesConfigParser::parse('; Comment'));
        self::assertEquals([], RemotesConfigParser::parse(';root=origin'));
        self::assertEquals(['main' => 'upstream'], RemotesConfigParser::parse('main=upstream'));
        self::assertEquals(['main' => 'upstream'], RemotesConfigParser::parse('main = upstream'));
        self::assertEquals(['main' => 'upstream'], RemotesConfigParser::parse("main = upstream\n"));
        self::assertEquals(['main' => 'upstream'], RemotesConfigParser::parse("\nmain = upstream\n"));
        self::assertEquals(['main' => 'upstream'], RemotesConfigParser::parse("; Comment \nmain = upstream"));
        self::assertEquals(['main' => 'upstream', 'fork' => 'origin'], RemotesConfigParser::parse("; Comment \nmain = upstream\nfork= origin"));
    }

    /** @test */
    public function it_only_accepts_main_or_fork_variables(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to process file ".hk_remotes" at line 2, declaration foo is not accepted, only "main" or "fork".');

        RemotesConfigParser::parse("\nfoo=bar");
    }

    /** @test */
    public function it_rejects_overwrites(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to process file ".hk_remotes" at line 3, declaration main already set.');

        RemotesConfigParser::parse("\nmain=bar\nmain=foo");
    }

    /** @test */
    public function it_does_not_accept_comments_at_declaration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Unable to process file ".hk_remotes" at line %d, expected declaration (`name=value` or `name=va_lue`), empty line or comment (`; comment`), got: %s', 1, 'foo=bar; Comment'));

        RemotesConfigParser::parse('foo=bar; Comment');
    }
}
