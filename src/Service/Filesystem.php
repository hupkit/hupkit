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

use Symfony\Component\Filesystem\Filesystem as SfFilesystem;

class Filesystem
{
    private $tempdir;
    private $tempFilenames = [];
    private $fs;

    public function __construct(string $tempdir = null)
    {
        $this->tempdir = $tempdir ?: sys_get_temp_dir();
        $this->fs = new SfFilesystem();
    }

    public function newTempFilename(string $content = null): string
    {
        $dir = $this->tempdir.DIRECTORY_SEPARATOR.'hubkit';
        $this->fs->mkdir($dir);

        $tmpName = tempnam($dir, '');
        $this->tempFilenames[] = $tmpName;

        if (null !== $content) {
            file_put_contents($tmpName, $content);
        }

        return $tmpName;
    }

    public function tempDirectory(string $name, bool $clearExisting = true): string
    {
        $tmpName = $this->tempdir.DIRECTORY_SEPARATOR.'hubkit'.DIRECTORY_SEPARATOR.$name;

        if ($clearExisting && $this->fs->exists($tmpName)) {
            $this->fs->remove($tmpName);
        }

        $this->fs->mkdir($tmpName);
        $this->tempFilenames[] = $tmpName;

        return $tmpName;
    }

    /**
     * Remove all the temp-file that were created
     * with newTempFilename().
     */
    public function clearTempFiles()
    {
        $this->fs->remove($this->tempFilenames);
    }

    public function getFilesystem(): SfFilesystem
    {
        return $this->fs;
    }

    public function chdir(string $directory): bool
    {
        return chdir($directory);
    }
}
