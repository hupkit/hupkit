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

namespace HubKit\Tests\Functional\Service\Git;

use HubKit\Service\Git\GitBase;
use HubKit\Tests\Functional\GitTesterTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GitBaseTest extends TestCase
{
    use GitTesterTrait;

    /** @test */
    public function it_returns_true_for_a_git_enabled_directory(): void
    {
        $this->cwd = $this->createGitDirectory($this->getTempDir() . '/git');
        $git = new GitBase($this->getProcessService($this->cwd), $this->cwd);

        self::assertTrue($git->isGitDir());
    }

    /** @test */
    public function it_returns_false_when_not_a_git_directory(): void
    {
        $git = new GitBase($this->getProcessService(sys_get_temp_dir()), sys_get_temp_dir());

        self::assertFalse($git->isGitDir());
    }
}
