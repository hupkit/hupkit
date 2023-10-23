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

namespace HubKit\Service\Git;

use HubKit\Service\CliProcess;
use HubKit\Service\Filesystem;
use HubKit\StringUtil;
use Symfony\Component\Process\Process;

/**
 * The GitTempRepository creates/provides a temporary Git working directory for a repository.
 */
class GitTempRepository
{
    public function __construct(
        private readonly CliProcess $process,
        private readonly Filesystem $filesystem
    ) {}

    public function getLocal(string $directory, string $branch = null): string
    {
        return $this->getRemote('file://' . $directory, $branch);
    }

    public function getRemote(string $repositoryUrl, string $branch = null): string
    {
        $tempdir = $this->filesystem->storageTempDirectory('repo_' . sha1($repositoryUrl), false, $exists);

        if (! $exists) {
            $this->process->mustRun(['git', 'clone', '--no-checkout', '--origin', 'origin', $repositoryUrl, $tempdir]);
        } else {
            $this->process->mustRun(new Process(['git', 'fetch', '--tags', 'origin'], $tempdir));
            $this->process->mustRun(new Process(['git', 'reset', '--hard'], $tempdir)); // Ensure the repository state is clean.
        }

        if ($branch !== null) {
            $this->checkout($tempdir, $branch);
        }

        return $tempdir;
    }

    private function checkout(string $directory, string $branchName): void
    {
        if ($this->branchExists($directory, $branchName)) {
            $this->process->mustRun(new Process(['git', 'checkout', $branchName], $directory));
            $this->process->mustRun(new Process(['git', 'reset', '--hard', 'remotes/origin/' . $branchName], $directory));

            return;
        }

        $this->process->mustRun(new Process(['git', 'checkout', 'remotes/origin/' . $branchName, '-b', $branchName], $directory));
    }

    private function branchExists(string $directory, string $branch): bool
    {
        $branches = StringUtil::splitLines(
            $this->process->mustRun(new Process(['git', 'for-each-ref', '--format', '%(refname:short)', 'refs/heads/'], $directory))->getOutput()
        );

        return \in_array($branch, $branches, true);
    }
}
