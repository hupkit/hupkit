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

namespace HubKit\Service\Git;

use HubKit\Exception\GitFileNotFound;
use HubKit\Service\CliProcess;
use HubKit\StringUtil;

/**
 * The GitFileReader allows get access to files in another branch.
 *
 * The main purpose of this service is get access to the `_hubkit` configuration branch
 * for loading configuration from a central source that is not affected by branch switching.
 */
class GitFileReader
{
    public function __construct(
        private readonly GitBranch $gitBranch,
        private readonly GitConfig $gitConfig,
        private readonly CliProcess $process,
        private readonly GitTempRepository $gitTempRepository
    ) {}

    /**
     * @param string $path Path relative to repository root
     */
    public function fileExists(string $branch, string $path): bool
    {
        if (! $this->gitBranch->isGitDir()) {
            return false;
        }

        if (! $this->gitBranch->branchExists($branch)) {
            return false;
        }

        return $this->fileExistsAtRef($branch, $path);
    }

    private function fileExistsAtRef(string $ref, string $path): bool
    {
        $files = array_map(StringUtil::normalizePath(...),
            StringUtil::splitLines(
                $this->process->mustRun(
                    [
                        'git',
                        'ls-tree',
                        '-r',
                        $ref,
                        '--name-only',
                    ]
                )->getOutput()
            ));

        return \in_array($path, $files, true);
    }

    /**
     * Returns the location to a temp-file containing the contents of file in Git vcs.
     *
     * Note: This makes a checkout of the branch into a temporary location,
     * and then returns the full path the file.
     *
     * @param string $path Path relative to repository root
     */
    public function getFile(string $branch, string $path): string
    {
        if (! $this->fileExists($branch, $path)) {
            throw GitFileNotFound::atBranch($branch, $path);
        }

        return $this->gitTempRepository->getLocal(mb_substr($this->gitBranch->getGitDirectory(), 0, -5), $branch) . \DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param string $path Path relative to repository root
     */
    public function fileExistsAtRemote(string $remote, string $branch, string $path): bool
    {
        if (! $this->gitBranch->isGitDir()) {
            return false;
        }

        if (! $this->gitBranch->remoteBranchExists($remote, $branch)) {
            return false;
        }

        return $this->fileExistsAtRef('refs/remotes/' . $remote . '/' . $branch, $path);
    }

    /**
     * Returns the location to a temp-file containing the contents of file in Git vcs.
     *
     * Note: This makes a checkout of the branch into a temporary location,
     * and then returns the full path the file.
     *
     * @param string $path Path relative to repository root
     */
    public function getFileAtRemote(string $remote, string $branch, string $path): string
    {
        if (! $this->fileExistsAtRemote($remote, $branch, $path)) {
            throw GitFileNotFound::atRemote($remote, $branch, $path);
        }

        return $this->gitTempRepository->getRemote($this->gitConfig->getLocal('remote.' . $remote . '.url'), $branch) . \DIRECTORY_SEPARATOR . $path;
    }
}
