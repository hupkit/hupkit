#!/usr/bin/env php
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
    echo sprintf('Please copy "%s.dist" to "%1$s" and configure your credentials.', __DIR__.'/../config.php');
    exit(1);
}

$parameters = [];
$parameters['current_dir'] = getcwd().'/';
$parameters['config_file'] = dirname(__DIR__).'/config.php';

$cli = new \Webmozart\Console\ConsoleApplication(new HubKitApplicationConfig(new Container($parameters)));
$cli->run();
