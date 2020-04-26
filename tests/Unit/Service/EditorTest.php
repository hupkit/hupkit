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

namespace HubKit\Tests\Unit\Service;

use HubKit\Service\CliProcess;
use HubKit\Service\Editor;
use HubKit\Service\Filesystem;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Process\Process;

final class EditorTest extends TestCase
{
    /** @test */
    public function opens_editor_with_string_contents(): void
    {
        if (false === $this->isTtySupported()) {
            $this->markTestSkipped('No TTY support');
        }

        $process = $this->createProcessSpy($processCmd);
        $filesystem = $this->createFilesystemSpy($tempFile);
        $editor = $this->createEditorWithMockEditor($process, $filesystem);

        $editor->fromString('Some contents go here.');

        self::assertFileExists($tempFile);
        self::assertEquals(file_get_contents($tempFile), 'Some contents go here.');
        self::assertEquals("'vim-or-die' '".$tempFile."'", $processCmd);
    }

    private function createFilesystemSpy(&$tempFile): Filesystem
    {
        $filesystemProphecy = $this->prophesize(Filesystem::class);
        $filesystemProphecy->newTempFilename(Argument::any())->will(
            function ($args) use (&$tempFile) {
                return $tempFile = (new Filesystem())->newTempFilename($args[0]);
            }
        );

        return $filesystemProphecy->reveal();
    }

    private function createProcessSpy(&$processCmd): CliProcess
    {
        $processProphecy = $this->prophesize(CliProcess::class);
        $processProphecy
            ->startAndWait(
                Argument::that(
                    function (Process $process) use (&$processCmd) {
                        $processCmd = $process->getCommandLine();

                        return true;
                    }
                )
            )->shouldBeCalled();

        return $processProphecy->reveal();
    }

    private function createEditorWithMockEditor($process, $filesystem): Editor
    {
        return new class($process, $filesystem) extends Editor {
            protected function getEditorExecutable(): string
            {
                return 'vim-or-die';
            }
        };
    }

    /** @test */
    public function opens_editor_with_string_contents_and_instructions(): void
    {
        if (false === $this->isTtySupported()) {
            $this->markTestSkipped('No TTY support');
        }

        $process = $this->createProcessSpy($processCmd);
        $filesystem = $this->createFilesystemSpy($tempFile);
        $editor = $this->createEditorWithMockEditor($process, $filesystem);

        $editor->fromStringWithInstructions('Some contents go here.', 'Release v2.0');

        self::assertFileExists($tempFile);
        self::assertEquals(file_get_contents($tempFile), "# THIS LINE IS AUTOMATICALLY REMOVED; Release v2.0\n\nSome contents go here.");
        self::assertEquals("'vim-or-die' '".$tempFile."'", $processCmd);
    }

    /** @test */
    public function opens_editor_with_string_contents_and_instructions_removed(): void
    {
        if (false === $this->isTtySupported()) {
            $this->markTestSkipped('No TTY support');
        }

        $filesystem = $this->createFilesystemSpy($tempFile);
        $process = $this->createProcessModifierSpy($processCmd, $tempFile, '# THIS LINE IS Some contents go here.');
        $editor = $this->createEditorWithMockEditor($process, $filesystem);

        $editor->fromStringWithInstructions('Some contents go here.', 'Release v2.0');

        self::assertFileExists($tempFile);
        self::assertEquals(file_get_contents($tempFile), '# THIS LINE IS Some contents go here.');
        self::assertEquals("'vim-or-die' '".$tempFile."'", $processCmd);
    }

    private function createProcessModifierSpy(&$processCmd, &$tempFile, string $contents): CliProcess
    {
        $processProphecy = $this->prophesize(CliProcess::class);
        $processProphecy
            ->startAndWait(
                Argument::that(
                    function (Process $process) use (&$processCmd, &$tempFile, $contents) {
                        $processCmd = $process->getCommandLine();

                        file_put_contents($tempFile, $contents);

                        return true;
                    }
                )
            )->shouldBeCalled();

        return $processProphecy->reveal();
    }

    /** @test */
    public function it_aborts_when_contents_are_empty(): void
    {
        $editor = new Editor($this->createMock(CliProcess::class), $this->createMock(Filesystem::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No content found. User aborted.');

        $editor->abortWhenEmpty(' ');
    }

    private function isTtySupported(): bool
    {
        return (bool) @proc_open('echo 1 >/dev/null', [['file', '/dev/tty', 'r'], ['file', '/dev/tty', 'w'], ['file', '/dev/tty', 'w']], $pipes);
    }
}
