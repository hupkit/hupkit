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

namespace HubKit\Tests\Functional\Service\Git;

use HubKit\Service\Git\GitConfig;
use HubKit\Tests\Functional\GitTesterTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @internal
 */
final class GitConfigTest extends TestCase
{
    use GitTesterTrait;
    use ProphecyTrait;

    /** @var string */
    private $localRepository;
    /** @var string */
    private $remoteRepository;
    /** @var GitConfig */
    private $git;
    /** @var StyleInterface */
    private $style;
    /** @var string */
    public $output = '';

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
        return [
            ['https://github.com/park-manager/hubkit'],
            ['https://github.com/park-manager/hubkit.git'],
            ['http://github.com/park-manager/hubkit'],
            ['http://github.com:80/park-manager/hubkit'],
            ['git://sstok@github.com/park-manager/hubkit'],
            ['ssh+git://sstok@github.com/park-manager/hubkit'],
            ['ssh://github.com/park-manager/hubkit'],
            ['ssh://github.com/~home/park-manager/hubkit'],
            ['ssh://github.com/park-manager/hubkit.git'],
            ['ssh://sstok@github.com/park-manager/hubkit'],
            ['ssh://sstok@github.com:8080/park-manager/hubkit'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideGitUrls
     */
    public function it_gets_git_url_info(string $url): void
    {
        self::assertEquals(['host' => 'github.com', 'org' => 'park-manager', 'repo' => 'hubkit'], $this->git::getGitUrlInfo($url));
    }
}
