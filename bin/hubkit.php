#!/usr/bin/env php
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

use HubKit\Cli\HubKitApplicationConfig;
use HubKit\Container;
use HubKit\RemotesConfigParser;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Webmozart\Console\ConsoleApplication;

require __DIR__ . '/../vendor/autoload.php';

ErrorHandler::register();
DebugClassLoader::enable();

if (! file_exists(__DIR__ . '/../config.php') && file_exists(__DIR__ . '/../config.php.dist')) {
    echo sprintf('Please copy "%s.dist" to "%1$s" and configure your credentials.', __DIR__ . '/../config.php');

    exit(1);
}

if (file_exists(getcwd() . '/.hk_remotes')) {
    $remotesNames = RemotesConfigParser::parse((string) file_get_contents(getcwd() . '/.hk_remotes'));
} else {
    $remotesNames = [];
}

define('REMOTE_MAIN', $remotesNames['main'] ?? 'upstream');
define('REMOTE_FORK', $remotesNames['fork'] ?? 'origin');

$parameters = [];
$parameters['current_dir'] = getcwd() . '/';
$parameters['config_file'] = dirname(__DIR__) . '/config.php';

$cli = new ConsoleApplication(new HubKitApplicationConfig(new Container($parameters)));
$cli->run();
