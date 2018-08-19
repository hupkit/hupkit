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

use Symfony\Component\Process\Exception\ProcessFailedException;

class SplitshGit
{
    private $git;
    private $process;
    private $executable;
    private $filesystem;

    public function __construct(Git $git, CliProcess $process, Filesystem $filesystem, ?string $executable)
    {
        $this->git = $git;
        $this->process = $process;
        $this->filesystem = $filesystem;
        $this->executable = $executable;
    }

    /**
     * Split the prefix directory into another repository.
     *
     * Returns information of the spit in the following format:
     * [remote-name => [sha1, git-url, commits-count]]
     *
     * The sha1 hash is the last commit, even when when no (new) commits
     * were detected.
     *
     * Note: When the prefix directory doesn't exist it's ignored (returns null).
     *
     * @param string $targetBranch Target branch to push to
     * @param string $prefix       Directory prefix, relative to the root directory
     * @param string $url          Git supported URL for pushing the commits
     *
     * @return array|null Information of the split or null when prefix doesn't exist
     */
    public function splitTo(string $targetBranch, string $prefix, string $url): ?array
    {
        if (!file_exists(getcwd().'/'.$prefix) || !is_dir(getcwd().'/'.$prefix)) {
            return null;
        }

        $process = $this->process->mustRun([$this->executable, '--prefix', $prefix]);
        $commits = (int) explode(' ', trim($process->getErrorOutput()))[0];
        $sha = trim($process->getOutput());

        $remoteName = '_'.Git::getGitUrlInfo($url)['repo'];
        $this->git->ensureRemoteExists($remoteName, $url);
        $tempBranchName = null;

        if ($commits > 0) {
            try {
                $this->git->pushToRemote($remoteName, $sha.':'.$targetBranch);
            } catch (ProcessFailedException $e) {
                // Failed to push. If remote branch is missing Git fails.
                if ($this->git->remoteBranchExists($remoteName, $targetBranch)) {
                    throw $e;
                }

                $this->git->checkout($sha);
                $this->git->checkout($tempBranchName = '_tmp_'.$sha, true);
                $this->git->pushToRemote($remoteName, $tempBranchName.':'.$targetBranch);
            } finally {
                if (null !== $tempBranchName) {
                    $this->git->checkout($targetBranch);
                    $this->git->deleteBranch($tempBranchName, true);
                }
            }
        }

        return [$remoteName => [$sha, $url, $commits]];
    }

    /**
     * Synchronize the source tag to split repositories.
     *
     * This method re-uses the information provided by splitTo().
     * Existing tags are silently ignored.
     *
     * @param string $versionStr Version (without prefix) for the tag name
     * @param array  $targets    Targets to tag and push as ['remote-name' => ['sha1-hash', 'url', 'commits count']]
     */
    public function syncTags(string $versionStr, string $branch, array $targets): void
    {
        $tempDir = $this->filesystem->tempDirectory('split');
        $filesystem = $this->filesystem->getFilesystem();
        $currentDir = getcwd();

        // Create a temp clone of the split-repository and tag a release (specific to the split).
        // All temp directories are automatically removed afterwards.

        foreach ($targets as $remote => list($targetCommit, $url)) {
            $filesystem->mkdir($tempDir.'/'.$remote);

            if (!$this->filesystem->chdir($tempDir.'/'.$remote)) {
                throw new \RuntimeException('Unable to change to temp repository. Aborting.');
            }

            $this->git->clone($url, 'origin', 200);
            $this->git->checkout($branch);

            try {
                $this->process->mustRun(['git', 'tag', 'v'.$versionStr, $targetCommit, '-s', '-m', 'Release '.$versionStr]);
            } catch (\Exception $e) {
                // no-op. Ignore and try to push.
            }

            $this->process->run(['git', 'push', 'origin', 'v'.$versionStr]);
        }

        $this->filesystem->chdir($currentDir);
    }

    public function checkPrecondition(): void
    {
        if (null === $this->executable) {
            throw new \RuntimeException('Unable to find "splitsh-lite" in PATH.');
        }

        if (!$this->git->isGitDir()) {
            throw new \RuntimeException(
                'Unable to perform split operation. Requires Git root directory of the repository.'
            );
        }
    }
}
