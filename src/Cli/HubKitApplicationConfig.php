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
use HubKit\Helper\BranchAliasResolver;
use HubKit\Helper\SingleLineChoiceQuestionHelper;
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

                if ($handler instanceof RequiresGitRepository && $isGit) {
                    $this->container['github']->autoConfigure($this->container['git']);
                } else {
                    $hostname = $event->getArgs()->isOptionSet('host') ? $event->getArgs()->getOption('host') : null;
                    $this->container['github']->initializeForHost($hostname);
                }
            }
        );

        $this
            ->beginCommand('self-diagnose')
                ->setDescription('Checks your system is ready to use HubKit and gives recommendations about changes you should make.')
                ->setHandler(function () {
                    return new Handler\SelfDiagnoseHandler(
                        $this->container['style'],
                        $this->container['config'],
                        $this->container['git'],
                        $this->container['github'],
                        $this->container['process']
                    );
                })
            ->end()

            ->beginCommand('repo-create')
                ->setDescription('Create a new empty GitHub repository. Wiki and Downloads are disabled by default')
                ->addArgument('full-name', Argument::REQUIRED, 'The user (org) and repository name. Eg. acme/my-repo')
                ->addOption('no-issues', null, Option::BOOLEAN, 'Disable issues, when a global issue tracker is used')
                ->addOption('private', null, Option::BOOLEAN, 'Create private repository (requires paid plan)')
                ->setHandler(function () {
                    return new Handler\RepositoryCreateHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github']
                    );
                })
            ->end()

            ->beginCommand('take')
                ->setDescription('Take an issue to work on, checks out the issue as new branch.')
                ->addArgument('number', Argument::INTEGER, 'Number of the issue to take')
                ->addOption('base', 'b', Option::STRING | Option::OPTIONAL_VALUE, 'Base branch to checkout', 'master')
                ->setHandler(function () {
                    return new Handler\TakeHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github']
                    );
                })
            ->end()

            ->beginCommand('merge')
                ->setDescription('Merge a pull request with preservation of the original title/description and comments.')
                ->addArgument('number', Argument::REQUIRED | Argument::INTEGER, 'The number of the pull request to merge')
                ->addOption('security', null, Option::BOOLEAN, 'Merge pull request as a security patch')
                ->addOption('squash', null, Option::BOOLEAN, 'Squash the pull request before merging')
                ->addOption('no-pull', null, Option::BOOLEAN, 'Skip pulling changes to your local branch')
                ->addOption('pat', null, Option::OPTIONAL_VALUE | Option::STRING, 'Thank you message, @author will be replaced with pr author(s)', 'Thank you @author')
                ->addOption('no-pat', null, Option::NO_VALUE | Option::BOOLEAN, 'Skip thank you message, cannot be used in combination with --pat')
                ->setHandler(function () {
                    return new Handler\MergeHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github'],
                        new BranchAliasResolver($this->container['style'], $this->container['git'], getcwd()),
                        new SingleLineChoiceQuestionHelper()
                    );
                })
            ->end()

            ->beginCommand('branch-alias')
                ->setDescription('Set/get the "master" branch-alias. Omit alias argument to get the current alias.')
                ->addArgument('alias', Argument::OPTIONAL | Argument::STRING, 'New alias to assign (omit to get the current alias)')
                ->setHandler(function () {
                    return new Handler\BranchAliasHandler(
                        $this->container['git']
                    );
                })
            ->end()

            ->beginCommand('changelog')
                ->setDescription('Generate a changelog with all changes between commits')
                ->addArgument('ref', Argument::OPTIONAL | Argument::STRING, 'Range reference as `base..head`')
                ->addOption('all', null, Option::NO_VALUE | Option::BOOLEAN, 'Show all categories (including empty)')
                ->addOption('oneline', null, Option::NO_VALUE | Option::BOOLEAN, 'Show changelog as singe lines without sections')
                ->setHandler(function () {
                    return new Handler\ChangelogHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github'],
                        $this->container['process']
                    );
                })
            ->end()

            ->beginCommand('upmerge')
                ->setDescription('Merges current branch to the next version branch (eg. 1.0 into 1.1)')
                ->addArgument('branch', Argument::OPTIONAL | Argument::STRING, 'Base branch to checkout and start with, uses current when omitted')
                ->addOption('all', null, Option::NO_VALUE | Option::BOOLEAN, 'Merge all version branches from lowest into highest (and finally master)')
                ->setHandler(function () {
                    return new Handler\UpMergeHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github'],
                        $this->container['process']
                    );
                })
            ->end()

            ->beginCommand('release')
                ->setDescription('Make a new release for the current branch')
                ->addArgument('version', Argument::REQUIRED | Argument::STRING, 'Version to make (supports relative, eg. minor, alpha, ...)')
                ->addOption('all-categories', null, Option::NO_VALUE | Option::BOOLEAN, 'Show all categories (including empty)')
                ->addOption('no-edit', null, Option::NO_VALUE | Option::BOOLEAN, 'Don\'t open the editor for editing the release page')
                ->addOption('pre-release', null, Option::NO_VALUE | Option::BOOLEAN, 'Mark as pre-release (not production ready)')
                ->setHandler(function () {
                    return new Handler\ReleaseHandler(
                        $this->container['style'],
                        $this->container['git'],
                        $this->container['github'],
                        $this->container['process'],
                        $this->container['editor']
                    );
                })
            ->end()
        ;
    }
}
