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
use Psr\Log\LoggerInterface;

abstract class HookScript
{
    public function __construct(
        protected readonly GitFileReader $gitFileReader,
        protected readonly LoggerInterface $logger,
        protected ?string $cwd = null,
    ) {
        $this->cwd = $cwd ?? getcwd();
    }

    protected function findScript(string $name): ?string
    {
        if ($this->gitFileReader->fileExists('_hubkit', $name . '.php')) {
            $this->logger->debug('Hook script {name}.php was found in the "_hubkit" branch.', ['name' => $name]);

            return $this->gitFileReader->getFile('_hubkit', $name . '.php');
        }

        $scriptFile = $this->cwd . '/.hubkit/' . $name . '.php';

        if (file_exists($scriptFile)) {
            $this->logger->warning('Hook script {name}.php was found at "{script}". Move this file to the "_hubkit" configuration branch instead.', ['name' => $name, 'script' => $this->cwd . '/.hubkit']);

            trigger_deprecation('hupkit/hupkit', 'v1.2.0', 'Storing hook scripts in ".hubkit" is deprecated and will no longer work in HubKit v2.0. Use the "_hubkit" configuration branch to store hook scripts instead.');

            return $scriptFile;
        }

        return null;
    }

    protected function getHookCallback(string $scriptFile): callable
    {
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

        return $hookCallback;
    }
}
