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

namespace HubKit\Cli\Handler;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Gitignore;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

class EditConfigHandler extends ConfigBaseHandler
{
    public function handle(Args $args, IO $io): void
    {
        $this->git->guardWorkingTreeReady();
        $this->informationHeader();

        $this->style->title('Configuration Editing');

        if ($this->git->getActiveBranchName() === '_hubkit') {
            $this->ensureRemoteIsNotDiverged();

            $this->style->info('Configuration branch already checked out.');

            return;
        }

        if (! $this->git->branchExists('_hubkit')) {
            if ($this->git->remoteBranchExists('upstream', '_hubkit')) {
                throw new \RuntimeException(
                    'The "_hubkit" branch exists remote, but the branch was not found locally.' . \PHP_EOL .
                    'Run the "sync-config" command to pull-in the remote branch.' . \PHP_EOL
                );
            }

            throw new \RuntimeException('The "_hubkit" branch does not exist yet. Run `init-config` first.');
        }

        if (getenv('HUBKIT_NO_LOCAL') !== 'true') {
            $this->ensureRemoteIsNotDiverged();
        }

        $this->ensureIgnoredFilesAreNotOverwritten();

        $activeBranch = $this->git->getActiveBranchName();

        $this->git->checkout('_hubkit');

        $this->style->success([
            'The "_hubkit" configuration branch was checked out.',
            'Make sure to add and commit once you are done.',
            sprintf('After you are done run `git checkout %s`.', $activeBranch),
            'And run the `sync-config` command to push the configuration to the upstream repository.',
        ]);
    }

    private function ensureIgnoredFilesAreNotOverwritten(): void
    {
        $configRepository = $this->tempRepository->getLocal($this->filesystem->getCwd(), '_hubkit');
        $gitIgnores = [];

        // Note that this is only works for top-level excludes, nested excludes are considered an edge-case.
        foreach (['./.gitignore', './.git/info/exclude'] as $excludeFile) {
            if ($this->filesystem->exists($excludeFile)) {
                $gitIgnores[] = Gitignore::toRegex($this->filesystem->getFileContents($excludeFile));
            }
        }

        $strip = mb_strlen($configRepository) + 1; // Path length to get relative path
        $found = [];

        foreach ($this->getFinder($gitIgnores, $configRepository) as $file) {
            $filePath = mb_substr($file->getPathname(), $strip);

            if ($this->filesystem->exists('./' . $filePath)) {
                $found[] = $filePath;
            }
        }

        if (\count($found) > 0) {
            throw new \RuntimeException(
                sprintf(
                    "One or more git-ignored files where found in the \"_hubkit\" branch, these would be overwritten when checking out.\n" .
                    "\nTemporarily move or rename these files:\n\n  * %s",
                    implode("\n  * ", $found)
                )
            );
        }
    }

    /**
     * Protected to allow testing.
     *
     * @param array<int, string> $gitIgnores
     */
    protected function getFinder(array $gitIgnores, string $configRepository): Finder
    {
        return (new Finder())
            ->files()
            ->name($gitIgnores)
            ->in($configRepository);
    }
}
