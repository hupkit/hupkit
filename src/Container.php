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
use Symfony\Component\Console\Style\SymfonyStyle;

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

            if (!empty($_SERVER['HTTP_PROXY']) || !empty($_SERVER['http_proxy'])) {
                $proxy = !empty($_SERVER['http_proxy']) ? $_SERVER['http_proxy'] : $_SERVER['HTTP_PROXY'];
                $options['proxy'] = $proxy;
            }

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

        $this['filesystem'] = function () {
            return new Service\Filesystem();
        };

        $this['downloader'] = function (Container $container) {
            return new Service\Downloader($container['filesystem'], $container['guzzle'], $container['io']);
        };

        //
        // Third-party APIs
        //

        $this['github'] = function (Container $container) {
            return new ThirdParty\GitHub(
                $container['guzzle'], $container['config']->getOrFail(['github', 'api_token'])
            );
        };
    }
}
