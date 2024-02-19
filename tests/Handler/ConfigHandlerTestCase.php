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

use HubKit\Config;
use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use HubKit\Service\Git\GitTempRepository;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Console\IO\BufferedIO;

/**
 * @internal
 */
abstract class ConfigHandlerTestCase extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    protected ObjectProphecy $git;
    protected ObjectProphecy $github;
    protected ObjectProphecy $process;
    protected ObjectProphecy $tempRepository;
    protected ObjectProphecy $filesystem;
    protected Config $config;
    protected BufferedIO $io;

    /** @before */
    public function setUpCommandHandler(): void
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->guardWorkingTreeReady()->will(static function (): void {});
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->getPrimaryBranch()->willReturn('master');

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');
        $this->github->getDefaultBranch()->willReturn('master');

        $this->process = $this->prophesize(CliProcess::class);

        $this->tempRepository = $this->prophesize(GitTempRepository::class);
        $this->filesystem = $this->prophesize(Filesystem::class);

        $this->config = new Config([
            'repositories' => [
                'github.com' => [
                    'repos' => [
                        'park-manager/park-manager' => [
                            'sync-tags' => true,
                            'branches' => [
                                ':default' => [
                                    'split' => [
                                        'src/Component/Core' => ['url' => 'git@github.com:park-manager/core.git'],
                                        'src/Component/Model' => ['url' => 'git@github.com:park-manager/model.git'],
                                        'doc' => [
                                            'url' => 'git@github.com:park-manager/doc.git',
                                            'sync-tags' => false,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->config->setActiveRepository('github.com', 'park-manager/park-manager');

        $this->io = new BufferedIO();
        $this->io->setInteractive(true);
    }

    protected function getArgs(): Args
    {
        return new Args(ArgsFormat::build()->getFormat(), new StringArgs(''));
    }
}
