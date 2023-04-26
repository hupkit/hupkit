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

namespace HubKit\Cli\Handler;

use HubKit\Service\Filesystem;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

final class ClearCacheHandler
{
    public function __construct(
        private readonly SymfonyStyle $style,
        private readonly Filesystem $filesystem
    ) {
    }

    public function handle(Args $args, IO $io): int
    {
        $this->style->title('Clear Cache');

        if ($io->isVerbose()) {
            $files = 0;
            $size = 0;

            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->filesystem->getTempdir(), \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO | \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $size += $file->getSize() ?: 0;
                ++$files;
            }

            $this->style->comment(sprintf('Cache directory %s', $this->filesystem->getTempdir()));
            $this->style->comment(sprintf('Removed %s file taking-up %s of space', $files, Helper::formatMemory($size)));
        }

        $this->filesystem->clearTempFolder();
        $this->style->success('Cache was cleared.');

        return 0;
    }
}
