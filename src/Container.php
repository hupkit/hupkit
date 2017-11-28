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

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;

class Container extends \Pimple\Container
{
    public function __construct(array $values = [])
    {
        parent::__construct($values);

        $this['config'] = function (Container $container) {
            return (new ConfigFactory($container['current_dir'], $container['config_file']))->create();
        };

        $this['guzzle'] = function (Container $container) {
            $options = [];

            if ($container['console_io']->isDebug()) {
                $options['debug'] = true;
            }

            return new GuzzleClient($options);
        };

        $this['style'] = function (Container $container) {
            return new SymfonyStyle($container['sf.console_input'], $container['sf.console_output']);
        };

        $this['process'] = function (Container $container) {
            return new Service\CliProcess($container['sf.console_output']);
        };

        $this['git'] = function (Container $container) {
            return new Service\Git($container['process'], $container['filesystem'], $container['style']);
        };

        $this['splitsh_git'] = function (Container $container) {
            return new Service\SplitshGit(
                $container['git'],
                $container['process'],
                $container['filesystem'],
                (new ExecutableFinder())->find('splitsh-lite')
            );
        };

        $this['filesystem'] = function () {
            return new Service\Filesystem();
        };

        $this['downloader'] = function (Container $container) {
            return new Service\Downloader($container['filesystem'], $container['guzzle'], $container['io']);
        };

        $this['editor'] = function (Container $container) {
            return new Service\Editor($container['process'], $container['filesystem']);
        };

        //
        // Third-party APIs
        //

        $this['github'] = function (Container $container) {
            return new Service\GitHub(new GuzzleClientAdapter($container['guzzle']), $container['config']);
        };
    }
}
