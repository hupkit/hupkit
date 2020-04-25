<?php

namespace HubKit\Tests\Functional\Service;

use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\Service\Git;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Process\Process;

class GitTest extends TestCase
{
    protected function setUp()
    {
        rename(__DIR__ . '/../../Fixtures/git_example_changelog_project/git', __DIR__ . '/../../Fixtures/git_example_changelog_project/.git');
    }

    /**
     * @test
     */
    public function it_returns_all_hubkit_merge_commits()
    {
        $expectedResult = [
            [
                'sha' => '0721910237402965bc6cf88a6aab2d3bb84624ad',
                'author' => 'example-name <name@example.com>',
                'subject' => 'feature #2 Example subject for changelog 2 (username123)',
                'message' => 'Add b.txt',
            ],
            [
                'sha' => '1f9376faffc80c43d64a6b9482359520e168952b',
                'author' => 'example-name <name@example.com>',
                'subject' => 'feature #3 Example subject for changelog 3 (username123)',
                'message' => '',
            ],
        ];

        chdir(__DIR__ . '/../../Fixtures/git_example_changelog_project');

        $cliProcess = new CliProcess(new NullOutput());
        $fileSystemHelper = $this->prophesize(Filesystem::class);
        $style = $this->prophesize(OutputStyle::class);

        $git = new Git($cliProcess, $fileSystemHelper->reveal(), $style->reveal());

        $result = $git->getLogBetweenCommits('dfa12a79fc16af2b763afda1f7b9902a43b37488', 'HEAD');

        $this->assertEquals($expectedResult, $result);
    }

    protected function tearDown()
    {
        rename(__DIR__ . '/../../Fixtures/git_example_changelog_project/.git', __DIR__ . '/../../Fixtures/git_example_changelog_project/git');
    }
}
