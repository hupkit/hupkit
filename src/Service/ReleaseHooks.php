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

namespace HubKit\Service;

use HubKit\Service\Git\GitFileReader;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Rollerworks\Component\Version\Version;

class ReleaseHooks extends HookScript
{
    public function __construct(
        GitFileReader $gitFileReader,
        LoggerInterface $logger,
        private readonly ContainerInterface $container,
        private readonly Git $git,
        string $cwd = null
    ) {
        parent::__construct($gitFileReader, $logger, $cwd);
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
        $scriptFile = $this->findScript($type);

        if ($scriptFile === null) {
            return null;
        }

        $hookCallback = $this->getHookCallback($scriptFile);
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
