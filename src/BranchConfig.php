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

namespace HubKit;

class BranchConfig
{
    public string $configName;

    /** @var array<int, string> */
    public array $configPath;

    /**
     * @param array<string, mixed>    $config
     * @param array<int, string>|null $configPath
     */
    public function __construct(
        public string $name,
        public array $config,
        string $configName = null,
        array $configPath = null
    ) {
        $this->configName = $configName ?? $name;
        $this->configPath = $configPath ?? [];
    }
}
