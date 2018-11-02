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

class Git
{
    public const STATUS_UP_TO_DATE = 'up-to-date';
    public const STATUS_NEED_PULL = 'need_pull';
    public const STATUS_NEED_PUSH = 'up-to-date';
    public const STATUS_DIVERGED = 'diverged';

    protected $process;
    private $filesystem;
    protected $style;
    private $gitDir;

    public function __construct(
        CliProcess $process,
        Filesystem $filesystemHelper,
        StyleInterface $style
    ) {
        $this->process = $process;
        $this->filesystem = $filesystemHelper;
        $this->style = $style;
    }

    public function isGitDir(): bool
    {
        $directory = trim($this->process->run(['git', 'rev-parse', '--show-toplevel'])->getOutput());

        if ('' === $directory) {
            return false;
        }

        return str_replace('\\', '/', $this->getCwd()) === $directory;
    }

    /**
     * Gets the diff status of the remote and local.
     *
     * @param string $remoteName
     * @param string $localBranch
     * @param string $remoteBranch
     *
     * @return string Returns the value of one of the following constants:
     *                GitHelper::STATUS_UP_TO_DATE, GitHelper::STATUS_NEED_PULL
     *                GitHelper::STATUS_NEED_PUSH, GitHelper::STATUS_DIVERGED
     *
     * @see https://gist.github.com/WebPlatformDocs/437f763b948c926ca7ba
     * @see https://stackoverflow.com/questions/3258243/git-check-if-pull-needed
     */
    public function getRemoteDiffStatus(string $remoteName, string $localBranch, string $remoteBranch = null): string
    {
        if (null === $remoteBranch) {
            $remoteBranch = $localBranch;
        }

        $localRef = $this->process->mustRun(['git', 'rev-parse', $localBranch])->getOutput();
        $remoteRef = $this->process->mustRun(['git', 'rev-parse', 'refs/remotes/'.$remoteName.'/'.$remoteBranch])->getOutput();
        $baseRef = $this->process->mustRun(['git', 'merge-base', $localBranch, 'refs/remotes/'.$remoteName.'/'.$remoteBranch])->getOutput();

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
        $activeBranch = trim($this->process->mustRun('git rev-parse --abbrev-ref HEAD')->getOutput());

        if ('HEAD' === $activeBranch) {
            throw new \RuntimeException(
                'You are currently in a detached HEAD state, unable to get active branch-name.'.
                'Please run `git checkout` first.'
            );
        }

        return $activeBranch;
    }

    public function getLastTagOnBranch(string $ref = 'HEAD'): string
    {
        return trim($this->process->mustRun(['git', 'describe', '--tags', '--abbrev=0', $ref])->getOutput());
    }

    public function getVersionBranches(string $remote): array
    {
        $branches = StringUtil::splitLines(
            $this->process->mustRun(
                ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/'.$remote]
            )->getOutput()
        );

        $branches = array_filter($branches, function (string $branch) {
            return preg_match('/^v?'.Version::VERSION_REGEX.'$/i', $branch) || preg_match('/^v?(?P<major>\d++)\.(?P<rel>x)$/', $branch);
        });

        // Sort in ascending order (lowest first).
        // Trim v prefix as this causes problems with the comparator.
        usort($branches, function ($a, $b) {
            $a = ltrim($a, 'vV');
            $b = ltrim($b, 'vV');

            if (substr($a, -1, 1) === 'x') {
                $a = substr_replace($a, '999', -1, 1);
            }

            if (substr($b, -1, 1) === 'x') {
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
     * @param string $start
     * @param string $end
     *
     * @return array[]
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
                '--merges',
                '--oneline',
                '--no-color',
                '--format=%H',
                '--reverse',
                $start.'..'.$end,
            ]
        )->getOutput());

        return array_map(
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

                return [
                    'sha' => $commitHash,
                    'author' => array_shift($commitData),
                    'subject' => array_shift($commitData),
                    // subject + \n\n + {$commitData remaining}
                    'message' => implode("\n", $commitData),
                ];
            },
            $commits
        );
    }

    public function remoteBranchExists(string $remote, string $branch): bool
    {
        $this->remoteUpdate($remote);
        $branches = StringUtil::splitLines(
            $this->process->mustRun(
                ['git', 'for-each-ref', '--format', '%(refname:strip=3)', 'refs/remotes/'.$remote]
            )->getOutput()
        );

        return \in_array($branch, $branches, true);
    }

    public function branchExists(string $branch): bool
    {
        $branches = StringUtil::splitLines(
            $this->process->mustRun("git for-each-ref --format='%(refname:short)' refs/heads/")->getOutput()
        );

        return \in_array($branch, $branches, true);
    }

    public function deleteRemoteBranch(string $remote, string $ref)
    {
        $this->process->mustRun(['git', 'push', $remote, ':'.$ref]);
    }

    public function deleteBranch(string $name, $allowFailure = false)
    {
        if ($allowFailure) {
            $this->process->run(['git', 'branch', '-d', $name], sprintf('Could not delete branch "%s".', $name));
        } else {
            $this->process->mustRun(['git', 'branch', '-d', $name]);
        }
    }

    public function addNotes(string $notes, string $commitHash, string $ref = 'github-comments')
    {
        $tmpName = $this->filesystem->newTempFilename();
        file_put_contents($tmpName, $notes);

        // Cannot add empty notes
        if ('' === trim($notes)) {
            return;
        }

        $commands = [
            'git',
            'notes',
            '--ref='.$ref,
            'add',
            '-F',
            $tmpName,
            $commitHash,
        ];

        $this->process->run($commands, 'Adding git notes failed.');
    }

    public function pushToRemote(string $remote, $ref, bool $setUpstream = false, bool $force = false)
    {
        $ref = (array) $ref;
        $ref = array_map(
            function ($ref) {
                if (':' === $ref[0]) {
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

    public function pullRemote(string $remote, string $ref = null)
    {
        $this->guardWorkingTreeReady();

        $command = ['git', 'pull', '--rebase', $remote];

        if ($ref) {
            $command[] = $ref;
        }

        $this->process->mustRun($command);
    }

    public function remoteUpdate(string $remote)
    {
        $this->process->mustRun(['git', 'fetch', $remote]);
    }

    public function isWorkingTreeReady()
    {
        if ('' !== trim($this->process->mustRun('git status --porcelain --untracked-files=no')->getOutput())) {
            return false;
        }

        if ('' !== trim($this->process->run('ls `git rev-parse --git-dir` | grep rebase')->getOutput())) {
            return false;
        }

        return true;
    }

    public function checkout(string $branchName, bool $createBranch = false)
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
     *
     * @param string $remote
     * @param string $branchName
     */
    public function checkoutRemoteBranch(string $remote, string $branchName)
    {
        if ($this->branchExists($branchName)) {
            $this->process->mustRun(['git', 'checkout', $branchName]);

            return;
        }

        $this->process->mustRun(['git', 'checkout', $remote.'/'.$branchName, '-b', $branchName]);
    }

    public function guardWorkingTreeReady()
    {
        if (!$this->isWorkingTreeReady()) {
            throw new WorkingTreeIsNotReady();
        }
    }

    public function ensureNotesFetching(string $remote)
    {
        $fetches = StringUtil::splitLines(
            $this->getGitConfig('remote.'.$remote.'.fetch', 'local', true)
        );

        if (!\in_array('+refs/notes/*:refs/notes/*', $fetches, true)) {
            $this->style->note(
                sprintf('Set fetching of notes for remote "%s".', $remote)
            );

            $this->process->mustRun(
                ['git', 'config', '--add', '--local', 'remote.'.$remote.'.fetch', '+refs/notes/*:refs/notes/*']
            );
        }
    }

    public function ensureBranchInSync(string $remote, string $localBranch, $allowPush = true)
    {
        $status = $this->getRemoteDiffStatus($remote, $localBranch);

        if (self::STATUS_NEED_PULL === $status) {
            $this->style->note(
                sprintf('Your local branch "%s" is outdated, running git pull.', $localBranch)
            );

            $this->pullRemote($remote, $localBranch);
        } elseif (self::STATUS_DIVERGED === $status) {
            throw new \RuntimeException(
                'Cannot safely perform the operation. '.
                sprintf('Your local and remote version of branch "%s" have differed.', $localBranch).
                ' Please resolve this problem manually.'
            );
        } elseif (!$allowPush && self::STATUS_NEED_PUSH === $status) {
            throw new \RuntimeException(
                sprintf('Branch "%s" contains commits not existing in the remote version.', $localBranch).
                'Push is prohibited for this operation. Create a new branch and do a `git reset --hard`.'
            );
        }
    }

    public function ensureRemoteExists(string $name, string $url)
    {
        if ($url !== $this->getGitConfig('remote.'.$name.'.url')) {
            $this->style->note(sprintf('Adding remote "%s" with "%s".', $name, $url));

            if (!$this->getGitConfig('remote.'.$name.'.url')) {
                $this->process->mustRun(['git', 'remote', 'add', $name, $url]);
            } else {
                $this->setGitConfig('remote.'.$name.'.url', $url, true);
            }
        }
    }

    public function setGitConfig(string $config, $value, bool $overwrite = false, string $section = 'local')
    {
        if (!$overwrite && '' !== $this->getGitConfig($config, $section, $value)) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to set git config "%s" at %s, because the value is already set.',
                    $config,
                    $section
                )
            );
        }

        $this->process->mustRun(
            sprintf(
                'git config "%s" "%s" --%s',
                $config,
                $value,
                $section
            )
        );
    }

    public function getGitConfig(string $config, string $section = 'local', bool $all = false): string
    {
        $process = $this->process->run(
            ['git', 'config', '--'.$section, '--'.($all ? 'get-all' : 'get'), $config]
        );

        return trim($process->getOutput());
    }

    /**
     * @param string $name
     *
     * @return array [host, org, repo]
     */
    public function getRemoteInfo(string $name = 'upstream'): array
    {
        return self::getGitUrlInfo($this->getGitConfig('remote.'.$name.'.url'));
    }

    /**
     * @param string $gitUri
     *
     * @return array [host, org, repo]
     */
    public static function getGitUrlInfo(string $gitUri): array
    {
        $info = [
            'host' => '',
            'org' => '',
            'repo' => '',
        ];

        if (0 === stripos($gitUri, 'http://') || 0 === stripos($gitUri, 'https://')) {
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
            $info['repo'] = substr($dirs[1], -4, 4) === '.git' ? substr($dirs[1], 0, -4) : $dirs[1];

            unset($info['path']);
        }

        return $info;
    }

    public function clone(string $ssh_url, string $remoteName = 'origin', ?int $depth = null)
    {
        $command = ['git', 'clone', $ssh_url, '.'];

        if (null !== $depth) {
            $command[] = '--depth';
            $command[] = $depth;
        }

        $this->process->mustRun($command);

        if ('origin' !== $remoteName) {
            $this->process->mustRun(['git', 'remote', 'rename', 'origin', $remoteName]);
        }
    }

    public function getGitDirectory(): string
    {
        if (null === $this->gitDir) {
            $gitDir = trim($this->process->run('git rev-parse --git-dir')->getOutput());
            if ('.git' === $gitDir) {
                $gitDir = $this->getCwd().'/.git';
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
