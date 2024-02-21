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

use HubKit\Service\Git\GitConfig;
use HubKit\Tests\Functional\GitTesterTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @internal
 */
final class GitConfigTest extends TestCase
{
    use GitTesterTrait;
    use ProphecyTrait;

    private string $output = '';
    private string $remoteRepository = '';

    private GitConfig $git;
    private ObjectProphecy $style;

    /** @before */
    public function setUpLocalRepository(): void
    {
        $this->cwd = $this->localRepository = $this->createGitDirectory($this->getTempDir() . '/git');
        $this->commitFileToRepository('foo.txt', $this->localRepository);

        $this->remoteRepository = $this->createBareGitDirectory($this->getTempDir() . '/git2');
        $this->addRemote('origin', $this->remoteRepository, $this->localRepository);
        $this->runCliCommand(['git', 'push', 'origin', 'master'], $this->localRepository);

        $test = $this; // Come-on Prophecy :'(
        $this->style = $this->prophesize(StyleInterface::class);
        $this->style->note(Argument::any())->will(
            static function ($text) use ($test): void {
                $test->output .= implode('', $text);
            }
        );
        $this->git = new GitConfig($this->getProcessService(), $this->style->reveal());
    }

    /** @test */
    public function it_ensures_notes_are_fetched(): void
    {
        $this->runCliCommand(['git', 'config', '--replace-all', '--local', 'remote.origin.fetch', '+refs/heads/*:refs/remotes/upstream/*']);

        $this->git->ensureNotesFetching('origin');

        self::assertEquals(
            "+refs/heads/*:refs/remotes/upstream/*\n+refs/notes/*:refs/notes/*",
            trim($this->runCliCommand(['git', 'config', '--get-all', '--local', 'remote.origin.fetch'])->getOutput())
        );
        self::assertEquals('Set fetching of notes for remote "origin".', $this->output);
    }

    /** @test */
    public function it_ensures_notes_are_fetched_once(): void
    {
        $this->runCliCommand(['git', 'config', '--replace-all', '--local', 'remote.origin.fetch', '+refs/heads/*:refs/remotes/upstream/*']);
        $this->runCliCommand(['git', 'config', '--add', '--local', 'remote.origin.fetch', '+refs/notes/*:refs/notes/*']);

        $this->git->ensureNotesFetching('origin');

        self::assertEquals(
            "+refs/heads/*:refs/remotes/upstream/*\n+refs/notes/*:refs/notes/*",
            trim($this->runCliCommand(['git', 'config', '--get-all', '--local', 'remote.origin.fetch'])->getOutput())
        );
        self::assertEquals('', $this->output);
    }

    /** @test */
    public function it_ensures_remote_exists(): void
    {
        $this->git->ensureRemoteExists('upstream', 'https://github.com/park-manager/hubkit');

        $this->assertGitConfigEquals('https://github.com/park-manager/hubkit', 'remote.upstream.url');
        $this->assertGitConfigEquals('file://' . $this->remoteRepository, 'remote.origin.url');
        self::assertEquals('Adding remote "upstream" with "https://github.com/park-manager/hubkit".', $this->output);
    }

    /** @test */
    public function it_ensures_remote_exists_with_correct_url(): void
    {
        $this->runCliCommand(['git', 'remote', 'add', 'upstream', 'https://github.com/park-manager/gubmit']);

        $this->git->ensureRemoteExists('upstream', 'https://github.com/park-manager/hubkit');

        $this->assertGitConfigEquals('https://github.com/park-manager/hubkit', 'remote.upstream.url');
        $this->assertGitConfigEquals('file://' . $this->remoteRepository, 'remote.origin.url');
        self::assertEquals('Adding remote "upstream" with "https://github.com/park-manager/hubkit".', $this->output);
    }

    private function assertGitConfigEquals(string $expectedValue, string $configName): void
    {
        self::assertEquals(
            $expectedValue,
            trim($this->runCliCommand(['git', 'config', '--get-all', '--local', $configName])->getOutput())
        );
    }

    /** @test */
    public function it_sets_local_configuration(): void
    {
        $this->git->setLocal('branch.master.alias', '2.0', true);

        self::assertEquals('2.0', $this->git->getLocal('branch.master.alias'));
        self::assertEquals('2.0', $this->git->getAllLocal('branch.master.alias'));
    }

    /** @test */
    public function it_gets_global_configuration(): void
    {
        $this->git->setLocal('branch.master.alias', '2.0', true);

        self::assertNotEquals('', $this->git->getGlobal('author.name'));
        self::assertNotEquals('', $this->git->getAllGlobal('author.name'));
    }

    /** @test */
    public function it_gets_remote_info(): void
    {
        $this->git->ensureRemoteExists('upstream', 'https://github.com/park-manager/hubkit');

        self::assertEquals(
            [
                'host' => 'github.com',
                'org' => 'park-manager',
                'repo' => 'hubkit',
            ],
            $this->git->getRemoteInfo('upstream')
        );
    }

    public function provideGitUrls(): iterable
    {
        yield 'Https' => [
            'https://github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Http' => [
            'http://github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Https with .git suffix' => [
            'https://github.com/park-manager/hubkit.git',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Https with port number' => [
            'http://github.com:80/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Https with username authenticator in hostname' => [
            'https://sstok@github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Https without repository, organization only' => [
            'https://github.com/park-manager',
            ['host' => 'github.com', 'org' => '', 'repo' => ''],
        ];

        yield 'Https without organization' => [
            'https://github.com/',
            ['host' => 'github.com', 'org' => '', 'repo' => ''],
        ];

        yield 'Https host only' => [
            'https://github.com',
            ['host' => 'github.com', 'org' => '', 'repo' => ''],
        ];

        yield 'Git protocol' => [
            'git://sstok@github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Git protocol without resolvable location' => [
            'git://sstok@github.com/park-manager-hubkit',
            ['host' => 'github.com', 'org' => '', 'repo' => ''],
        ];

        yield 'Ssh+git protocol' => [
            'ssh+git://sstok@github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Ssh protocol' => [
            'ssh://github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Ssh protocol with username' => [
            'ssh://sstok@github.com/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Ssh with relative path location' => [
            'ssh://github.com/~home/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Ssh with .git suffix' => [
            'ssh://github.com/park-manager/hubkit.git',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'Ssh port number' => [
            'ssh://sstok@github.com:8080/park-manager/hubkit',
            ['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'],
        ];

        yield 'File local protocol' => [
            'file:///home/homer/projects/park-manager/',
            ['host' => '', 'org' => '', 'repo' => ''],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideGitUrls
     */
    public function it_gets_git_url_info(string $url, array $info): void
    {
        self::assertEquals($info, $this->git::getGitUrlInfo($url));
    }
}
