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

require __DIR__ . '/../vendor/autoload.php';

// Isolate the tests from the developer's global Git configuration
// (GPG signing, editor, missing author.name, etc.) by using a fixed one.
$gitConfig = __DIR__ . '/Fixtures/gitconfig';

putenv('GIT_CONFIG_GLOBAL=' . $gitConfig);
$_ENV['GIT_CONFIG_GLOBAL'] = $_SERVER['GIT_CONFIG_GLOBAL'] = $gitConfig;
