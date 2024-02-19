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

use HubKit\Cli\Handler\CheckoutHandler;
use HubKit\Config;
use HubKit\Service\CliProcess;
use HubKit\Service\Git;
use HubKit\Service\GitHub;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\Args\Format\ArgsFormat;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Args\StringArgs;

/**
 * @internal
 */
final class CheckoutHandlerTest extends TestCase
{
    use ProphecyTrait;
    use SymfonyStyleTrait;

    private ObjectProphecy $git;
    private ObjectProphecy $github;
    private ObjectProphecy $process;
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

        $this->process = $this->prophesize(CliProcess::class);

        $this->config = new Config([
            'schema_version' => 2,
            'github' => [
                'github.com' => [
                    'username' => 'sstok',
                    'api_token' => 'CHANGE-ME',
                ],
            ],
        ]);
        $this->config->setActiveRepository('github.com', 'hubkit-sandbox/empire');
    }

    /** @test */
    public function it_errors_when_pull_request_is_closed(): void
    {
        $this->github->getPullRequest(12)->willReturn([
            'state' => 'closed',
            'html_url' => 'https://github.com/RandallCox/hubkit-empire/pull/12',
            'head' => [
                'ref' => 'bug/broken-plain',
                'repo' => [
                    'ssh_url' => 'git@github.com:RandallCox/hubkit-empire.git',
                ],
                'user' => [
                    'login' => 'RandallCox',
                ],
            ],
        ]);

        $this->expectExceptionObject(new \InvalidArgumentException('Cannot checkout closed/merged pull-request.'));

        $this->executeHandler(12);
    }

    /** @test */
    public function it_checksout_a_pull_request(): void
    {
        $this->github->getPullRequest(12)->willReturn([
            'state' => 'open',
            'html_url' => 'https://github.com/RandallCox/hubkit-empire/pull/12',
            'head' => [
                'ref' => 'bug/broken-plain',
                'repo' => [
                    'ssh_url' => 'git@github.com:RandallCox/hubkit-empire.git',
                ],
                'user' => [
                    'login' => 'RandallCox',
                ],
            ],
        ]);

        $this->git->guardWorkingTreeReady()->shouldBeCalled();
        $this->git->ensureRemoteExists('RandallCox', 'git@github.com:RandallCox/hubkit-empire.git')->shouldBeCalled();
        $this->git->remoteUpdate('RandallCox')->shouldBeCalled();

        $this->git->branchExists('RandallCox--bug/broken-plain')->willReturn(false);
        $this->git->checkoutRemoteBranch('RandallCox', 'bug/broken-plain', false)->shouldBeCalled();
        $this->git->checkout('RandallCox--bug/broken-plain', true)->shouldBeCalled();

        $this->process->run(['git', 'branch', '--set-upstream-to', 'RandallCox/bug/broken-plain', 'RandallCox--bug/broken-plain'])->shouldBeCalled();

        $this->executeHandler(12);

        $this->assertOutputMatches('Pull request https://github.com/RandallCox/hubkit-empire/pull/12 is checked out!');
    }

    /** @test */
    public function it_checksout_a_pull_request_with_existing_branch(): void
    {
        $this->github->getPullRequest(14)->willReturn([
            'state' => 'open',
            'html_url' => 'https://github.com/RandallCox/hubkit-empire/pull/12',
            'head' => [
                'ref' => 'bug/broken-plain',
                'repo' => [
                    'ssh_url' => 'git@github.com:RandallCox/hubkit-empire.git',
                ],
                'user' => [
                    'login' => 'RandallCox',
                ],
            ],
        ]);

        $this->git->guardWorkingTreeReady()->shouldBeCalled();
        $this->git->ensureRemoteExists('RandallCox', 'git@github.com:RandallCox/hubkit-empire.git')->shouldBeCalled();
        $this->git->remoteUpdate('RandallCox')->shouldBeCalled();

        $this->git->branchExists('RandallCox--bug/broken-plain')->willReturn(true);
        $this->git->checkout('RandallCox--bug/broken-plain')->shouldBeCalled();
        $this->git->getRemoteDiffStatus('RandallCox', 'RandallCox--bug/broken-plain', 'bug/broken-plain')->willReturn(Git::STATUS_UP_TO_DATE);

        $this->executeHandler(14);

        $this->assertOutputMatches(
            [
                'This pull request was already checked out locally, updating your local version.',
                'Pull request https://github.com/RandallCox/hubkit-empire/pull/12 is checked out!',
            ]
        );
        $this->assertOutputNotMatches('Your local branch "RandallCox--bug/broken-plain" is outdated, running git pull.');
    }

    /** @test */
    public function it_checksout_a_pull_request_with_existing_branch_needs_pull(): void
    {
        $this->github->getPullRequest(14)->willReturn([
            'state' => 'open',
            'html_url' => 'https://github.com/RandallCox/hubkit-empire/pull/12',
            'head' => [
                'ref' => 'bug/broken-plain',
                'repo' => [
                    'ssh_url' => 'git@github.com:RandallCox/hubkit-empire.git',
                ],
                'user' => [
                    'login' => 'RandallCox',
                ],
            ],
        ]);

        $this->git->guardWorkingTreeReady()->shouldBeCalled();
        $this->git->ensureRemoteExists('RandallCox', 'git@github.com:RandallCox/hubkit-empire.git')->shouldBeCalled();
        $this->git->remoteUpdate('RandallCox')->shouldBeCalled();

        $this->git->branchExists('RandallCox--bug/broken-plain')->willReturn(true);
        $this->git->checkout('RandallCox--bug/broken-plain')->shouldBeCalled();
        $this->git->getRemoteDiffStatus('RandallCox', 'RandallCox--bug/broken-plain', 'bug/broken-plain')->willReturn(Git::STATUS_NEED_PULL);
        $this->git->pullRemote('RandallCox')->shouldBeCalled();

        $this->executeHandler(14);

        $this->assertOutputMatches(
            [
                'This pull request was already checked out locally, updating your local version.',
                'Your local branch "RandallCox--bug/broken-plain" is outdated, running git pull.',
                'Pull request https://github.com/RandallCox/hubkit-empire/pull/12 is checked out!',
            ]
        );
    }

    /** @test */
    public function it_checksout_a_pull_request_with_existing_branch_diverged(): void
    {
        $this->github->getPullRequest(14)->willReturn([
            'state' => 'open',
            'html_url' => 'https://github.com/RandallCox/hubkit-empire/pull/12',
            'head' => [
                'ref' => 'bug/broken-plain',
                'repo' => [
                    'ssh_url' => 'git@github.com:RandallCox/hubkit-empire.git',
                ],
                'user' => [
                    'login' => 'RandallCox',
                ],
            ],
        ]);

        $this->git->guardWorkingTreeReady()->shouldBeCalled();
        $this->git->ensureRemoteExists('RandallCox', 'git@github.com:RandallCox/hubkit-empire.git')->shouldBeCalled();
        $this->git->remoteUpdate('RandallCox')->shouldBeCalled();

        $this->git->branchExists('RandallCox--bug/broken-plain')->willReturn(true);
        $this->git->checkout('RandallCox--bug/broken-plain')->shouldBeCalled();
        $this->git->getRemoteDiffStatus('RandallCox', 'RandallCox--bug/broken-plain', 'bug/broken-plain')->willReturn(Git::STATUS_DIVERGED);

        $this->expectExceptionObject(new \RuntimeException('Your local branch and the remote version have differed. Please resolve this problem manually.'));

        $this->executeHandler(14);
    }

    private function getArgs(): Args
    {
        $format = ArgsFormat::build()
            ->addArgument(new Argument('number', Argument::REQUIRED | Argument::INTEGER))
            ->getFormat()
        ;

        return new Args($format, new StringArgs(''));
    }

    private function executeHandler(int $number): void
    {
        $style = $this->createStyle();
        $handler = new CheckoutHandler(
            $style,
            $this->git->reveal(),
            $this->github->reveal(),
            $this->config,
            $this->process->reveal(),
        );

        $args = $this->getArgs()->setArgument('number', $number);
        $handler->handle($args);
    }
}
