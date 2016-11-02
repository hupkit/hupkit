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

namespace HubKit\Cli;

use HubKit\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmozart\Console\Adapter\ArgsInput;
use Webmozart\Console\Adapter\IOOutput;
use Webmozart\Console\Api\Args\Format\Argument;
use Webmozart\Console\Api\Args\Format\Option;
use Webmozart\Console\Api\Event\ConsoleEvents;
use Webmozart\Console\Api\Event\PreHandleEvent;
use Webmozart\Console\Api\Formatter\Style;
use Webmozart\Console\Config\DefaultApplicationConfig;

final class HubKitApplicationConfig extends DefaultApplicationConfig
{
    /**
     * The version of the Application.
     */
    const VERSION = '@package_version@';

    /**
     * @var Container
     */
    private $container;

    /**
     * Creates the configuration.
     *
     * @param Container $container The service container (only to be injected during tests)
     */
    public function __construct(Container $container = null)
    {
        if (null === $container) {
            if (!file_exists(__DIR__.'/../../config.php') && file_exists(__DIR__.'/../../config.php.dist')) {
                throw new \InvalidArgumentException(
                    sprintf('Please copy "%s.dist" to "%$1s" and change the API token.', __DIR__.'/../../config.php')
                );
            }

            $parameters = [];
            $parameters['current_dir'] = getcwd().'/';
            $parameters['config_dir'] = realpath(__DIR__.'/../../config.php');

            $container = new Container($parameters);
        }

        $this->container = $container;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setEventDispatcher(new EventDispatcher());

        parent::configure();

        $this
            ->setName('hubkit')
            ->setDisplayName('HubKit')

            ->setVersion(self::VERSION)
            ->setDebug('true' === getenv('HUBKIT_DEBUG'))
            ->addStyle(Style::tag('good')->fgGreen())
            ->addStyle(Style::tag('bad')->fgRed())
            ->addStyle(Style::tag('warn')->fgYellow())
            ->addStyle(Style::tag('hl')->fgGreen())
        ;

        $this->addEventListener(
            ConsoleEvents::PRE_HANDLE,
            function (PreHandleEvent $event) {
                // Set-up the IO for the Symfony Helper classes.
                if (!isset($this->container['console_io'])) {
                    $io = $event->getIO();
                    $args = $event->getArgs();

                    $input = new ArgsInput($args->getRawArgs(), $args);
                    $input->setInteractive($io->isInteractive());

                    $this->container['console_io'] = $io;
                    $this->container['console_args'] = $args;
                    $this->container['sf.console_input'] = $input;
                    $this->container['sf.console_output'] = new IOOutput($io);
                }
            }
        );

        $this->addEventListener(
            ConsoleEvents::PRE_HANDLE,
            function (PreHandleEvent $event) {
                $handler = $event->getCommand()->getConfig()->getHandler();
                $isGit = $this->container['git']->isGitDir();

                if ($handler instanceof RequiresGitRepository && !$isGit) {
                    throw new \RuntimeException(
                        'This Command can only be executed from the root of a Git repository.'
                    );
                }

                if ($isGit) {
                    $this->container['github']->autoConfigure($this->container['git']);
                }
            }
        );

        $this
            ->beginCommand('diagnose')
                ->setDescription('Manage the profiles of your project')
                ->setHandler(function () {
                    return new Handler\DiagnoseHandler(
                        $this->container['style'],
                        $this->container['config'],
                        $this->container['git'],
                        $this->container['github']
                    );
                })
            ->end()

            ->beginCommand('repository')
                ->setDescription('Manage the profiles of your project')
                ->setHandler(function () {
                    return new Handler\RepositoryHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github']
                    );
                })
                ->beginSubCommand('create')
                    ->setDescription('Create a new empty GitHub repository. Wiki and Downloads are disabled by default')
                    ->addArgument('organization', Argument::REQUIRED, 'Organization holding the repository')
                    ->addArgument('name', Argument::REQUIRED, 'The name of the repository')
                    ->addOption('no-issues', null, Option::BOOLEAN, 'Disable issues, when a global issue tracker is used')
                    ->addOption('private', null, Option::BOOLEAN, 'Create private repository (requires paid plan)')
                    ->setHandlerMethod('handleCreate')
                ->end()
            ->end()

            ->beginCommand('take-issue')
                ->setDescription('Manage the profiles of your project')
                ->addArgument('number', Argument::INTEGER, 'Number of the issue to take')
                ->addOption('base', 'b', Option::STRING | Option::OPTIONAL_VALUE, 'Base branch', 'master')
                ->setHandler(function () {
                    return new Handler\IssueTakeHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github']
                    );
                })
            ->end()

            ->beginCommand('pull-request')
                ->setHandler(function () {
                    return new Handler\PullRequestMergeHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['config'],
                        $this->container['github']
                    );
                })
                ->beginSubCommand('merge')
                    ->setDescription('Merge a pull-request using the GitHub API')
                    ->addArgument('number', Argument::REQUIRED | Argument::INTEGER, 'The name of the repository')
                    ->addOption('squash', 's', Option::BOOLEAN, 'Squash the pull-request when merging')
                    ->addOption('thanks', null, Option::OPTIONAL_VALUE | Option::STRING, 'Thank you message, @author replaced with actual pr-author', 'Thank you @author')
                    ->setHandlerMethod('handleMerge')
                ->end()
            ->end()
        ;
    }
}
