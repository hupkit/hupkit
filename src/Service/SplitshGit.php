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

use HubKit\Service\Git\GitTempRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class SplitshGit
{
    public function __construct(
        private Git $git,
        private CliProcess $process,
        private LoggerInterface $logger,
        private GitTempRepository $gitTempRepository,
        private ?string $executable
    ) {
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
     * @return array{0: string, 1: string, 2: string}|null Information of the split ['commit-hash', 'url', 'repository-location']]
     *                                                     or null when prefix doesn't exist
     */
    public function splitTo(string $targetBranch, string $prefix, string $url): ?array
    {
        if (! file_exists(getcwd() . '/' . $prefix) || ! is_dir(getcwd() . '/' . $prefix)) {
            $this->logger->warning('Prefix directory "{prefix}" for "{url}" does not exist in the local repository.', ['prefix' => $prefix, 'url' => $url]);
            $this->logger->warning('The behaviour for missing directories will change in Hubkit v2.0 and this warning will produce an fatal error. Set the split for this destination to "false" instead');

            return null;
        }

        $tempDir = $this->gitTempRepository->getRemote($url, $targetBranch);
        $sha = trim($this->process->mustRun([$this->executable, '--prefix', $prefix])->getOutput());

        // NOTE: Always perform the push as git-splitsh in some cases don't produce a new commit as there was already one
        //
        // Push directly to the temp repository, Git allows to push without a remote name.
        // This is much safer than keeping all the remotes local as this fails with git update (puling-in conflicting tags)
        $this->git->pushToRemote('file://' . $tempDir, $sha . ':refs/heads/' . $targetBranch);

        $this->process->mustRun(new Process(['git', 'push', 'origin', $targetBranch . ':refs/heads/' . $targetBranch], $tempDir));

        return [$sha, $url, $tempDir];
    }

    /**
     * Synchronize the source tag to split repositories.
     *
     * This method re-uses the information provided by splitTo().
     * Existing tags are silently ignored.
     *
     * @param string                                             $versionStr Version (without prefix) for the tag name
     * @param array<string, array{0: string, 1: string, 2: int}> $targets    Targets to tag and push as
     *                                                                       ['repository-location' => ['commit-hash', 'url', 'commits count']]
     */
    public function syncTags(string $versionStr, string $branch, array $targets): void
    {
        foreach ($targets as [$targetCommit, $url]) {
            $this->syncTag($versionStr, $url, $branch, $targetCommit);
        }
    }

    /**
     * Synchronize the source tag to split repositories.
     *
     * This method re-uses the information provided by splitTo().
     * Existing tags are silently ignored.
     *
     * @param string $versionStr Version (without prefix) for the tag name
     */
    public function syncTag(string $versionStr, string $url, string $branch, string $targetCommit): void
    {
        $tempGitDir = $this->gitTempRepository->getRemote($url, $branch);

        $this->process->run(new Process(['git', 'tag', 'v' . $versionStr, $targetCommit, '-s', '-m', 'Release ' . $versionStr], $tempGitDir));
        $this->process->run(new Process(['git', 'push', 'origin', 'v' . $versionStr], $tempGitDir));
    }

    public function checkPrecondition(): void
    {
        if ($this->executable === null) {
            throw new \RuntimeException('Unable to find "splitsh-lite" in PATH.');
        }

        if (! $this->git->isGitDir()) {
            throw new \RuntimeException(
                'Unable to perform split operation. Requires Git root directory of the repository.'
            );
        }
    }
}
