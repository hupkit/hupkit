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

namespace HubKit\Tests\Handler;

use HubKit\Cli\Handler\CleanupUnmaintainedHandler;
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
final class CleanupUnmaintainedHandlerTest extends TestCase
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
            '_local' => [
                'branches' => [
                    ':default' => [
                        'split' => 'changed-only',
                        'maintained' => false, // This is not even a valid config option, but we ignore this branch
                    ],
                    'master' => [
                        'maintained' => true,
                    ],
                    '2.0' => [
                        'maintained' => true,
                    ],
                    '1.5' => [
                        'maintained' => false,
                    ],
                    '1.0' => [
                        'maintained' => false,
                    ],
                ],
            ],
        ]);
        $this->config->setActiveRepository('github.com', 'hubkit-sandbox/empire');
    }

    /** @test */
    public function does_nothing_when_no_unmaintained_branches_found(): void
    {
        $this->git->branchExists('master')->willReturn(true);
        $this->git->branchExists('2.0')->willReturn(true);
        $this->git->branchExists(Argument::any())->willReturn(false);

        $this->executeHandler();

        $this->assertOutputMatches('No unmaintained branches found.');
    }

    /** @test */
    public function removes_unmaintained_branches(): void
    {
        $this->git->branchExists('master')->willReturn(true);
        $this->git->branchExists('2.0')->willReturn(true);
        $this->git->branchExists('1.5')->willReturn(true);
        $this->git->branchExists('1.0')->willReturn(true);
        $this->git->deleteBranchWithForce('1.0')->shouldBeCalled();
        $this->git->deleteBranchWithForce('1.5')->shouldBeCalled();

        $this->executeHandler();

        $this->assertOutputMatches([
            'The following local branches are unmaintained and can be deleted.',
            ' * 1.0',
            ' * 1.5',
            'Delete these local branches now?',
            'Deleting branch "1.0"...',
            'Deleting branch "1.5"...',
        ]);
        $this->assertOutputNotMatches(['Deleting branch "2.0"...']);
    }

    /** @test */
    public function keeps_unmaintained_branches_when_aborted(): void
    {
        $this->git->branchExists('master')->willReturn(true);
        $this->git->branchExists('2.0')->willReturn(true);
        $this->git->branchExists('1.5')->willReturn(true);
        $this->git->branchExists('1.0')->willReturn(true);
        $this->git->deleteBranchWithForce('1.0')->shouldNotBeCalled();
        $this->git->deleteBranchWithForce('1.5')->shouldNotBeCalled();

        try {
            $this->executeHandler(false);
        } catch (\RuntimeException $e) {
            self::assertSame('User aborted.', $e->getMessage());
        }

        $this->assertOutputMatches([
            'The following local branches are unmaintained and can be deleted.',
            ' * 1.0',
            ' * 1.5',
            'Delete these local branches now?',
        ]);
    }

    private function getArgs(): Args
    {
        return new Args(ArgsFormat::build()->getFormat(), new StringArgs(''));
    }

    private function executeHandler(bool $confirm = true): void
    {
        $style = $this->createStyle($confirm ? ['yes'] : ['no']);
        $handler = new CleanupUnmaintainedHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
        );

        $handler->handle($this->getArgs());
    }
}
