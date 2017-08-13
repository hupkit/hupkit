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

namespace HubKit;

final class ConfigFactory
{
    /**
     * Schema version of the configuration.
     *
     * Needs to be updated whenever the config structure changes
     * break BC compatibility.
     */
    private const SCHEMA_VERSION = 1;

    private $currentDir;
    private $configFile;

    public function __construct(string $currentDir, string $configFile)
    {
        $this->currentDir = self::normalizePath($currentDir);
        $this->configFile = self::normalizePath($configFile);
    }

    public function create(): Config
    {
        $config = require $this->configFile;
        $config['current_dir'] = $this->currentDir;

        if (empty($config['schema_version'])) {
            $config['schema_version'] = 0;
        }

        if ($config['schema_version'] !== self::SCHEMA_VERSION) {
            throw new \RuntimeException(
                sprintf(
                    'Config schema-version mismatch, expected %s got "%s". ',
                    self::SCHEMA_VERSION,
                    $config['schema_version']
                )."\n".'If expected number is lower update HubKit else update the configuration.'
            );
        }

        return new Config($config);
    }

    private static function normalizePath(string $path = null)
    {
        if (null === $path) {
            return;
        }

        if (false === $realPath = realpath($path)) {
            throw new \InvalidArgumentException(
                sprintf('Unable to normalize path "%s", no such file or directory.', $path)
            );
        }

        return str_replace('\\', '//', $realPath);
    }
}
