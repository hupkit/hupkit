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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Rollerworks\Component\Version\Version;

class ReleaseHooks
{
    /**
     * @var string|null
     */
    private readonly string | bool $cwd;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Git $git,
        private readonly LoggerInterface $logger,
        ?string $cwd = null
    ) {
        $this->cwd = $cwd ?? getcwd();
    }

    public function preRelease(Version $version, string $branch, ?string $releaseTitle, string $changelog): ?string
    {
        return $this->executeScript('pre-release', $version, $branch, $releaseTitle, $changelog);
    }

    public function postRelease(Version $version, string $branch, ?string $releaseTitle, string $changelog): ?string
    {
        return $this->executeScript('post-release', $version, $branch, $releaseTitle, $changelog);
    }

    private function executeScript(string $type, Version $version, string $branch, ?string $releaseTitle, string $changelog): ?string
    {
        $scriptFile = $this->cwd . '/.hubkit/' . $type . '.php';

        if (! file_exists($scriptFile)) {
            $this->logger->debug('File {script} was not found. ' . $type . ' script will not be executed.', ['script' => $scriptFile]);

            return '';
        }

        $hookCallback = include $scriptFile;

        if (! \is_callable($hookCallback)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected script file "%s" to return a callable, got "%s" instead.',
                    $scriptFile,
                    \gettype($hookCallback)
                )
            );
        }

        $result = $hookCallback($this->container, $version, $branch, $releaseTitle, $changelog);

        if (! $this->git->isWorkingTreeReady()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected script file "%s" to leave a clean state after execution. Changed files must be committed by the script.',
                    $scriptFile
                )
            );
        }

        return $result;
    }
}
