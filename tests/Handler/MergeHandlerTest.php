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

use HubKit\Cli\Handler\MergeHandler;
use HubKit\Config;
use HubKit\Helper\BranchAliasResolver;
use HubKit\Helper\SingleLineChoiceQuestionHelper;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use HubKit\Service\SplitshGit;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument as PropArgument;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Console\IO\BufferedIO;

final class MergeHandlerTest extends TestCase
{
    use SymfonyStyleTrait;
    private const PR_NUMBER = 42;
    private const PR_BRANCH = 'feature-something';
    private const HEAD_SHA = '1b04532c8a09d9084abce36f8d9daf675f89eacc';
    private const MERGE_SHA = '52a6bb3aeb7e08e8b641cfa679e4416096bf8439';

    /** @var ObjectProphecy */
    private $git;
    /** @var ObjectProphecy */
    private $aliasResolver;
    /** @var ObjectProphecy */
    private $github;
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
        $this->git->guardWorkingTreeReady()->willReturn(null);

        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');
        $this->github->getAuthUsername()->willReturn('sstok');
        $this->git->getActiveBranchName()->willReturn('master');

        $this->aliasResolver = $this->prophesize(BranchAliasResolver::class);
        $this->aliasResolver->getAlias()->willReturn('1.0-dev');
        $this->aliasResolver->getDetectedBy()->willReturn('composer.json "extra.branch-alias.dev-master"');

        $this->config = new Config([]);
        $this->splitshGit = $this->prophesize(SplitshGit::class);
        $this->splitshGit->checkPrecondition()->shouldNotBeCalled();

        $this->io = new BufferedIO();
        $this->io->setInteractive(true);
    }

    /** @test */
    public function it_merges_a_pull_request_opened_by_merger()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
                'Pull request has been merged.',
                'Pushing notes please wait...',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_merges_a_pull_request_and_closses_issues_if_confirmed()
    {
        $body = '| Q             | A
| ------------- | ---
| Bug fix?      | yes
| New feature?  | no
| BC breaks?    | no
| Deprecations? | no
| Tests pass?   | yes
| Fixed tickets | #56 #600 , #710,#22, #bloop #12
| License       | MIT

It turned-out to me much easier to fix this than expected. When the prefix directory doesn\'t exist ignore the tagging for that repository.';

        $pr = $this->expectPrInfo('sstok', [], 'open', true, $body);
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<BODY
This PR was merged into the 1.0-dev branch.

Discussion
----------

{$body}

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $this->expectIssueProvides(56, '[Release] Fatal error when prefix directory does not exist');
        $this->expectIssueProvides(600, 'Skeletons of Variate', 'closed');
        $this->expectIssueProvides(710, 'Title long this is not');
        $this->expectIssueProvides(22, 'This is the Byte song', 'closed');
        // #bloop and #12 are not expected

        $this->github->closeIssues(56, 710)->shouldBeCalled();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['yes']);

        $this->assertOutputMatches(
            [
                'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
                'Pull request has been merged.',
                'Pushing notes please wait...',
                'Your local "master" branch is updated.',
                'The following issues can be closed after merging this pull request:',
                '* https://github.com/park-manager/park-manager/issues/56 : [Release] Fatal error when prefix directory does not exist',
                '* https://github.com/park-manager/park-manager/issues/710 : Title long this is not',
                'Close them now? (yes/no) [yes]:',
            ]
        );
    }

    /** @test */
    public function it_merges_a_pull_request_and_does_not_closses_issues_if_not_confirmed()
    {
        $body = '| Q             | A
| ------------- | ---
| Bug fix?      | yes
| New feature?  | no
| BC breaks?    | no
| Deprecations? | no
| Tests pass?   | yes
| Fixed tickets | #56 #600 , #710,#22, #bloop #12
| License       | MIT

It turned-out to me much easier to fix this than expected. When the prefix directory doesn\'t exist ignore the tagging for that repository.';

        $pr = $this->expectPrInfo('sstok', [], 'open', true, $body);
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<BODY
This PR was merged into the 1.0-dev branch.

Discussion
----------

{$body}

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $this->expectIssueProvides(56, '[Release] Fatal error when prefix directory does not exist');
        $this->expectIssueProvides(600, 'Skeletons of Variate', 'closed');
        $this->expectIssueProvides(710, 'Title long this is not');
        $this->expectIssueProvides(22, 'This is the Byte song', 'closed');
        // #bloop and #12 are not expected

        $this->github->closeIssues(56, 710)->shouldNotBeCalled();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['no']);

        $this->assertOutputMatches(
            [
                'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
                'Pull request has been merged.',
                'Pushing notes please wait...',
                'Your local "master" branch is updated.',
                'The following issues can be closed after merging this pull request:',
                '* https://github.com/park-manager/park-manager/issues/56 : [Release] Fatal error when prefix directory does not exist',
                '* https://github.com/park-manager/park-manager/issues/710 : Title long this is not',
                'Close them now? (yes/no) [yes]:',
            ]
        );
    }

    /** @test */
    public function it_merges_a_pull_request_and_splits_repository_when_confirmed()
    {
        $this->config = new Config([
            'repos' => [
                'github.com' => [
                    'park-manager/hubkit' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Component/Core' => 'git@github.com:park-manager/core.git',
                            'src/Component/Model' => 'git@github.com:park-manager/model.git',
                            'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                        ],
                    ],
                ],
            ],
        ]);

        $this->splitshGit->checkPrecondition()->shouldBeCalled();
        $this->expectGitSplit('src/Component/Core', '_core', 'git@github.com:park-manager/core.git', '09d103bae644592ebdc10a2665a2791c291fbea7');
        $this->expectGitSplit('src/Component/Model', '_model', 'git@github.com:park-manager/model.git', 'b2faccdde512f226ae67e5e73a9f3259c83b933a', 0);
        $this->expectGitSplit('doc', '_doc', 'git@github.com:park-manager/doc.git', 'c526695a61c698d220f5b2c68ce7b6c689013d55');

        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['yes']);

        $this->assertOutputMatches([
            'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
            'Pull request has been merged.',
            'Pushing notes please wait...',
            'Your local "master" branch is updated.',
            'Split repository now?',
            'Starting split operation please wait...',
            ['3/3 \[[^\]]+\] 100%', true],
        ]);
    }

    /** @test */
    public function it_merges_a_pull_request_and_skips_repository_split_when_confirm_is_rejected()
    {
        $this->config = new Config([
            'repos' => [
                'github.com' => [
                    'park-manager/hubkit' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Component/Core' => 'git@github.com:park-manager/core.git',
                            'src/Component/Model' => 'git@github.com:park-manager/model.git',
                            'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                        ],
                    ],
                ],
            ],
        ]);

        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['no']);

        $this->assertOutputMatches([
            'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
            'Pull request has been merged.',
            'Pushing notes please wait...',
            'Your local "master" branch is updated.',
            'Split repository now?',
        ]);

        $this->assertOutputNotMatches('Starting split operation please wait...');
    }

    /** @test */
    public function it_merges_a_pull_request_and_skips_repository_split_when_local_branch_is_not_ready()
    {
        $this->config = new Config([
            'repos' => [
                'github.com' => [
                    'park-manager/hubkit' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Component/Core' => 'git@github.com:park-manager/core.git',
                            'src/Component/Model' => 'git@github.com:park-manager/model.git',
                            'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                        ],
                    ],
                ],
            ],
        ]);

        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate(false);
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['no']);

        $this->assertOutputMatches([
            'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
            'Pull request has been merged.',
            'Pushing notes please wait...',
            'The Git working tree has uncommitted changes, unable to update your local branch.',
        ]);

        $this->assertOutputNotMatches(['Split repository now?', 'Starting split operation please wait...']);
    }

    /** @test */
    public function it_merges_a_pull_request_and_skips_repository_split_when_local_branch_does_exist()
    {
        $this->config = new Config([
            'repos' => [
                'github.com' => [
                    'park-manager/hubkit' => [
                        'sync-tags' => true,
                        'split' => [
                            'src/Component/Core' => 'git@github.com:park-manager/core.git',
                            'src/Component/Model' => 'git@github.com:park-manager/model.git',
                            'doc' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                        ],
                    ],
                ],
            ],
        ]);

        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate(true, false);
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['no']);

        $this->assertOutputMatches([
            'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
            'Pull request has been merged.',
            'Pushing notes please wait...',
        ]);

        $this->assertOutputNotMatches(['Split repository now?', 'Starting split operation please wait...']);
    }

    /** @test */
    public function it_skips_updating_base_when_missing()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate(true, false);
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches('Pull request has been merged.');
        $this->assertOutputNotMatches('Your local "master" branch is updated.');
    }

    /** @test */
    public function it_skips_updating_base_when_wc_not_ready()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate(false);
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches('Pull request has been merged.');
        $this->assertOutputNotMatches('Your local "master" branch is updated.');
    }

    /** @test */
    public function it_merges_a_pull_request_with_comments()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches(['Pull request has been merged.', 'Your local "master" branch is updated.']);
    }

    /** @test */
    public function it_keeps_local_and_remote_branch_when_not_confirmed()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists(false);

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['no']);

        $this->assertOutputNotMatches('Branch "feature-something" was deleted.');
        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Delete branch "feature-something" (origin and local) (yes/no)',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_cleans_local_and_remote_branch_when_confirmed()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['yes']);

        $this->assertOutputNotMatches('No remote configured for branch "feature-something", skipping deletion.');
        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Delete branch "feature-something" (origin and local) (yes/no)',
                'Branch "feature-something" was deleted.',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_cleans_only_local_branch_when_confirmed_and_no_remote_is_set()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists(true, false);

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['yes']);

        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Delete branch "feature-something" (origin and local) (yes/no)',
                'Branch "feature-something" was deleted.',
                'No remote configured for branch "feature-something", skipping deletion.',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_skips_cleaning_local_and_or_branch_when_not_owned()
    {
        $pr = $this->expectPrInfo('doctor-who');
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists(false, false);

        $this->github->createComment(self::PR_NUMBER, 'Thank you @doctor-who')->shouldBeCalled();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Your local "master" branch is updated.',
            ]
        );

        $this->assertOutputNotMatches(
            [
                'Delete branch "feature-something" (origin and local) (yes/no)',
                'Branch "feature-something" was deleted.',
            ]
        );
    }

    /** @test */
    public function it_squashes_before_merging_when_asked()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was squashed before being merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $args->setOption('squash', true);
        $this->executeHandler($args);

        $this->assertOutputMatches(['Pull request has been merged.', 'Your local "master" branch is updated.']);
    }

    /** @test */
    public function its_merge_subject_contains_all_authors()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr, 'doctor-wo');

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (doctor-wo, sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes(
            [
                ['user' => ['login' => 'someone'], 'created_at' => '2014-11-23T14:39:24Z', 'body' => 'Status: reviewed'],
                ['user' => ['login' => 'who-else'], 'created_at' => '2014-11-23T14:50:24Z', 'body' => ':+1:'],
            ],
            '---------------------------------------------------------------------------

by someone at 2014-11-23T14:39:24Z

Status: reviewed

---------------------------------------------------------------------------

by who-else at 2014-11-23T14:50:24Z

:+1:
');

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            ['Pull request has been merged.', 'Pushing notes please wait...', 'Your local "master" branch is updated.']
        );
    }

    /** @test */
    public function it_merges_a_pull_request_with_pending_status()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus([], 'pending');
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Status checks are pending, merge with caution.',
                'Pull request has been merged.',
                'Pushing notes please wait...',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_shows_status_table_with_warning_for_pending()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus([
            [
                'state' => 'success',
                'context' => 'Scrutinizer',
                'description' => '134 new issues',
            ],
            [
                'state' => 'pending',
                'context' => 'continuous-integration/travis-ci',
                'description' => 'The Travis CI build is in progress',
            ],
        ], '');
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches(
            [
                'Scrutinizer    OK         134 new issues',
                'Travis-ci      Pending    The Travis CI build is in progress',
                'One or more status checks did not complete or failed. Merge with caution.',
                'Pull request has been merged.',
            ]
        );
    }

    /** @test */
    public function it_shows_status_table_with_all_success()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus([
            [
                'state' => 'success',
                'context' => 'Scrutinizer',
                'description' => '134 new issues',
            ],
            [
                'state' => 'success',
                'context' => 'continuous-integration/travis-ci',
                'description' => 'Travis CI build completed',
            ],
        ], '');
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputNotMatches('One or more status checks did not complete or failed. Merge with caution.');
        $this->assertOutputMatches(
            [
                'Scrutinizer    OK    134 new issues',
                'Travis-ci      OK    Travis CI build completed',
                'Pull request has been merged.',
            ]
        );
    }

    /**
     * @test
     * @dataProvider provideStatusLabels
     */
    public function it_shows_status_table_with_review_status(array $labels, bool $success = true, string $row = '')
    {
        $pr = $this->expectPrInfo('sstok', $labels);
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $expected = [];

        if ($row) {
            $expected[] = $row;
        }

        if (!$success) {
            $expected[] = 'One or more status checks did not complete or failed. Merge with caution.';
        } else {
            $this->assertOutputNotMatches('One or more status checks did not complete or failed. Merge with caution.');
        }

        $this->assertOutputMatches($expected);
    }

    public function provideStatusLabels(): array
    {
        return [
            [['Status: ready'], true, 'Reviewed    OK        Status: ready'],
            [['Status: reviewed'], true, 'Reviewed    OK        Status: reviewed'],
            [['Status: reviewed', 'needs work'], true, 'Reviewed    OK        Status: reviewed'],
            [['Status: ready'], true, 'Reviewed    OK        Status: ready'],
            [['status: reviewed'], true, 'Reviewed    OK        status: reviewed'],
            [['status: needs work'], false, 'Reviewed    FAIL        status: needs work'],
            [['status: needs review'], false, 'Reviewed    Pending        status: needs review'],
        ];
    }

    /** @test */
    public function it_includes_info_labels_in_merge_commit_message()
    {
        $pr = $this->expectPrInfo('sstok', ['Bug', 'Deprecation', 'BC Break']);
        $this->expectCommitStatus();
        $this->expectCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was squashed before being merged into the 1.0-dev branch.
labels: deprecation,bc-break

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $args->setOption('squash', true);
        $this->executeHandler($args);

        $this->assertOutputMatches(['Pull request has been merged.', 'Your local "master" branch is updated.']);
    }

    /** @test */
    public function it_checks_pr_is_open()
    {
        $this->expectPrInfo('sstok', [], 'closed');

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Cannot merge closed pull request.');

        $this->executeHandler($args);
    }

    /** @test */
    public function it_checks_commit_messages_contents_and_fails_for_high_severity()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectUnacceptableCommits($pr);

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        try {
            $this->executeHandler($args, 'feature', ['yes']);

            $this->fail('Should have throw an exception.');
        } catch (\InvalidArgumentException $e) {
            self::assertEquals('Please fix the commits contents before continuing.', $e->getMessage());

            $this->assertOutputMatches(
                [
                    '[WARNING] On or more commits are problematic, make sure this is correct.',
                    ' * Description contains unacceptable contents: Crap, I am so...',
                    " * Unrelated commits or work in progress?: OH: PullRequestMergeHandler was already committed\n  Anyway, moved some stuff to a base Handler class",
                ]
            );
        }
    }

    /** @test */
    public function it_checks_commit_messages_contents_rejects_acceptance()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectLessAcceptableCommits($pr);

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        try {
            $this->executeHandler($args, 'feature', ['no']);

            $this->fail('Should have throw an exception.');
        } catch (\InvalidArgumentException $e) {
            self::assertEquals('User aborted. Please fix commits contents before continuing.', $e->getMessage());

            $this->assertOutputMatches(
                [
                    '[WARNING] On or more commits are problematic, make sure this is correct.',
                    " * Unrelated commits or work in progress?: OH: PullRequestMergeHandler was already committed\n  Anyway, moved some stuff to a base Handler class",
                    'Ignore problematic commits and continue anyway?',
                ]
            );
        }
    }

    /** @test */
    public function it_checks_commit_messages_contents_allows_acceptance()
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectLessAcceptableCommits($pr);

        $this->github->mergePullRequest(
            self::PR_NUMBER,
            'feature #42 Brand new design (sstok)',
            PropArgument::exact(<<<'BODY'
This PR was merged into the 1.0-dev branch.

Discussion
----------

There I fixed it

Commits
-------

06f57b45415f0456719d578ca5003f9683b941fb Properly handle repository requirement
06f57b45415f0456719d578ca5003f9683b941fe OH: PullRequestMergeHandler was already committed

BODY
),
            self::HEAD_SHA
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        $this->executeHandler($args, 'feature', ['yes']);
        $this->assertOutputMatches(
            [
                '[WARNING] On or more commits are problematic, make sure this is correct.',
                " * Unrelated commits or work in progress?: OH: PullRequestMergeHandler was already committed\n  Anyway, moved some stuff to a base Handler class",
                'Ignore problematic commits and continue anyway?',
            ]
        );

        $this->assertOutputNotMatches('Please fix the commits contents before continuing.');
    }

    /** @test */
    public function it_checks_pr_is_mergeable()
    {
        $this->expectPrInfo('sstok', [], 'open', null);

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Pull request is not processed yet. Please try again in a few seconds.');

        $this->executeHandler($args);
    }

    /** @test */
    public function it_checks_pr_is_mergeable_with_false()
    {
        $this->expectPrInfo('sstok', [], 'open', false);

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Pull request has conflicts which need to be resolved first.');

        $this->executeHandler($args);
    }

    private function getArgs(): Args
    {
        $format = ArgsFormat::build()
            ->addOption(new Option('squash', null, Option::BOOLEAN))
            ->addOption(new Option('no-pull', null, Option::BOOLEAN))
            ->addOption(new Option('pat', null, Option::OPTIONAL_VALUE | Option::STRING, null, 'Thank you @author'))
            ->addOption(new Option('no-pat', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addArgument(new Argument('number', Argument::REQUIRED | Argument::INTEGER))
            ->getFormat()
        ;

        return new Args($format, new StringArgs(''));
    }

    private function executeHandler(Args $args, string $category = 'feature', array $input = [])
    {
        // XXX Ugly comment: This was the only way to make this work for now, in the feature I plan to
        // use an adapter/replacement for SymfonyStyle where all styling takes place. - Sebastiaan Stok
        $questionHelper = $this->prophesize(SingleLineChoiceQuestionHelper::class);
        $questionHelper->ask(
            PropArgument::any(),
            PropArgument::any(),
            PropArgument::any()
        )->willReturn($category);

        $style = $this->createStyle($input);
        $handler = new MergeHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->aliasResolver->reveal(),
            $questionHelper->reveal(),
            $this->config,
            $this->splitshGit->reveal()
        );

        $handler->handle($args, $this->io);
    }

    private function expectPrInfo(
        string $author = 'sstok',
        array $labels = [],
        string $state = 'open',
        $mergeable = true,
        string $body = 'There I fixed it'
    ): array {
        $number = self::PR_NUMBER;

        $this->github->getPullRequest($number, true)->willReturn(
            $pr = [
                'number' => $number,
                'state' => $state,
                'title' => 'Brand new design',
                'body' => $body,
                'html_url' => 'https://github.com/park-manager/hubkit/pull/'.$number,
                'base' => ['ref' => 'master', 'repo' => ['name' => 'hubkit', 'owner' => ['login' => 'park-manager']]],
                'head' => [
                    'ref' => self::PR_BRANCH,
                    'sha' => self::HEAD_SHA,
                    'user' => ['login' => $author],
                    'repo' => ['name' => 'hubkit'],
                ],
                'mergeable' => $mergeable,
                'user' => ['login' => $author],
                'labels' => array_map(
                    function ($label) {
                        return ['name' => $label, 'color' => '#ffff'];
                    },
                    $labels
                ),
            ]
        );

        return $pr;
    }

    private function expectCommitStatus(array $status = [], string $state = 'success')
    {
        $this->github->getCommitStatuses('park-manager', 'hubkit', self::HEAD_SHA)
            ->willReturn(['state' => $state, 'statuses' => $status]);
    }

    private function expectCommits(array $pr, $author1 = 'sstok', $author2 = 'sstok')
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'].':'.$pr['base']['ref'],
            $pr['head']['ref']
        )->willReturn(
            [
                [
                    'author' => ['login' => $author1],
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fb',
                    'commit' => ['message' => 'Properly handle repository requirement'],
                ],
                [
                    'author' => ['login' => $author2],
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fe',
                    'commit' => [
                        'message' => 'PullRequestMergeHandler was already committed'."\n\n".
                                     'Anyway, moved some stuff to a base Handler class, '.
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectUnacceptableCommits(array $pr)
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'].':'.$pr['base']['ref'],
            $pr['head']['ref']
        )->willReturn(
            [
                [
                    'author' => ['login' => 'sstok'],
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fb',
                    'commit' => ['message' => 'Crap, I am so...'],
                ],
                [
                    'author' => ['login' => 'sstok'],
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fe',
                    'commit' => [
                        'message' => 'OH: PullRequestMergeHandler was already committed'."\n\n".
                                     'Anyway, moved some stuff to a base Handler class, '.
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectLessAcceptableCommits(array $pr)
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'].':'.$pr['base']['ref'],
            $pr['head']['ref']
        )->willReturn(
            [
                [
                    'author' => ['login' => 'sstok'],
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fb',
                    'commit' => ['message' => 'Properly handle repository requirement'],
                ],
                [
                    'author' => ['login' => 'sstok'],
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fe',
                    'commit' => [
                        'message' => 'OH: PullRequestMergeHandler was already committed'."\n\n".
                                     'Anyway, moved some stuff to a base Handler class, '.
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectLocalBranchNotExists()
    {
        $this->git->branchExists(self::PR_BRANCH)->willReturn(false);
    }

    private function expectLocalBranchExists(bool $remove = true, $removeRemote = true)
    {
        $this->git->branchExists(self::PR_BRANCH)->willReturn(true);
        $this->git->getGitConfig('branch.'.self::PR_BRANCH.'.remote')->willReturn($removeRemote ? 'origin' : '');

        if (!$remove) {
            return;
        }

        $this->git->deleteBranch('feature-something', true)->shouldBeCalled();

        if ($removeRemote) {
            $this->git->deleteRemoteBranch('origin', self::PR_BRANCH)->shouldBeCalled();
        }
    }

    private function expectNotes(array $notes = [], string $notesMessage = '')
    {
        $this->github->getComments(self::PR_NUMBER)->willReturn($notes);

        $this->git->ensureNotesFetching('upstream')->shouldBeCalled();
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->addNotes($notesMessage ? PropArgument::containingString($notesMessage) : '', self::MERGE_SHA, 'github-comments')->shouldBeCalled();

        if ('' !== $notesMessage) {
            $this->git->pushToRemote('upstream', 'refs/notes/github-comments')->shouldBeCalled();
        }
    }

    private function expectLocalUpdate(bool $ready = true, bool $branchExists = true)
    {
        $this->git->branchExists('master')->willReturn($branchExists);
        $this->git->isWorkingTreeReady()->willReturn($ready);

        if (!$ready) {
            return;
        }

        if ($branchExists) {
            $this->git->checkout('master')->shouldBeCalled();
            $this->git->pullRemote('upstream', 'master')->shouldBeCalled();
        }
    }

    private function expectGitSplit(string $prefix, string $remote, string $url, string $sha, int $commits = 3): void
    {
        $this->splitshGit->splitTo('master', $prefix, $url)->shouldBeCalled()->willReturn([$remote => [$sha, $url, $commits]]);
    }

    private function expectIssueProvides(int $id, string $title, string $state = 'open'): void
    {
        $this->github->getIssue($id)->willReturn(
            [
                'number' => $id,
                'state' => $state,
                'html_url' => 'https://github.com/park-manager/park-manager/issues/'.$id,
                'title' => $title,
            ]
        );
    }
}
