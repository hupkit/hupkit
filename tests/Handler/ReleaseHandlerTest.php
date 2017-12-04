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

use HubKit\Cli\Handler\ReleaseHandler;
use HubKit\Config;
use HubKit\Service\CliProcess;
use HubKit\Service\Editor;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\SplitshGit;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument as PropArgument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Process\Process;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Console\IO\BufferedIO;

final class ReleaseHandlerTest extends TestCase
{
    use SymfonyStyleTrait;

    private const COMMITS = [
        [
            'sha' => '2bee3b83a9b0073497f37acd4f0920ef61945552',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'feature #93 Introduce a new API for ValuesBag (sstok)',
            'message' => 'This PR was merged into the master branch.

                Discussion
                ----------
            
                |Q            |A  |
                |---          |---|
                |Bug Fix?     |no |
                |New Feature? |yes|
                |BC Breaks?   |no |
                |Deprecations?|yes|
                |Fixed Tickets|   |
                |License      |MIT|
            
                Commits
                -------
            
                1b04532c8a09d9084abce36f8d9daf675f89eacc Introduce a new API for ValuesBag',
        ],
        [
            'sha' => 'd22220c0a97a5fc0ff4e0a0e595247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'minor #56 Clean up (sstok)',
            'message' => 'This PR was merged into the 1.0-dev branch.

                9b67df3871e871084d0ebbf1e0db639d552fc7eb commit 1',
        ],
        [
            'sha' => 'd2222010a97a5fc0ff4e0a0e595247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'feature #55 Great new architecture (sstok, someone)',
            'message' => 'This PR was merged into the 1.0-dev branch.
labels: deprecation , removed-deprecation

9b67df3871e871084d0ebbf1e0db639d552fc7eb commit 1',
        ],
        [
            'sha' => 'd2222010a97a5fc0ff4e0a0e595247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'refactor #52 Removed deprecated API (sstok)',
            'message' => 'This PR was merged into the 1.0-dev branch.
labels: removed-deprecation

9b67df3871e871084d0abef1e0db639d552fc7e commit 2',
        ],
        [
            'sha' => 'd22220c0a97a666fc0ff4e0a0e5247919b89bfa0',
            'author' => 'Sebastiaan Stok <s.stok@rollerscapes.net>',
            'subject' => 'Merge pull request #50 from sstok/docs-cleanup',
            'message' => 'testing #50 Diddly (sstok)',
        ],
    ];

    /** @var ObjectProphecy */
    private $git;
    /** @var ObjectProphecy */
    private $github;
    /** @var ObjectProphecy */
    private $process;
    /** @var ObjectProphecy */
    private $editor;
    /** @var Config */
    private $config;
    /** @var ObjectProphecy */
    private $splitshGit;
    /** @var BufferedIO */
    private $io;

    /** @before */
    public function setUpCommandHandler()
    {
        $this->git = $this->prophesize(Git::class);
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->ensureBranchInSync('upstream', 'master')->willReturn(true);

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');

        $this->process = $this->prophesize(CliProcess::class);
        $this->editor = $this->prophesize(Editor::class);

        $this->config = new Config(
            [
                'repos' => [
                    'github.com' => [
                        'park-manager/park-manager' => [
                            'sync-tags' => true,
                            'split' => [
                                'src/Component/Core' => 'git@github.com:park-manager/core.git',
                                'src/Component/Model' => 'git@github.com:park-manager/model.git',
                                'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->splitshGit = $this->prophesize(SplitshGit::class);
        $this->splitshGit->checkPrecondition()->shouldNotBeCalled();

        $this->io = new BufferedIO();
        $this->io->setInteractive(true);
    }

    /** @test */
    public function it_creates_a_new_release_for_current_branch_without_existing_tags()
    {
        $this->expectTags();
        $this->expectMatchingVersionBranchNotExists();
        $this->expectEditorReturns('Initial release.');

        $url = $this->expectTagAndGitHubRelease('1.0.0', 'Initial release.');

        $args = $this->getArgs('1.0');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Preparing release 1.0.0 (target branch master)',
                'Please wait...',
                'Successfully released 1.0.0',
                $url,
            ]
        );

        $this->assertOutputNotMatches('Starting split operation, please wait...');
    }

    /** @test */
    public function it_creates_a_new_release_for_current_branch_with_existing_tags_and_no_gap()
    {
        $this->expectTags(['0.1.0', '1.0.0-BETA1']);
        $this->git->getLastTagOnBranch()->willReturn('1.0.0-BETA1');
        $this->expectMatchingVersionBranchNotExists();

        $this->git->getLogBetweenCommits('1.0.0-BETA1', 'master')->willReturn(self::COMMITS);

        $this->expectEditorReturns("### Added\n- Introduce a new API for ValuesBag");

        $url = $this->expectTagAndGitHubRelease('1.0.0', "### Added\n- Introduce a new API for ValuesBag");

        $args = $this->getArgs('1.0');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Provided version: 1.0.0',
                'Preparing release 1.0.0 (target branch master)',
                'Please wait...',
                'Successfully released 1.0.0',
                $url,
            ]
        );
    }

    /** @test */
    public function it_asks_confirmation_when_there_is_a_gap_and_continues_when_confirmed()
    {
        $this->expectTags(['0.1.0', '1.0.0-BETA1']);
        $this->git->getLastTagOnBranch()->willReturn('1.0.0-BETA1');
        $this->expectMatchingVersionBranchNotExists('3.0');

        $this->git->getLogBetweenCommits('1.0.0-BETA1', 'master')->willReturn(self::COMMITS);

        $this->expectEditorReturns("### Added\n- Introduce a new API for ValuesBag");

        $url = $this->expectTagAndGitHubRelease('3.0.0', "### Added\n- Introduce a new API for ValuesBag");

        $args = $this->getArgs('3.0');
        $this->executeHandler($args, ['yes']);

        $this->assertOutputMatches(
            [
                'Provided version: 3.0.0',
                'It appears there is gap compared to the last version.',
                'Expected one of : 2.0.0-ALPHA1, 2.0.0-BETA1, 2.0.0',
                'Please confirm your input is correct. (yes/no) [no]:',
                'Preparing release 3.0.0 (target branch master)',
                'Please wait...',
                'Successfully released 3.0.0',
                $url,
            ]
        );
    }

    /** @test */
    public function it_asks_confirmation_when_there_is_a_gap_and_aborts_when_rejected()
    {
        $this->expectTags(['0.1.0', '1.0.0-BETA1']);
        $this->git->getLastTagOnBranch()->willReturn('1.0.0-BETA1');
        $this->expectMatchingVersionBranchNotExists('3.0');

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('User aborted.');

        $args = $this->getArgs('3.0');
        $this->executeHandler($args); // default answer is no
    }

    /** @test */
    public function it_asks_confirmation_when_target_branch_has_a_mismatch_and_continues_when_confirmed()
    {
        $this->git->getActiveBranchName()->willReturn('2.0');

        $this->expectTags(['0.1.0', '1.0.0', '2.0.0']);
        $this->git->getLastTagOnBranch()->willReturn('2.0.0');
        $this->expectMatchingVersionBranchExists('3.0');

        $this->git->ensureBranchInSync('upstream', '2.0')->shouldBeCalled();
        $this->git->getLogBetweenCommits('2.0.0', '2.0')->willReturn(self::COMMITS);

        $this->expectEditorReturns("### Added\n- Introduce a new API for ValuesBag");

        $url = $this->expectTagAndGitHubRelease('3.0.0', "### Added\n- Introduce a new API for ValuesBag");

        $args = $this->getArgs('3.0');
        $this->executeHandler($args, ['yes']);

        $this->assertOutputMatches(
            [
                'Provided version: 3.0.0',
                'This release will be created for the "2.0" branch.',
                'But a branch with version pattern "3.0" exists, did you target the correct branch?',
                'Please confirm your input is correct. (yes/no) [no]:',
                'Preparing release 3.0.0 (target branch 2.0)',
                'Please wait...',
                'Successfully released 3.0.0',
                $url,
            ]
        );
    }

    /** @test */
    public function it_asks_confirmation_when_target_branch_has_a_mismatch_and_aborts_when_rejected()
    {
        $this->git->getActiveBranchName()->willReturn('2.0');
        $this->git->getActiveBranchName()->willReturn('2.0');

        $this->expectTags(['0.1.0', '1.0.0', '2.0.0']);
        $this->git->getLastTagOnBranch()->willReturn('2.0.0');
        $this->expectMatchingVersionBranchExists('3.0');

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('User aborted.');

        $args = $this->getArgs('3.0');
        $this->executeHandler($args); // default answer is no
    }

    /** @test */
    public function it_creates_a_new_release_with_a_relative_version()
    {
        $this->expectTags(['0.1.0', '1.0.0', '2.0.0']);
        $this->git->getLastTagOnBranch()->willReturn('2.5.0');
        $this->expectMatchingVersionBranchNotExists('3.0');

        $this->git->getLogBetweenCommits('2.5.0', 'master')->willReturn(self::COMMITS);

        $this->expectEditorReturns("### Added\n- Introduce a new API for ValuesBag", 'Drop support for older PHP versions');

        $url = $this->expectTagAndGitHubRelease('3.0.0', 'Drop support for older PHP versions');

        $args = $this->getArgs('major');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Provided version: 3.0.0',
                'Preparing release 3.0.0 (target branch master)',
                'Please wait...',
                'Successfully released 3.0.0',
                $url,
            ]
        );
    }

    /** @test */
    public function it_creates_a_new_release_for_split_repositories()
    {
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('park-manager');

        $this->expectTags();
        $this->expectMatchingVersionBranchNotExists();
        $this->expectEditorReturns('Initial release.');

        $this->splitshGit->checkPrecondition()->shouldBeCalled();
        $this->expectGitSplit('src/Component/Core', '_core', 'git@github.com:park-manager/core.git', '09d103bae644592ebdc10a2665a2791c291fbea7');
        $this->expectGitSplit('src/Component/Model', '_model', 'git@github.com:park-manager/model.git', 'b2faccdde512f226ae67e5e73a9f3259c83b933a', 0);
        $this->expectGitSplit('doc', '_doc', 'git@github.com:park-manager/doc.git', 'c526695a61c698d220f5b2c68ce7b6c689013d55');

        $this->splitshGit->syncTags(
            '1.0.0',
            'master',
            [
                '_core' => ['09d103bae644592ebdc10a2665a2791c291fbea7', 'git@github.com:park-manager/core.git', 3],
                '_model' => ['b2faccdde512f226ae67e5e73a9f3259c83b933a', 'git@github.com:park-manager/model.git', 0],
                // doc is ignored because tag synchronization is disabled for this repository
            ]
        )->shouldBeCalled();

        $url = $this->expectTagAndGitHubRelease('1.0.0', 'Initial release.');

        $args = $this->getArgs('1.0');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Preparing release 1.0.0 (target branch master)',
                'Please wait...',
                'Starting split operation please wait...',
                ['3/3 \[[^\]]+\] 100%', true],
                'Successfully released 1.0.0',
                $url,
            ]
        );
    }

    /** @test */
    public function it_creates_a_new_release_with_a_custom_title()
    {
        $this->expectTags(['0.1.0', '1.0.0-BETA1']);
        $this->git->getLastTagOnBranch()->willReturn('1.0.0-BETA1');
        $this->expectMatchingVersionBranchNotExists();

        $this->git->getLogBetweenCommits('1.0.0-BETA1', 'master')->willReturn(self::COMMITS);

        $this->expectEditorReturns("### Added\n- Introduce a new API for ValuesBag");

        $url = $this->expectTagAndGitHubRelease('1.0.0', "### Added\n- Introduce a new API for ValuesBag", 'When Pigs fly');

        $args = $this->getArgs('1.0');
        $args->setOption('title', 'When Pigs fly');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Provided version: 1.0.0',
                'Preparing release 1.0.0 (target branch master)',
                'Please wait...',
                'Successfully released 1.0.0',
                $url,
            ]
        );
    }

    /** @test */
    public function it_fails_when_tag_already_exists()
    {
        $this->expectTags(['v0.1.0', 'v0.2.0', 'v0.3.0', '1.0.0-BETA1', '1.0.0']);
        $this->git->getLastTagOnBranch()->willReturn('1.0.0-BETA1');
        $this->expectMatchingVersionBranchNotExists('3.0');

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Tag for version "v0.1.0" already exists, did you mean: v0.3.1, v0.4.0 ?');

        $args = $this->getArgs('0.1.0');
        $this->executeHandler($args);
    }

    /** @test */
    public function it_fails_when_tag_without_prefix_already_exists()
    {
        $this->expectTags(['0.1.0', '1.0.0-BETA1']);
        $this->git->getLastTagOnBranch()->willReturn('1.0.0-BETA1');
        $this->expectMatchingVersionBranchNotExists('3.0');

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Tag for version "v0.1.0" already exists, did you mean: v0.1.1, v0.2.0, v1.0.0 ?');

        $args = $this->getArgs('0.1.0');
        $this->executeHandler($args);
    }

    private function getArgs(string $version): Args
    {
        $format = ArgsFormat::build()
            ->addOption(new Option('all-categories', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addOption(new Option('no-edit', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addOption(new Option('pre-release', null, Option::BOOLEAN))
            ->addOption(new Option('title', null, Option::REQUIRED_VALUE | Option::NULLABLE | Option::STRING))
            ->addArgument(new Argument('version', Argument::REQUIRED | Argument::STRING))
            ->getFormat()
        ;

        $args = new Args($format, new StringArgs(''));
        $args->setArgument('version', $version);

        return $args;
    }

    private function executeHandler(Args $args, array $input = ['no'])
    {
        $style = $this->createStyle($input);
        $handler = new ReleaseHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->process->reveal(),
            $this->editor->reveal(),
            $this->config,
            $this->splitshGit->reveal()
        );

        $handler->handle($args, $this->io);
    }

    private function expectTags(array $tags = [])
    {
        $process = $this->prophesize(Process::class);
        $process->getOutput()->willReturn(implode("\n", $tags));

        $this->process->mustRun('git tag --list')->willReturn($process->reveal());
    }

    private function expectMatchingVersionBranchExists(string $branch = '1.0')
    {
        $this->git->remoteBranchExists('upstream', $branch)->willReturn(true);
    }

    private function expectMatchingVersionBranchNotExists(string $branch = '1.0')
    {
        $this->git->remoteBranchExists('upstream', $branch)->willReturn(false);
    }

    private function expectTagAndGitHubRelease(string $version, string $message, ?string $title = null): string
    {
        $this->process->mustRun(['git', 'tag', '-s', 'v'.$version, '-m', 'Release '.$version])->shouldBeCalled();
        $this->process->mustRun(['git', 'push', '--tags', 'upstream'])->shouldBeCalled();

        $this->github->createRelease('v'.$version, $message, false, $title)->willReturn(
            ['html_url' => $url = 'https://github.com/park-manager/hubkit/releases/tag/v'.$version]
        );

        return $url;
    }

    private function expectEditorReturns(string $input, string $output = null)
    {
        $this->editor->fromString(PropArgument::containingString($input), true, PropArgument::containingString('Leave file empty to abort operation.'))
            ->willReturn($output ?? $input);
    }

    private function expectGitSplit(string $prefix, string $remote, string $url, string $sha, int $commits = 3): void
    {
        $this->splitshGit->splitTo('master', $prefix, $url)->shouldBeCalled()->willReturn([$remote => [$sha, $url, $commits]]);
    }
}
