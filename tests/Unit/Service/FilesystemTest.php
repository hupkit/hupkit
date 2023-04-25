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

use HubKit\Service\Filesystem;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem as SfFilesystem;

/**
 * @internal
 */
final class FilesystemTest extends TestCase
{
    use ProphecyTrait;

    /**
     * This can never be valid, which ensures the actual FS is not affected.
     *
     * @var string
     */
    public const MOCK_TMP_DIR = '{:temp:}';

    /** @var string|null */
    private $tempDir;

    /** @before */
    public function setUpTempDirectoryPath(): void
    {
        $this->tempDir = realpath(sys_get_temp_dir()) . '/hbk-fs/' . mb_substr(hash('sha256', random_bytes(8)), 0, 10);
        self::assertDirectoryDoesNotExist($this->tempDir); // Pre-condition
    }

    /** @test */
    public function it_creates_a_temp_file_with_no_contents(): void
    {
        $filesystem = new Filesystem($this->tempDir);

        $filename = $filesystem->newTempFilename();

        self::assertFileExists($filename);
        self::assertEquals(
            '',
            file_get_contents($filename)
        );
    }

    /** @test */
    public function it_creates_a_temp_file_with_contents(): void
    {
        $filesystem = new Filesystem($this->tempDir);

        $filename = $filesystem->newTempFilename('Test, test. I am Testing my tests for you');

        self::assertFileExists($filename);
        self::assertEquals(
            'Test, test. I am Testing my tests for you',
            file_get_contents($filename)
        );
    }

    /** @test */
    public function it_creates_temp_directory_when_not_existing(): void
    {
        $path = $this->getTempdirPath('split');

        $sfFilesystem = $this->prophesize(SfFilesystem::class);
        $sfFilesystem->mkdir('{:temp:}/hubkit')->shouldBeCalledOnce();
        $sfFilesystem->exists($path)->willReturn(false);
        $sfFilesystem->remove(Argument::any())->shouldNotBeCalled();
        $sfFilesystem->mkdir($path)->shouldBeCalledOnce();

        $filesystem = new Filesystem(self::MOCK_TMP_DIR, $sfFilesystem->reveal());

        self::assertEquals($path, $filesystem->tempDirectory('split'));
    }

    private function getTempdirPath(string $name): string
    {
        return self::MOCK_TMP_DIR . \DIRECTORY_SEPARATOR . 'hubkit' . \DIRECTORY_SEPARATOR . 'temp' . \DIRECTORY_SEPARATOR . $name;
    }

    /** @test */
    public function it_clears_temp_directory_when_existing(): void
    {
        $path = $this->getTempdirPath('split');

        $sfFilesystem = $this->prophesize(SfFilesystem::class);
        $sfFilesystem->mkdir('{:temp:}/hubkit')->shouldBeCalledOnce();
        $sfFilesystem->exists($path)->willReturn(true);
        $sfFilesystem->remove($path)->shouldBeCalledOnce();
        $sfFilesystem->mkdir($path)->shouldBeCalledOnce();

        $filesystem = new Filesystem(self::MOCK_TMP_DIR, $sfFilesystem->reveal());

        self::assertEquals($path, $filesystem->tempDirectory('split'));
    }

    /** @test */
    public function it_clears_temp_files(): void
    {
        $recordedRemovals = [];

        $sfFilesystem = $this->prophesize(SfFilesystem::class);
        $sfFilesystem->exists(Argument::any())->willReturn(false);
        $sfFilesystem->mkdir(
            Argument::that(
                static function ($path) {
                    (new SfFilesystem())->mkdir($path);

                    return true;
                }
            )
        )->shouldBeCalled();
        $sfFilesystem->remove(
            Argument::that(
                static function ($paths) use (&$recordedRemovals) {
                    $recordedRemovals = array_merge($recordedRemovals, $paths);

                    return true;
                }
            )
        )->shouldBeCalledOnce();
        $filesystem = new Filesystem($this->tempDir, $sfFilesystem->reveal());

        $filesystem->tempDirectory('split1');
        $filesystem->tempDirectory('split2');
        $filename = $filesystem->newTempFilename('split2');

        $filesystem->clearTempFiles();

        $path = $this->tempDir . \DIRECTORY_SEPARATOR . 'hubkit' . \DIRECTORY_SEPARATOR . 'temp' . \DIRECTORY_SEPARATOR;
        self::assertEquals(
            [
                $path . 'split1',
                $path . 'split2',
                $filename,
            ],
            $recordedRemovals
        );
    }
}
