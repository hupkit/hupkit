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
use HubKit\Service\BranchSplitsh;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument as PropArgument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Args\StringArgs;
use Webmozart\Console\IO\BufferedIO;

/**
 * @internal
 */
final class MergeHandlerTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    private const PR_NUMBER = 42;
    private const PR_BRANCH = 'feature-something';
    private const HEAD_SHA = '1b04532c8a09d9084abce36f8d9daf675f89eacc';
    private const MERGE_SHA = '52a6bb3aeb7e08e8b641cfa679e4416096bf8439';

    private ObjectProphecy $git;
    private ObjectProphecy $aliasResolver;
    private ObjectProphecy $github;
    private ObjectProphecy $branchSplitsh;
    private BufferedIO $io;
    private Config $config;

    /** @before */
    public function setUpCommandHandler(): void
    {
        $this->github = $this->prophesize(GitHub::class);
        $this->github->getHostname()->willReturn('github.com');
        $this->github->getOrganization()->willReturn('park-manager');
        $this->github->getRepository()->willReturn('hubkit');
        $this->github->getAuthUsername()->willReturn('sstok');
        $this->expectCommitCount(1);

        $this->git = $this->prophesize(Git::class);
        $this->git->getActiveBranchName()->willReturn('master');
        $this->git->getPrimaryBranch()->willReturn('master');

        $this->aliasResolver = $this->prophesize(BranchAliasResolver::class);
        $this->aliasResolver->getAlias()->willReturn('1.0-dev');
        $this->aliasResolver->getDetectedBy()->willReturn('composer.json "extra.branch-alias.dev-master"');

        $this->config = new Config([]);

        $this->branchSplitsh = $this->prophesize(BranchSplitsh::class);
        $this->branchSplitsh->splitBranch('master')->shouldBeCalled();

        $this->io = new BufferedIO();
        $this->io->setInteractive(true);
    }

    /** @test */
    public function it_merges_a_pull_request_opened_by_merger(): void
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
            self::HEAD_SHA,
            false
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
                'Status checks are pending, merge with caution.',
                'master branch is aliased as 1.0-dev (detected by composer.json "extra.branch-alias.dev-master")',
                'Pull request has been merged.',
                'Pushing notes please wait...',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_shows_ci_information(): void
    {
        $pr = $this->expectPrInfo();
        $this->expectCommits($pr);
        $this->expectNotes();
        $this->expectCommitStatus([
            ['description' => 'Run tests', 'state' => 'success', 'context' => 'github/ci-tests-PHP6'],
            ['description' => 'Lower button must not be buttoned', 'state' => 'failure', 'context' => 'Gentlemans-gazette'],
        ]);

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
            self::HEAD_SHA,
            false
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputNotMatches('Status checks are pending, merge with caution.');

        self::assertMatchesRegularExpression(
            <<<'TABLE'
                {
                ---------------------------------------------------------------------
                \s+Item\s+Status\h+Details\h+
                ---------------------------------------------------------------------
                \h+Ci-tests-PHP6\h+OK\h+Run\h+tests\h+
                \h+Gentlemans-gazette\h+FAIL\h+Lower\h+button\h+must\h+not\h+be\h+buttoned\h+
                \h+test\h+run\h+OK\h+Extra\h+info\h+
                ---------------------------------------------------------------------
                }
                TABLE,
            $this->getDisplay()
        );
    }

    /** @test */
    public function it_merges_a_pull_request_and_skips_repository_split_when_local_branch_is_not_ready(): void
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
        $this->expectNoSplits();

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
            self::HEAD_SHA,
            false
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
    }

    /** @test */
    public function it_skips_updating_base_when_missing(): void
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
            self::HEAD_SHA,
            false
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate(true, false);
        $this->expectLocalBranchNotExists();
        $this->expectNoSplits();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args);

        $this->assertOutputMatches('Pull request has been merged.');
        $this->assertOutputNotMatches('Your local "master" branch is updated.');
    }

    /** @test */
    public function it_skips_updating_base_when_wc_not_ready(): void
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);
        $this->expectNoSplits();

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
            self::HEAD_SHA,
            false
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
    public function it_merges_a_pull_request_with_comments(): void
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
            self::HEAD_SHA,
            false
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
    public function it_keeps_local_and_remote_branch_when_auto_clean_disabled(): void
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
            self::HEAD_SHA,
            false
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists(false);

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $args->setOption('no-cleanup', true);
        $this->executeHandler($args, 'feature');

        $this->assertOutputNotMatches('Branch "feature-something" was deleted.');
        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_cleans_local_and_remote_branch(): void
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
            self::HEAD_SHA,
            false
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature');

        $this->assertOutputNotMatches('No remote configured for branch "feature-something", skipping deletion.');
        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Branch "feature-something" was deleted.',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_cleans_only_local_branch_when_confirmed_and_no_remote_is_set(): void
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
            self::HEAD_SHA,
            false
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchExists(true, false);

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature');

        $this->assertOutputMatches(
            [
                'Pull request has been merged.',
                'Branch "feature-something" was deleted.',
                'No remote configured for branch "feature-something", skipping deletion.',
                'Your local "master" branch is updated.',
            ]
        );
    }

    /** @test */
    public function it_skips_cleaning_local_and_or_branch_when_not_owned(): void
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
            self::HEAD_SHA,
            false
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
    public function it_squashes_before_merging_when_asked(): void
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
            self::HEAD_SHA,
            true
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

    /**
     * @test
     */
    public function it_asks_for_squash_with_multiple_commits_before_merging_when_asked(): void
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommits($pr);
        $this->expectCommitCount(2);

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
            self::HEAD_SHA,
            true
        )->willReturn(['sha' => self::MERGE_SHA]);

        $this->expectNotes();
        $this->expectLocalUpdate();
        $this->expectLocalBranchNotExists();

        $args = $this->getArgs();
        $args->setArgument('number', '42');
        $this->executeHandler($args, 'feature', ['yes']);

        $this->assertOutputMatches(['Pull request has been merged.', 'Your local "master" branch is updated.']);
    }

    /** @test */
    public function its_merge_subject_contains_all_authors(): void
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
            self::HEAD_SHA,
            false
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
    public function its_merge_subject_has_correct_author_when_commit_has_no_author(): void
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectCommitsWithoutAuthor($pr);

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
            self::HEAD_SHA,
            false
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
    public function it_merges_a_pull_request_with_pending_status(): void
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
            self::HEAD_SHA,
            false
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
    public function it_shows_status_table_with_warning_for_pending(): void
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
            self::HEAD_SHA,
            false
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
    public function it_shows_status_table_with_all_success(): void
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
            self::HEAD_SHA,
            false
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
     *
     * @dataProvider provideStatusLabels
     */
    public function it_shows_status_table_with_review_status(array $labels, bool $success = true, string $row = ''): void
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
            self::HEAD_SHA,
            false
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

        if (! $success) {
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
    public function it_includes_info_labels_in_merge_commit_message(): void
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
            self::HEAD_SHA,
            true
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
    public function it_checks_pr_is_open(): void
    {
        $this->expectPrInfo('sstok', [], 'closed');
        $this->expectNoSplits();

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Cannot merge closed pull request.');

        $this->executeHandler($args);
    }

    /** @test */
    public function it_checks_commit_messages_contents_and_fails_for_high_severity(): void
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectNoSplits();
        $this->expectUnacceptableCommits($pr);

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        try {
            $this->executeHandler($args, 'feature', ['yes']);

            self::fail('Should have throw an exception.');
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
    public function it_checks_commit_messages_contents_rejects_acceptance(): void
    {
        $pr = $this->expectPrInfo();
        $this->expectCommitStatus();
        $this->expectNoSplits();
        $this->expectLessAcceptableCommits($pr);

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        try {
            $this->executeHandler($args, 'feature', ['no']);

            self::fail('Should have throw an exception.');
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
    public function it_checks_commit_messages_contents_allows_acceptance(): void
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
            self::HEAD_SHA,
            false
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
    public function it_checks_pr_is_mergeable(): void
    {
        $this->expectPrInfo('sstok', [], 'open', null);
        $this->expectNoSplits();

        $args = $this->getArgs();
        $args->setArgument('number', '42');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Pull request is not processed yet. Please try again in a few seconds.');

        $this->executeHandler($args);
    }

    /** @test */
    public function it_checks_pr_is_mergeable_with_false(): void
    {
        $this->expectPrInfo('sstok', [], 'open', false);
        $this->expectNoSplits();

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
            ->addOption(new Option('no-split', null, Option::BOOLEAN))
            ->addOption(new Option('pat', null, Option::OPTIONAL_VALUE | Option::STRING, null, 'Thank you @author'))
            ->addOption(new Option('no-pat', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addOption(new Option('no-cleanup', null, Option::NO_VALUE | Option::BOOLEAN))
            ->addArgument(new Argument('number', Argument::REQUIRED | Argument::INTEGER))
            ->getFormat()
        ;

        return new Args($format, new StringArgs(''));
    }

    private function executeHandler(Args $args, string $category = 'feature', array $input = []): void
    {
        // XXX Ugly comment: This was the only way to make this work for now, in the feature I plan to
        // use an adapter/replacement for SymfonyStyle where all styling takes place. - Sebastiaan Stok
        $questionHelper = $this->prophesize(SingleLineChoiceQuestionHelper::class);
        $questionHelper->ask(
            PropArgument::any(),
            PropArgument::any(),
            PropArgument::any()
        )->will(static function ($args) use ($input, $category) {
            if ($args[2] instanceof ConfirmationQuestion) {
                return array_pop($input) === 'yes';
            }

            return $category;
        });

        $style = $this->createStyle($input);
        $handler = new MergeHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
            $this->aliasResolver->reveal(),
            $questionHelper->reveal(),
            $this->branchSplitsh->reveal()
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
                'html_url' => 'https://github.com/park-manager/hubkit/pull/' . $number,
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
                    static fn ($label) => ['name' => $label, 'color' => '#ffff'],
                    $labels
                ),
            ]
        );

        return $pr;
    }

    private function expectCommitStatus(array $statuses = [], string $state = 'success'): void
    {
        $this->github->getCommitStatuses('park-manager', 'hubkit', self::HEAD_SHA)
            ->willReturn(['state' => $state, 'statuses' => $statuses])
        ;
        $this->github->getCheckSuitesForReference('park-manager', 'hubkit', self::HEAD_SHA)
            ->willReturn(['check_suites' => [['id' => 1, 'status' => $state]]])
        ;
        $this->github->getCheckRunsForCheckSuite('park-manager', 'hubkit', 1)
            ->willReturn(['check_runs' => [['name' => 'test run', 'conclusion' => 'success', 'output' => ['title' => 'Extra info']]]])
        ;
    }

    private function expectCommits(array $pr, string $author1 = 'sstok', string $author2 = 'sstok'): void
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'] . ':' . $pr['base']['ref'],
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
                        'message' => 'PullRequestMergeHandler was already committed' . "\n\n" .
                                     'Anyway, moved some stuff to a base Handler class, ' .
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectCommitsWithoutAuthor(array $pr): void
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'] . ':' . $pr['base']['ref'],
            $pr['head']['ref']
        )->willReturn(
            [
                [
                    'author' => null,
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fb',
                    'commit' => ['message' => 'Properly handle repository requirement'],
                ],
                [
                    'author' => null,
                    'sha' => '06f57b45415f0456719d578ca5003f9683b941fe',
                    'commit' => [
                        'message' => 'PullRequestMergeHandler was already committed' . "\n\n" .
                                     'Anyway, moved some stuff to a base Handler class, ' .
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectUnacceptableCommits(array $pr): void
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'] . ':' . $pr['base']['ref'],
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
                        'message' => 'OH: PullRequestMergeHandler was already committed' . "\n\n" .
                                     'Anyway, moved some stuff to a base Handler class, ' .
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectLessAcceptableCommits(array $pr): void
    {
        $this->github->getCommits(
            $pr['head']['user']['login'],
            $pr['head']['repo']['name'],
            $pr['base']['repo']['owner']['login'] . ':' . $pr['base']['ref'],
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
                        'message' => 'OH: PullRequestMergeHandler was already committed' . "\n\n" .
                                     'Anyway, moved some stuff to a base Handler class, ' .
                                     'review status (if any) and fixes for broken command',
                    ],
                ],
            ]
        );
    }

    private function expectLocalBranchNotExists(): void
    {
        $this->git->branchExists(self::PR_BRANCH)->willReturn(false);
    }

    private function expectLocalBranchExists(bool $remove = true, $removeRemote = true): void
    {
        $this->git->branchExists(self::PR_BRANCH)->willReturn(true);
        $this->git->getGitConfig('branch.' . self::PR_BRANCH . '.remote')->willReturn($removeRemote ? 'origin' : '');

        if (! $remove) {
            return;
        }

        $this->git->deleteBranch('feature-something', true)->shouldBeCalled();

        if ($removeRemote) {
            $this->git->deleteRemoteBranch('origin', self::PR_BRANCH)->shouldBeCalled();
        }
    }

    private function expectNotes(array $notes = [], string $notesMessage = ''): void
    {
        $this->github->getComments(self::PR_NUMBER)->willReturn($notes);

        $this->git->ensureNotesFetching('upstream')->shouldBeCalled();
        $this->git->remoteUpdate('upstream')->shouldBeCalled();
        $this->git->addNotes($notesMessage ? PropArgument::containingString($notesMessage) : '', self::MERGE_SHA, 'github-comments')->shouldBeCalled();

        if ($notesMessage !== '') {
            $this->git->pushToRemote('upstream', 'refs/notes/github-comments')->shouldBeCalled();
        }
    }

    private function expectLocalUpdate(bool $ready = true, bool $branchExists = true): void
    {
        $this->git->branchExists('master')->willReturn($branchExists);
        $this->git->isWorkingTreeReady()->willReturn($ready);

        if (! $ready) {
            return;
        }

        if ($branchExists) {
            $this->git->checkout('master')->shouldBeCalled();
            $this->git->pullRemote('upstream', 'master')->shouldBeCalled();
        }
    }

    private function expectCommitCount(int $count): void
    {
        $this->github->getPullrequestCommitCount(self::PR_NUMBER)->willReturn($count);
    }

    private function expectNoSplits(): void
    {
        $this->branchSplitsh->splitBranch('master')->shouldNotBeCalled();
    }
}
