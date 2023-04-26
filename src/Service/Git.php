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

namespace HubKit\Service;

use Composer\Semver\Comparator;
use HubKit\Exception\WorkingTreeIsNotReady;
use HubKit\StringUtil;
use Rollerworks\Component\Version\Version;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Process\Process;

class Git
{
    final public const STATUS_UP_TO_DATE = 'up-to-date';
    final public const STATUS_NEED_PULL = 'need_pull';
    final public const STATUS_NEED_PUSH = 'need_push';
    final public const STATUS_DIVERGED = 'diverged';

    private ?string $gitDir = null;

    public function __construct(
        protected CliProcess $process,
        private readonly Filesystem $filesystem,
        protected StyleInterface $style
    ) {
    }

    public function isGitDir(): bool
    {
        $process = $this->process->run(['git', 'rev-parse', '--show-toplevel']);

        if (! $process->isSuccessful()) {
            return false;
        }

        $directory = trim($process->getOutput());

        if ($directory === '') {
            return false;
        }

        return str_replace('\\', '/', $this->getCwd()) === $directory;
    }

    /**
     * Gets the diff status of the remote and local.
     *
     * @return string Returns the value of one of the following constants:
     *                GitHelper::STATUS_UP_TO_DATE, GitHelper::STATUS_NEED_PULL
     *                GitHelper::STATUS_NEED_PUSH, GitHelper::STATUS_DIVERGED
     *
     * @see https://gist.github.com/WebPlatformDocs/437f763b948c926ca7ba
     * @see https://stackoverflow.com/questions/3258243/git-check-if-pull-needed
     */
    public function getRemoteDiffStatus(string $remoteName, string $localBranch, ?string $remoteBranch = null): string
    {
        if ($remoteBranch === null) {
            $remoteBranch = $localBranch;
        }

        if (! $this->remoteBranchExists($remoteName, $remoteBranch)) {
            return self::STATUS_NEED_PUSH;
        }

        $localRef = $this->process->mustRun(['git', 'rev-parse', $localBranch])->getOutput();
        $remoteRef = $this->process->mustRun(['git', 'rev-parse', 'refs/remotes/' . $remoteName . '/' . $remoteBranch])->getOutput();
        $baseRef = $this->process->mustRun(['git', 'merge-base', $localBranch, 'refs/remotes/' . $remoteName . '/' . $remoteBranch])->getOutput();

        if ($localRef === $remoteRef) {
            return self::STATUS_UP_TO_DATE;
        }

        if ($localRef === $baseRef) {
            return self::STATUS_NEED_PULL;
        }

        if ($remoteRef === $baseRef) {
            return self::STATUS_NEED_PUSH;
        }

        return self::STATUS_DIVERGED;
    }

    public function getActiveBranchName(): string
    {
        $activeBranch = trim($this->process->mustRun(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->getOutput());

        if ($activeBranch === 'HEAD') {
            throw new \RuntimeException(
                'You are currently in a detached HEAD state, unable to get active branch-name.' .
                'Please run `git checkout` first.'
            );
        }

        return $activeBranch;
    }

    /**
     * @return string either main, master or a custom configured branch-name
     */
    public function getPrimaryBranch(): string
    {
        static $branch = null;

        $branch ??= $this->getGitConfig('init.defaultbranch');

        if ($branch !== '') {
            return $branch;
        }

        if ($this->branchExists('main')) {
            $branch = 'main';
        } elseif ($this->branchExists('master')) {
            $branch = 'master';
        } else {
            throw new \RuntimeException(
                'Unable to determine primary-branch , expected either "master" or "main". But neither one was found, set the "init.defaultbranch" Git local config to resolve this.'
            );
        }

        return $branch;
    }

    public function getLastTagOnBranch(string $ref = 'HEAD'): string
    {
        return trim($this->process->mustRun(['git', 'describe', '--tags', '--abbrev=0', $ref])->getOutput());
    }

    /**
     * @return array<int, string> ['v1.0', 'v1.5', 'v2.0' '...']
     */
    public function getVersionBranches(string $remote): array
    {
        $branches = StringUtil::splitLines(
            $this->process->mustRun(
                ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/' . $remote]
            )->getOutput()
        );

        $branches = array_filter($branches, static fn (string $branch) => preg_match('/^v?' . Version::VERSION_REGEX . '$/i', $branch) || preg_match('/^v?(?P<major>\d++)\.(?P<rel>x)$/', $branch));

        // Sort in ascending order (lowest first).
        // Trim v prefix as this causes problems with the comparator.
        usort($branches, static function ($a, $b) {
            $a = ltrim($a, 'vV');
            $b = ltrim($b, 'vV');

            if (mb_substr($a, -1, 1) === 'x') {
                $a = substr_replace($a, '999', -1, 1);
            }

            if (mb_substr($b, -1, 1) === 'x') {
                $b = substr_replace($b, '999', -1, 1);
            }

            if (Comparator::equalTo($a, $b)) {
                return 0;
            }

            return Comparator::lessThan($a, $b) ? -1 : 1;
        });

        return array_merge([], $branches);
    }

    /**
     * Returns the log commits between two ranges (either commit or branch-name).
     *
     * Returned result is an array like:
     * [
     *     ['sha' => '...', 'author' => '...', 'subject' => '...', 'message' => '...'],
     * ]
     *
     * Note;
     * - Commits are by default returned in order of oldest to newest.
     * - sha is the full commit-hash
     * - author is the author name and e-mail address like "My Name <someone@example.com>"
     * - Message contains the subject followed by two new lines and the actual message-body.
     *
     * Or an empty array when there are no logs.
     *
     * @return array<int array{'sha': string, 'author': string, 'subject': string, 'message': string}>
     */
    public function getLogBetweenCommits(string $start, string $end): array
    {
        // First we get all the commits, then of each commit we get the actual data
        // We can't use the commit data in one go because the body contains newlines

        $commits = StringUtil::splitLines($this->process->mustRun(
            [
                'git',
                '--no-pager',
                'log',
                '--oneline',
                '--no-color',
                '--format=%H',
                '--reverse',
                $start . '..' . $end,
            ]
        )->getOutput());

        $results = array_map(
            function ($commitHash) {
                // 0=author, 1=subject, anything higher then 2 is the full body
                $commitData = StringUtil::splitLines(
                    $this->process->run(
                        [
                            'git',
                            '--no-pager',
                            'show',
                            '--format=%an <%ae>%n%s%n%b',
                            '--no-color',
                            '--no-patch',
                            $commitHash,
                        ]
                    )->getOutput()
                );

                $author = array_shift($commitData);
                $subject = array_shift($commitData);

                if (! preg_match('/^(feature|refactor|bug|minor|style|security)\s#\d*\s.*\s\(.*\)$/', $subject)) {
                    return null;
                }

                return [
                    'sha' => $commitHash,
                    'author' => $author,
                    'subject' => $subject,
                    // subject + \n\n + {$commitData remaining}
                    'message' => implode("\n", $commitData),
                ];
            },
            $commits
        );

        return array_values(array_filter($results));
    }

    public function remoteBranchExists(string $remote, string $branch): bool
    {
        $this->remoteUpdate($remote);
        $branches = StringUtil::splitLines(
            $this->process->mustRun(
                ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/' . $remote]
            )->getOutput()
        );

        return \in_array($branch, $branches, true);
    }

    public function branchExists(string $branch): bool
    {
        $branches = StringUtil::splitLines(
            $this->process->mustRun(['git', 'for-each-ref', '--format', '%(refname:short)', 'refs/heads/'])->getOutput()
        );

        return \in_array($branch, $branches, true);
    }

    public function deleteRemoteBranch(string $remote, string $ref): void
    {
        $this->process->mustRun(['git', 'push', $remote, ':' . $ref]);
    }

    public function deleteBranch(string $name, $allowFailure = false): void
    {
        if ($allowFailure) {
            $this->process->run(['git', 'branch', '-d', $name], sprintf('Could not delete branch "%s".', $name));
        } else {
            $this->process->mustRun(['git', 'branch', '-d', $name]);
        }
    }

    public function deleteBranchWithForce(string $name): void
    {
        $this->process->run(['git', 'branch', '-D', $name], sprintf('Could not delete branch "%s".', $name));
    }

    public function addNotes(string $notes, string $commitHash, string $ref = 'github-comments'): void
    {
        $tmpName = $this->filesystem->newTempFilename();
        file_put_contents($tmpName, $notes);

        // Cannot add empty notes
        if (trim($notes) === '') {
            return;
        }

        $commands = [
            'git',
            'notes',
            '--ref=' . $ref,
            'add',
            '-F',
            $tmpName,
            $commitHash,
        ];

        $this->process->run($commands, 'Adding git notes failed.');
    }

    /**
     * @param array<int, string>|string $ref either a single ref of array of references
     */
    public function pushToRemote(string $remote, array | string $ref, bool $setUpstream = false, bool $force = false): void
    {
        $ref = (array) $ref;
        $ref = array_map(
            static function ($ref) {
                if ($ref[0] === ':') {
                    throw new \RuntimeException(
                        sprintf(
                            'Push target "%s" does not include the local branch-name, please report this bug!',
                            $ref
                        )
                    );
                }

                return $ref;
            },
            $ref
        );

        $command = ['git', 'push'];

        if ($setUpstream) {
            $command[] = '--set-upstream';
        }

        if ($force) {
            $command[] = '--force';
        }

        $command[] = $remote;

        $this->process->mustRun(array_merge($command, $ref));
    }

    public function pullRemote(string $remote, string $ref = null): void
    {
        $this->guardWorkingTreeReady();

        $command = ['git', 'pull', '--rebase', $remote];

        if ($ref) {
            $command[] = $ref;
        }

        $this->process->mustRun($command);
    }

    public function remoteUpdate(string $remote): void
    {
        $this->process->mustRun(['git', 'fetch', $remote]);
    }

    public function isWorkingTreeReady()
    {
        if (trim($this->process->mustRun(['git', 'status', '--porcelain', '--untracked-files=no'])->getOutput()) !== '') {
            return false;
        }

        if (trim($this->process->run(Process::fromShellCommandline('ls `git rev-parse --git-dir` | grep rebase'))->getOutput()) !== '') {
            return false;
        }

        return true;
    }

    public function checkout(string $branchName, bool $createBranch = false): void
    {
        $command = ['git', 'checkout'];

        if ($createBranch) {
            $command[] = '-b';
        }

        $command[] = $branchName;

        $this->process->mustRun($command);
    }

    /**
     * Checkout a remote branch or create it when it doesn't exit yet.
     */
    public function checkoutRemoteBranch(string $remote, string $branchName): void
    {
        if ($this->branchExists($branchName)) {
            $this->process->mustRun(['git', 'checkout', $branchName]);

            return;
        }

        $this->process->mustRun(['git', 'checkout', 'remotes/' . $remote . '/' . $branchName, '-b', $branchName]);
    }

    public function guardWorkingTreeReady(): void
    {
        if (! $this->isWorkingTreeReady()) {
            throw new WorkingTreeIsNotReady();
        }
    }

    public function ensureNotesFetching(string $remote): void
    {
        $fetches = StringUtil::splitLines(
            $this->getGitConfig('remote.' . $remote . '.fetch', 'local', true)
        );

        if (! \in_array('+refs/notes/*:refs/notes/*', $fetches, true)) {
            $this->style->note(
                sprintf('Set fetching of notes for remote "%s".', $remote)
            );

            $this->process->mustRun(
                ['git', 'config', '--add', '--local', 'remote.' . $remote . '.fetch', '+refs/notes/*:refs/notes/*']
            );
        }
    }

    public function ensureBranchInSync(string $remote, string $localBranch, bool $allowPush = true): void
    {
        $status = $this->getRemoteDiffStatus($remote, $localBranch);

        if ($status === self::STATUS_NEED_PULL) {
            $this->style->note(
                sprintf('Your local branch "%s" is outdated, running git pull.', $localBranch)
            );

            $this->pullRemote($remote, $localBranch);
        } elseif ($status === self::STATUS_DIVERGED) {
            throw new \RuntimeException(
                'Cannot safely perform the operation. ' .
                sprintf('Your local and remote version of branch "%s" have differed.', $localBranch) .
                ' Please resolve this problem manually.'
            );
        } elseif (! $allowPush && $status === self::STATUS_NEED_PUSH) {
            throw new \RuntimeException(
                sprintf('Branch "%s" contains commits not existing in the remote version.', $localBranch) .
                'Push is prohibited for this operation. Create a new branch and do a `git reset --hard`.'
            );
        }
    }

    public function ensureRemoteExists(string $name, string $url): void
    {
        if ($url !== $this->getGitConfig('remote.' . $name . '.url')) {
            $this->style->note(sprintf('Adding remote "%s" with "%s".', $name, $url));

            if (! $this->getGitConfig('remote.' . $name . '.url')) {
                $this->process->mustRun(['git', 'remote', 'add', $name, $url]);
            } else {
                $this->setGitConfig('remote.' . $name . '.url', $url, true);
            }
        }
    }

    public function setGitConfig(string $config, string | int $value, bool $overwrite = false, string $section = 'local'): void
    {
        if (! $overwrite && $this->getGitConfig($config, $section) !== '') {
            throw new \RuntimeException(
                sprintf(
                    'Unable to set git config "%s" at %s, because the value is already set.',
                    $config,
                    $section
                )
            );
        }

        // Git adds a new value (superseding the old one) but we want replace the entire value.
        // And `--replace-all` requires a regexp (WAT?) to properly replace the value...
        $this->process->run(['git', 'config', '--' . $section, '--unset', $config]);
        $this->process->mustRun(['git', 'config', '--' . $section, $config, $value]);
    }

    public function getGitConfig(string $config, string $section = 'local', bool $all = false): string
    {
        $process = $this->process->run(
            ['git', 'config', '--' . $section, '--' . ($all ? 'get-all' : 'get'), $config]
        );

        return trim($process->getOutput());
    }

    /**
     * @return array [host, org, repo]
     */
    public function getRemoteInfo(string $name = 'upstream'): array
    {
        return self::getGitUrlInfo($this->getGitConfig('remote.' . $name . '.url'));
    }

    /**
     * @return array [host, org, repo]
     */
    public static function getGitUrlInfo(string $gitUri): array
    {
        $info = [
            'host' => '',
            'org' => '',
            'repo' => '',
        ];

        if (mb_stripos($gitUri, 'http://') === 0 || mb_stripos($gitUri, 'https://') === 0) {
            $url = parse_url($gitUri);

            $info['host'] = $url['host'];
            $info['path'] = ltrim($url['path'], '/');
        } elseif (preg_match('%^(?:(?:git|ssh)://)?[^@]+@(?P<host>[^:]+):(?P<path>[^$]+)$%', $gitUri, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        } elseif (preg_match('%^(?:(?:git|ssh)://)?([^@]+@)?(?P<host>[^/]+)/(?P<path>[^$]+)$%', $gitUri, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        }

        if (isset($info['path'])) {
            $dirs = \array_slice(explode('/', $info['path']), -2, 2);

            $info['org'] = $dirs[0];
            $info['repo'] = mb_substr($dirs[1], -4, 4) === '.git' ? mb_substr($dirs[1], 0, -4) : $dirs[1];

            unset($info['path']);
        }

        return $info;
    }

    public function clone(string $ssh_url, string $remoteName = 'origin', ?int $depth = null): void
    {
        $command = ['git', 'clone', $ssh_url, '.'];

        if ($depth !== null) {
            $command[] = '--depth';
            $command[] = $depth;
        }

        $this->process->mustRun($command);

        if ($remoteName !== 'origin') {
            $this->process->mustRun(['git', 'remote', 'rename', 'origin', $remoteName]);
        }
    }

    public function getGitDirectory(): string
    {
        if ($this->gitDir === null) {
            $gitDir = trim($this->process->run(['git', 'rev-parse', '--git-dir'])->getOutput());

            if ($gitDir === '.git') {
                $gitDir = $this->getCwd() . '/.git';
            }

            $this->gitDir = $gitDir;
        }

        return $this->gitDir;
    }

    protected function getCwd(): string
    {
        return getcwd();
    }
}
