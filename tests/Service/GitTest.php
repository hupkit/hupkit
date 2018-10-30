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

namespace HubKit\Tests\Service;

use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

class GitTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideExpectedVersions
     */
    public function it_gets_versioned_branches_in_correct_order(array $branches, array $expectedVersions)
    {
        self::assertSame($expectedVersions, $this->createGitService($branches)->getVersionBranches('upstream'));
    }

    public function provideExpectedVersions(): array
    {
        return [
            [['master', '1.0', 'v1.1', '2.0', 'x.1'], ['1.0', 'v1.1', '2.0']],
            [['v1.1', 'master', '2.0', '1.0'], ['1.0', 'v1.1', '2.0']],
            [['master', 'feature-1.0', '1.0', 'v1.1', '2.0'], ['1.0', 'v1.1', '2.0']],
            [['master', '1.0', 'v1.1', '2.0', '1.x'], ['1.0', 'v1.1', '1.x', '2.0']],
            [['master', '1.0', 'v1.1', '2.0', 'v1.x'], ['1.0', 'v1.1', 'v1.x', '2.0']],

            'Duplicate version match' => [['master', '1.0', 'v1.0', 'v1.1', '2.0', 'v1.x'], ['1.0', 'v1.0', 'v1.1', 'v1.x', '2.0']],
            'Duplicate version match 2' => [['master', 'v1.0', 'v1.1', '2.0', 'v1.x', '1.0'], ['v1.0', '1.0', 'v1.1', 'v1.x', '2.0']],
        ];
    }

    private function createGitService(array $branches): Git
    {
        $process = $this->prophesize(Process::class);
        $process->getOutput()->willReturn(implode("\n", $branches));

        $processHelper = $this->prophesize(CliProcess::class);
        $processHelper->mustRun(['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/upstream'])->willReturn($process->reveal());

        $filesystem = $this->prophesize(Filesystem::class);
        $style = $this->prophesize(StyleInterface::class);

        return new Git($processHelper->reveal(), $filesystem->reveal(), $style->reveal());
    }
}
