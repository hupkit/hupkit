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

use HubKit\Cli\Handler\BranchAliasHandler;
use HubKit\Service\Git;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\IO\BufferedIO;

/**
 * @internal
 */
final class BranchAliasHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy */
    private $git;
    /** @var BufferedIO */
    private $io;

    /** @before */
    public function setUpCommandHandler(): void
    {
        $this->git = $this->prophesize(Git::class);
        $this->io = new BufferedIO();
    }

    /** @test */
    public function it_sets_a_new_alias(): void
    {
        $this->git->getGitConfig('branch.master.alias')->willReturn('1.0-dev');
        $this->git->setGitConfig('branch.master.alias', '1.2-dev', true)->shouldBeCalled();
        $this->executeHandler('1.2');

        self::assertEquals('1.2-dev', trim($this->io->fetchOutput()));
    }

    /** @test */
    public function it_gets_current_alias(): void
    {
        $this->git->getGitConfig('branch.master.alias')->willReturn('1.5-dev');
        $this->executeHandler();

        self::assertEquals('1.5-dev', trim($this->io->fetchOutput()));
    }

    /** @test */
    public function it_requires_alias_in_a_specific_format(): void
    {
        $this->git->getGitConfig('branch.master.alias')->willReturn('1.0-dev');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('A branch alias consists of major and minor version');

        $this->executeHandler('v1.2');
    }

    private function executeHandler(string $alias = null): void
    {
        $format = ArgsFormat::build()
            ->addArgument(new Argument('alias', Argument::OPTIONAL | Argument::STRING))
            ->getFormat()
        ;

        $args = new Args($format);

        if ($alias) {
            $args->setArgument('alias', $alias);
        }

        $handler = new BranchAliasHandler($this->git->reveal());
        $handler->handle($args, $this->io);
    }
}
