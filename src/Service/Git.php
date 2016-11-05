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

use HubKit\Exception\WorkingTreeIsNotReady;
use HubKit\StringUtil;
use Symfony\Component\Console\Style\StyleInterface;

class Git
{
    const STATUS_UP_TO_DATE = 'up-to-date';
    const STATUS_NEED_PULL = 'need_pull';
    const STATUS_NEED_PUSH = 'up-to-date';
    const STATUS_DIVERGED = 'diverged';

    private $process;
    private $filesystem;
    private $style;

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
        $directory = $this->process->run(['git', 'rev-parse', '--show-toplevel'])->getOutput();

        if ('' === $directory) {
            return false;
        }

        return str_replace('\\', '/', getcwd()) !== $directory;
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
     * @link https://gist.github.com/WebPlatformDocs/437f763b948c926ca7ba
     * @link https://stackoverflow.com/questions/3258243/git-check-if-pull-needed
     */
    public function getRemoteDiffStatus(string $remoteName, string $localBranch, string $remoteBranch = null): string
    {
        if (null === $remoteBranch) {
            $remoteBranch = $localBranch;
        }

        $localRef = $this->process->mustRun(['git', 'rev-parse', $localBranch])->getOutput();
        $remoteRef = $this->process->mustRun(['git', 'rev-parse', $remoteName.'/'.$remoteBranch])->getOutput();
        $baseRef = $this->process->mustRun(['git', 'merge-base', $remoteBranch, $remoteName.'/'.$remoteBranch])->getOutput();

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
        return $this->process->mustRun(['git', 'describe', '--tags', '--abbrev=0', $ref])->getOutput();
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
                    'subject' => $commitData[0],
                    // subject + \n\n + {$commitData remaining}
                    'message' => array_shift($commitData)."\n\n".implode("\n", $commitData),
                ];
            },
            $commits
        );
    }

    public function branchExists(string $branch): bool
    {
        $result = $this->process->mustRun(['git', 'branch', '--list', $branch])->getOutput();
        if (1 >= ($exists = preg_match_all('#(?<=\s)'.preg_quote($branch, '#').'(?!\w)$#m', $result))) {
            return 1 === $exists;
        }

        throw new \RuntimeException(sprintf('Invalid list of local branches found while searching for "%s"', $branch));
    }

    public function deleteRemoteBranch(string $remote, string $ref)
    {
        $this->process->mustRun(['git', 'push', $remote, ':'.$ref]);
    }

    public function deleteBranch(string $name, $allowFailure = false)
    {
        if ($allowFailure) {
            $this->process->run(
                ['git', 'branch', '-d', $name],
                sprintf('Could not delete branch "%s", not fully merged?.', $name)
            );
        } else {
            $this->process->mustRun(['git', 'branch', '-d', $name]);
        }
    }

    /**
     * Merge a branch with a commit log in the merge message.
     *
     * @param string $base         The branch to which $sourceBranch is merged
     * @param string $sourceBranch The source branch name
     *
     * @throws WorkingTreeIsNotReady
     *
     * @return string The merge-commit hash
     */
    public function mergeBranchWithLog(string $base, string $sourceBranch): string
    {
        $this->guardWorkingTreeReady();
        $this->checkout($base);

        return trim($this->process->mustRun(['git', 'merge', '--no-ff', '--log', $sourceBranch])->getOutput());
    }

    public function addNotes(string $notes, string $commitHash, string $ref = 'github-comments')
    {
        $tmpName = $this->filesystem->newTempFilename();
        file_put_contents($tmpName, $notes);

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

    public function pushToRemote(string $remote, string $ref, bool $setUpstream = false, bool $force = false)
    {
        if (':' === $ref[0]) {
            throw new \RuntimeException(
                sprintf('Push target "%s" does not include the local branch-name, please report this bug!', $ref)
            );
        }

        $command = ['git', 'push'];

        if ($setUpstream) {
            $command[] = '--set-upstream';
        }

        if ($force) {
            $command[] = '--force';
        }

        $command[] = $remote;
        $command[] = $ref;

        $this->process->mustRun($command);
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
        $this->process->mustRun(['git', 'remote', 'update', $remote]);
    }

    public function isWorkingTreeReady()
    {
        return '' === trim($this->process->mustRun('git status --porcelain --untracked-files=no')->getOutput());
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

        if (!in_array('+refs/notes/*:refs/notes/*', $fetches, true)) {
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

    public function setGitConfig(string $config, $value, bool $overwrite = false, string $section = 'local')
    {
        if (!$overwrite && '' !== (string) $this->getGitConfig($config, $section, $value)) {
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
        $info = [
            'host' => '',
            'org' => '',
            'repo' => '',
        ];

        $output = $this->getGitConfig('remote.'.$name.'.url');

        if (0 === stripos($output, 'http://') || 0 === stripos($output, 'https://')) {
            $url = parse_url($output);

            $info['host'] = $url['host'];
            $info['path'] = ltrim($url['path'], '/');
        } elseif (preg_match('%^(?:(?:git|ssh)://)?[^@]+@(?P<host>[^:]+):(?P<path>[^$]+)$%', $output, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        } elseif (preg_match('%^(?:(?:git|ssh)://)?([^@]+@)?(?P<host>[^/]+)/(?P<path>[^$]+)$%', $output, $match)) {
            $info['host'] = $match['host'];
            $info['path'] = $match['path'];
        }

        if (isset($info['path'])) {
            $dirs = array_slice(explode('/', $info['path']), -2, 2);

            $info['org'] = $dirs[0];
            $info['repo'] = substr($dirs[1], -4, 4) === '.git' ? substr($dirs[1], 0, -4) : $dirs[1];

            unset($info['path']);
        }

        return $info;
    }

    public function applyPatch(string $patchFile, $message, $type = 'p0')
    {
        $this->guardWorkingTreeReady();

        $this->process->mustRun(['patch', '-'.$type, '--input', $patchFile]);
        $this->process->mustRun(['git', 'commit', '-a', '--file', $this->filesystem->newTempFilename($message)]);
    }

    public function clone(string $ssh_url, string $remoteName = 'origin')
    {
        $this->process->mustRun(['git', 'clone', $ssh_url, '.']);

        if ('origin' !== $remoteName) {
            $this->process->mustRun(['git', 'remote', 'rename', 'origin', $remoteName]);
        }
    }
}
