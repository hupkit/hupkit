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

final class Config
{
    public ?string $activeHost = null;
    public ?string $activeRepository = null;

    public function __construct(private readonly array $config) {}

    public function setActiveRepository(?string $host, ?string $repository): void
    {
        $this->activeHost = $host;
        $this->activeRepository = $repository;
    }

    /**
     * @param string|string[] $keys    Single level key like 'profiles' or array-path
     *                                 like ['profiles', 'symfony-bundle']
     * @param mixed           $default Default value to use when no config is found (null)
     */
    public function get(string | array $keys, mixed $default = null)
    {
        $keys = (array) $keys;

        if (\count($keys) === 1) {
            return \array_key_exists($keys[0], $this->config) ? $this->config[$keys[0]] : $default;
        }

        $current = $this->config;

        foreach ($keys as $key) {
            if (! \is_array($current) || ! \array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Returns a config value or throws an exception when config is missing.
     *
     * @param string|string[] $keys Single level key like 'profiles' or array-path
     *                              like ['profiles', 'symfony-bundle']
     */
    public function getOrFail(string | array $keys)
    {
        $keys = (array) $keys;
        $value = $this->get($keys, $invalid = new \stdClass());

        if ($value === $invalid) {
            throw new \InvalidArgumentException(sprintf('Unable to find config "[%s]"', implode('][', $keys)));
        }

        return $value;
    }

    /**
     * Returns the first none-null configuration value.
     *
     * @param array<int, string|string[]> $keys    Array of single level keys like "adapters" or array-path
     *                                             like ['profiles', 'symfony-bundle'] to check
     * @param mixed                       $default Default value to use when no config is found (null)
     */
    public function getFirstNotNull(array $keys, mixed $default = null)
    {
        foreach ($keys as $key) {
            $value = $this->get($key);

            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Checks whether the config exists.
     *
     * @param string|string[] $keys Single level key like "profiles" or array-path
     *                              like ['profiles', 'symfony-bundle']
     */
    public function has(string | array $keys): bool
    {
        $keys = (array) $keys;

        if (\count($keys) === 1) {
            return \array_key_exists($keys[0], $this->config);
        }

        $current = $this->config;

        foreach ($keys as $key) {
            if (! \is_array($current) || ! \array_key_exists($key, $current)) {
                return false;
            }

            $current = $current[$key];
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getForRepository(string $host, string $repository, bool | null &$isLocal = false): array
    {
        $globalConfig = $this->get(['repositories', $host, 'repos', $repository], ['branches' => []]);

        if ($this->activeHost === $host && $this->activeRepository === $repository) {
            $isLocal = true;

            return $this->get(['_local'], $globalConfig);
        }

        return $globalConfig;
    }

    public function getBranchConfig(string $host, string $repository, string $branchName): BranchConfig
    {
        $repoConfig = $this->getForRepository($host, $repository, $isLocal);
        $configPath = $isLocal ? ['_local', 'branches'] : ['repositories', $host, 'repos', $repository, 'branches'];

        /**
         * @var array<string, array<string, mixed>> $branches
         */
        $branches = $repoConfig['branches'];

        /**
         * @var array<string, array<string, mixed>> $default
         */
        $default = $branches[':default'] ?? [];

        if (isset($branches['#' . $branchName]) && str_ends_with($branchName, '.x')) {
            $branchName = '#' . $branchName;
        }

        if (isset($branches[$branchName])) {
            return new BranchConfig(
                $branchName,
                $this->branchesConfig($default, $branches[$branchName]),
                configPath: array_merge($configPath, [$branchName])
            );
        }

        foreach ($branches as $configName => $config) {
            if ($configName[0] === '/') {
                $normalizedName = trim($configName, '/');

                if (preg_match(sprintf('/^(%s)$/', $normalizedName), $branchName) === 1) {
                    return new BranchConfig(
                        $branchName,
                        $this->branchesConfig($default, $config),
                        $configName,
                        array_merge($configPath, [$configName])
                    );
                }
            } elseif (preg_match('{^\d+\.([x*]|\d+)$}', $configName) === 1) {
                $normalizedName = str_replace(['x', '*', '.'], ['\d+', '\d+', '\\.'], mb_strtolower($configName));

                if (preg_match(sprintf('/^%s$/', $normalizedName), $branchName) === 1) {
                    return new BranchConfig(
                        $branchName,
                        $this->branchesConfig($default, $config),
                        $configName,
                        array_merge($configPath, [$configName])
                    );
                }
            }
        }

        $configName = isset($branches[':default']) ? ':default' : $branchName;

        return new BranchConfig(
            $branchName,
            $default,
            configName: $configName,
            configPath: array_merge($configPath, [$configName]),
        );
    }

    /**
     * @param array<string, mixed> $default
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function branchesConfig(array $default, array $config, bool $ignoreDefault = false): array
    {
        if (($config['ignore-default'] ?? false) || \count($default) === 0) {
            return $config;
        }

        unset($config['ignore-default']);

        foreach ($config as $name => $value) {
            $default[$name] = \is_array($value) ? array_merge($default[$name], $value) : $value;
        }

        return $default;
    }
}
