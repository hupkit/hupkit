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

use Symfony\Component\Process\Process;

class Editor
{
    private $process;
    private $filesystem;

    public function __construct(CliProcess $process, Filesystem $filesystem)
    {
        $this->process = $process;
        $this->filesystem = $filesystem;
    }

    /**
     * Launch an external editor and open a temporary
     * file containing the given string value.
     *
     * @param string $contents
     * @param bool   $abortOnEmpty
     *
     * @return string
     */
    public function fromString(string $contents, bool $abortOnEmpty = true, string $instructions = null)
    {
        if (false === $editor = getenv('EDITOR')) {
            throw new \RuntimeException('No EDITOR environment variable set.');
        }

        if (null !== $instructions) {
            $instructions = "# THIS LINE IS AUTOMATICALLY AND REMOVED; $instructions\n\n";
            $contents = $instructions.$contents;
        }

        $tmpName = $this->filesystem->newTempFilename($contents);

        $process = new Process($editor.' '.escapeshellarg($tmpName));
        $process->setTimeout(null);

        $this->process->mustRun($process);
        $contents = file_get_contents($tmpName);

        if (null !== $instructions) {
            $contents = preg_replace("/^# THIS LINE IS AUTOMATICALLY AND REMOVED;(.++)(\r?\n)/i", '', $contents);
        }

        if ($abortOnEmpty && '' === trim($contents)) {
            throw new \RuntimeException('No content found. User aborted.');
        }

        return $contents;
    }
}
