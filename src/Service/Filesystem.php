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
    private readonly string $tempdir;
    private readonly SfFilesystem $fs;
    private array $tempFilenames = [];

    public function __construct(?string $tempdir = null, ?SfFilesystem $sfFilesystem = null)
    {
        $this->fs = $sfFilesystem ?? new SfFilesystem();
        $this->tempdir = ($tempdir ?: sys_get_temp_dir()) . \DIRECTORY_SEPARATOR . 'hubkit';

        $this->fs->mkdir($this->tempdir);
    }

    /**
     * Creates a new temporary file (with the contents).
     *
     * This file is removed when clearTempFiles() is called.
     *
     * @return string The full path to the temporary file
     */
    public function newTempFilename(string $content = null): string
    {
        $tmpName = tempnam($this->tempdir, '');
        $this->tempFilenames[] = $tmpName;

        if ($content !== null) {
            file_put_contents($tmpName, $content);
        }

        return $tmpName;
    }

    /**
     * @param string|resource $content
     */
    public function dumpFile(string $filename, $content): void
    {
        if (str_starts_with($filename, './')) {
            $filename = substr_replace($filename, $this->getCwd(), 0, 1);
        }

        $this->fs->dumpFile($filename, $content);
    }

    /**
     * Creates a (new) or overwrites a temporary directory.
     *
     * Note: Unlike a regular temp file this method will remove
     * the existing temp directory (by name) if it exists.
     *
     * This directory is removed when clearTempFiles() is called.
     *
     * @return string The full path to the temporary directory
     */
    public function tempDirectory(string $name, bool $clearExisting = true, ?bool &$exists = null): string
    {
        $tmpName = $this->tempdir . \DIRECTORY_SEPARATOR . 'temp' . \DIRECTORY_SEPARATOR . $name;
        $exists = $this->fs->exists($tmpName);

        if ($clearExisting && $exists) {
            $this->fs->remove($tmpName);
        }

        $this->fs->mkdir($tmpName);
        $this->tempFilenames[] = $tmpName;

        return $tmpName;
    }

    /**
     * Creates a (new) or overwrites a temporary directory.
     *
     * Note: Unlike the tempDirectory() method this doesn't clear the directory at clearTempFiles().
     *
     * @return string The full path to the temporary directory
     */
    public function storageTempDirectory(string $name, bool $clearExisting = true, ?bool &$exists = null): string
    {
        $tmpName = $this->tempdir . \DIRECTORY_SEPARATOR . 'stor' . \DIRECTORY_SEPARATOR . $name;
        $exists = $this->fs->exists($tmpName);

        if ($clearExisting && $exists) {
            $this->fs->remove($tmpName);
        }

        $this->fs->mkdir($tmpName);

        return $tmpName;
    }

    /**
     * Remove all the temp-file that were created
     * with newTempFilename() and tempDirectory().
     */
    public function clearTempFiles(): void
    {
        $this->fs->remove($this->tempFilenames);
        $this->tempFilenames = [];
    }

    public function clearTempFolder(): void
    {
        $this->fs->remove($this->tempdir);
    }

    public function getFilesystem(): SfFilesystem
    {
        return $this->fs;
    }

    public function chdir(string $directory): bool
    {
        return chdir($directory);
    }

    public function getCwd(): string | false
    {
        return getcwd();
    }

    public function getTempdir(): string
    {
        return $this->tempdir;
    }
}
