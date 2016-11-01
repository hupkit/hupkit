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

use HubKit\Cli\HubKitApplicationConfig;
use HubKit\Container;

require __DIR__.'/../vendor/autoload.php';

\Symfony\Component\Debug\ErrorHandler::register();
\Symfony\Component\Debug\DebugClassLoader::enable();

if (!file_exists(__DIR__.'/../config.php') && file_exists(__DIR__.'/../config.php.dist')) {
    throw new \InvalidArgumentException(
        sprintf('Please copy "%s.dist" to "%$1s" and change the API token.', __DIR__.'/../config.php')
    );
}

$parameters = [];
$parameters['current_dir'] = getcwd().'/';
$parameters['config_dir'] = dirname(__DIR__).'/config.php';

$cli = new \Webmozart\Console\ConsoleApplication(new HubKitApplicationConfig(new Container($parameters)));
$cli->run();
