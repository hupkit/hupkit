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
     * Launch an external editor and open a temporary file containing the $contents value.
     */
    public function fromString(string $contents, bool $abortOnEmpty = true, string $instructions = ''): string
    {
        $editor = $this->getEditorExecutable();

        if ('' !== $instructions) {
            $instructions = "# THIS LINE IS AUTOMATICALLY REMOVED; $instructions\n\n";
            $contents = $instructions.$contents;
        }

        $tmpName = $this->filesystem->newTempFilename($contents);

        $process = new Process([$editor, $tmpName]);
        $process->setTimeout(null);

        $this->process->mustRun($process);
        $contents = file_get_contents($tmpName);

        if ('' !== $instructions) {
            $contents = preg_replace("/^# THIS LINE IS AUTOMATICALLY REMOVED;(.++)(\r?\n)/i", '', $contents);
        }

        if ($abortOnEmpty) {
            $this->abortWhenEmpty($contents);
        }

        return $contents;
    }

    public function fromStringWithInstructions(string $contents, string $instructions): string
    {
        return $this->fromString($contents, false, $instructions);
    }

    public function abortWhenEmpty(string $contents): string
    {
        if ('' === trim($contents)) {
            throw new \RuntimeException('No content found. User aborted.');
        }

        return $contents;
    }

    protected function getEditorExecutable(): string
    {
        $editor = getenv('EDITOR');

        if (false === $editor) {
            throw new \RuntimeException('No EDITOR environment variable set.');
        }

        return (string) $editor;
    }
}
