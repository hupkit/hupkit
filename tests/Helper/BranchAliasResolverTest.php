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

namespace HubKit\Tests\Helper;

use HubKit\Config;
use HubKit\Helper\BranchAliasResolver;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Tests\Functional\GitTesterTrait;
use HubKit\Tests\Handler\SymfonyStyleTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
final class BranchAliasResolverTest extends TestCase
{
    use GitTesterTrait;
    use ProphecyTrait;
    use SymfonyStyleTrait;

    /** @test */
    public function it_resolves_from_composer_file(): void
    {
        $style = $this->createStyle(['3.0']);
        $git = $this->prophesize(Git::class);

        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->fileExists('./composer.json')->willReturn(true);
        $filesystem->getFileContents('./composer.json')->willReturn(
            '{
                "extra": {
                    "branch-alias": {
                        "dev-master": "1.3-dev"
                    }
                }
            }'
        );

        $config = new Config([]);
        $config->setActiveRepository('github.com', 'hupkit/hupkit');

        $resolver = new BranchAliasResolver($filesystem->reveal(), $style, $git->reveal(), $config);

        self::assertEquals('1.3-dev', $resolver->getAlias('master'));
        self::assertEquals('composer.json "extra.branch-alias.dev-master"', $resolver->getDetectedBy());
    }

    /** @test */
    public function it_resolves_by_local_config(): void
    {
        $style = $this->createStyle(['3.0']);
        $git = $this->prophesize(Git::class);

        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->fileExists('./composer.json')->willReturn(false);

        $config = new Config(['_local' => ['branches_alias' => ['master' => '1.3-dev']]]);
        $config->setActiveRepository('github.com', 'hupkit/hupkit');

        $resolver = new BranchAliasResolver($filesystem->reveal(), $style, $git->reveal(), $config);

        self::assertEquals('1.3-dev', $resolver->getAlias('master'));
        self::assertEquals('configuration [branches_alias][master]', $resolver->getDetectedBy());
    }

    /** @test */
    public function it_resolves_by_git_config(): void
    {
        $this->cwd = $this->createGitDirectory($this->getTempDir() . '/git-alias-resolve');
        $style = $this->createStyle(['3.0']);

        $filesystemProphecy = $this->prophesize(Filesystem::class);
        $filesystemProphecy->fileExists('./composer.json')->willReturn(false);
        $filesystem = $filesystemProphecy->reveal();

        $git = new Git($this->getProcessService(), $filesystem, $style);
        $git->setGitConfig('branch.master.alias', '1.3-dev', true);

        $config = new Config([]);
        $config->setActiveRepository('github.com', 'hupkit/hupkit');

        $resolver = new BranchAliasResolver($filesystem, $style, $git, $config);

        self::assertEquals('1.3-dev', $resolver->getAlias('master'));
        self::assertEquals('Git config "branch.master.alias"', $resolver->getDetectedBy());

        $this->assertOutputMatches([
            'Usage of Git config "branch.master.alias" is deprecated and will be removed in v2.0. Add either an "extra.branch-alias.dev-master" in composer.json or add branches_alias.master to the repository local configuration.',
        ]);
    }

    /** @test */
    public function it_resolves_by_asking_and_storing(): void
    {
        $this->cwd = $this->createGitDirectory($this->getTempDir() . '/git-alias-resolve');
        $style = $this->createStyle(['3.0']);

        $filesystemProphecy = $this->prophesize(Filesystem::class);
        $filesystemProphecy->fileExists('./composer.json')->willReturn(false);
        $filesystem = $filesystemProphecy->reveal();

        $git = new Git($this->getProcessService(), $filesystem, $style);

        $config = new Config([]);
        $config->setActiveRepository('github.com', 'hupkit/hupkit');

        $resolver = new BranchAliasResolver($filesystem, $style, $git, $config);

        self::assertEquals('3.0-dev', $resolver->getAlias('master'));
        self::assertEquals('Git config "branch.master.alias"', $resolver->getDetectedBy());

        $this->assertOutputMatches([
            'Usage of Git config "branch.master.alias" is deprecated and will be removed in v2.0. Add either an "extra.branch-alias.dev-master" in composer.json or add branches_alias.master to the repository local configuration.',

            'No branch-alias found for "master", please provide an alias.',
            'Branch-alias is stored for feature reference.',
        ]);
    }
}
