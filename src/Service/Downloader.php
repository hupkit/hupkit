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

use GuzzleHttp\ClientInterface;
use Symfony\Component\Filesystem\Filesystem as SfFilesystem;
use Webmozart\Console\Api\IO\IO;

/**
 * The DownloadHelper helps with downloading files.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class Downloader
{
    private $fs;
    private $filesystemHelper;
    private $guzzle;
    private $io;

    public function __construct(Filesystem $filesystemHelper, ClientInterface $guzzle, IO $io)
    {
        $this->fs = new SfFilesystem();
        $this->filesystemHelper = $filesystemHelper;
        $this->guzzle = $guzzle;
        $this->io = $io;
    }

    /**
     * Download a file from the URL to the destination.
     *
     * @param string $url Fully qualified URL to the file
     *
     * @return string
     */
    public function downloadFile(string $url): string
    {
        $this->io->writeLine(sprintf("\n Downloading %s...\n", $url), IO::VERBOSE);

        $response = $this->guzzle->request('GET', $url);
        $tmpFile = $this->filesystemHelper->newTempFilename();

        $this->fs->dumpFile($tmpFile, (string) $response->getBody());

        return $tmpFile;
    }
}
