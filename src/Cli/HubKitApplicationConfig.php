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

namespace HubKit\Cli;

use HubKit\Container;
use HubKit\Helper\BranchAliasResolver;
use HubKit\Helper\SingleLineChoiceQuestionHelper;
use Symfony\Component\Console\Logger\ConsoleLogger;
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
    private const VERSION = '@package_version@';

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
        if ($container === null) {
            if (! file_exists(__DIR__ . '/../../config.php') && file_exists(__DIR__ . '/../../config.php.dist')) {
                throw new \InvalidArgumentException(
                    sprintf('Please copy "%s.dist" to "%1$s" and change the API token.', __DIR__ . '/../../config.php')
                );
            }

            $parameters = [];
            $parameters['current_dir'] = getcwd() . '/';
            $parameters['config_dir'] = realpath(__DIR__ . '/../../config.php');

            $container = new Container($parameters);
        }

        $this->container = $container;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setEventDispatcher(new EventDispatcher());

        parent::configure();

        $this
            ->setName('hubkit')
            ->setDisplayName('HubKit')
            ->setVersion(self::VERSION)
            ->setDebug(getenv('HUBKIT_DEBUG') === 'true')
            ->addStyle(Style::tag('good')->fgGreen())
            ->addStyle(Style::tag('bad')->fgRed())
            ->addStyle(Style::tag('warn')->fgYellow())
            ->addStyle(Style::tag('hl')->fgGreen())
        ;

        $this->addEventListener(
            ConsoleEvents::PRE_HANDLE,
            function (PreHandleEvent $event): void {
                // Set-up the IO for the Symfony Helper classes.
                if (! isset($this->container['console_io'])) {
                    $io = $event->getIO();
                    $args = $event->getArgs();

                    $input = new ArgsInput($args->getRawArgs(), $args);
                    $input->setInteractive($io->isInteractive());

                    $this->container['console_io'] = $io;
                    $this->container['console_args'] = $args;
                    $this->container['sf.console_input'] = $input;
                    $this->container['sf.console_output'] = new IOOutput($io);
                    $this->container['logger'] = new ConsoleLogger($this->container['sf.console_output']);
                }
            }
        );

        $this->addEventListener(
            ConsoleEvents::PRE_HANDLE,
            function (PreHandleEvent $event): void {
                $handler = $event->getCommand()->getConfig()->getHandler();
                $isGit = $this->container['git']->isGitDir();

                if ($handler instanceof RequiresGitRepository && ! $isGit) {
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
            ->setDescription('Checks your system is ready to use HuPKit and gives recommendations about changes you should make.')
            ->setHandler(function () {
                return new Handler\SelfDiagnoseHandler(
                    $this->container['style'],
                    $this->container['config'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['process'],
                    $this->container['git.file_reader'],
                );
            })
            ->end()

            ->beginCommand('clear-cache')
            ->setDescription('Clears the HuPKit cache directory. Run this command to free-up space or resolve recurring errors')
            ->setHandler(function () {
                return new Handler\ClearCacheHandler(
                    $this->container['style'],
                    $this->container['filesystem'],
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

            ->beginCommand('split-repo')
            ->setDescription('Split the repository into configured targets. Requires "repos" is configured in config.php')
            ->setHelp('Splits the current repository into configured targets, use the `split-create` command to set-up missing repositories')
            ->addArgument('branch', Argument::OPTIONAL | Argument::STRING, 'Branch to checkout and split from, uses current when omitted')
            ->addOption('dry-run', null, Option::NO_VALUE | Option::BOOLEAN, 'Show which operations would have been performed (without actually splitting)')
            ->addOption('prefix', null, Option::REQUIRED_VALUE | Option::STRING, 'Split only a specific prefix instead of all')
            ->setHandler(function () {
                return new Handler\SplitRepoHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['branch_splitsh_git']
                );
            })
            ->end()

            ->beginCommand('split-create')
            ->setDescription('Create repositories for the current repository configured split targets (already existing repositories are ignored). Requires "repos" is configured in config.php')
            ->setHandler(function () {
                return new Handler\SplitCreatedHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                );
            })
            ->end()

            ->beginCommand('take')
            ->setDescription('Take an issue to work on, checks out the issue as new branch.')
            ->addArgument('number', Argument::INTEGER, 'Number of the issue to take')
            ->addOption('base', 'b', Option::STRING | Option::OPTIONAL_VALUE, 'Base branch to checkout')
            ->setHandler(function () {
                return new Handler\TakeHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config']
                );
            })
            ->end()

            ->beginCommand('checkout')
            ->setDescription('Checkout a pull request as local branch. Allows to push changes (unless disabled by author)')
            ->addArgument('number', Argument::INTEGER, 'Number of the pull request to checkout')
            ->setHandler(function () {
                return new Handler\CheckoutHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['process']
                );
            })
            ->end()

            ->beginCommand('init-config')
            ->setDescription('Initialize "_hubkit" configuration branch')
            ->setHandler(function () {
                return new Handler\InitConfigHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['filesystem'],
                    $this->container['git.temp_repository'],
                    $this->container['process'],
                );
            })
            ->end()

            ->beginCommand('edit-config')
            ->setDescription('Edit the contents of the "_hubkit" configuration branch')
            ->setHandler(function () {
                return new Handler\EditConfigHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['filesystem'],
                    $this->container['git.temp_repository'],
                );
            })
            ->end()

            ->beginCommand('sync-config')
            ->setDescription('Synchronizes "_hubkit" configuration branch with the upstream')
            ->setHandler(function () {
                return new Handler\SynchronizeConfigHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                );
            })
            ->end()

            ->beginCommand('merge')
            ->setDescription('Merge a pull request with preservation of the original title/description and comments.')
            ->addArgument('number', Argument::REQUIRED | Argument::INTEGER, 'The number of the pull request to merge')
            ->addOption('security', null, Option::BOOLEAN, 'Merge pull request as a security patch')
            ->addOption('squash', null, Option::BOOLEAN, 'Squash the pull request before merging')
            ->addOption('no-pull', null, Option::BOOLEAN, 'Skip pulling changes to your local branch')
            ->addOption('no-split', null, Option::BOOLEAN, 'Skip splitting of monolith repository')
            ->addOption('no-cleanup', null, Option::BOOLEAN, 'Skip clean-up of feature branch (if present)')
            ->addOption('pat', null, Option::OPTIONAL_VALUE | Option::STRING, 'Thank you message, @author will be replaced with pr author(s)', 'Thank you @author')
            ->addOption('no-pat', null, Option::NO_VALUE | Option::BOOLEAN, 'Skip thank you message, cannot be used in combination with --pat')
            ->setHandler(function () {
                return new Handler\MergeHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    new BranchAliasResolver($this->container['style'], $this->container['git'], getcwd()),
                    new SingleLineChoiceQuestionHelper(),
                    $this->container['branch_splitsh_git']
                );
            })
            ->end()

            ->beginCommand('switch-base')
            ->setDescription('Switch the base of a pull request (and perform a rebase to prevent unwanted commits)')
            ->addArgument('number', Argument::REQUIRED | Argument::INTEGER, 'The number of the pull request to switch')
            ->addArgument('new-base', Argument::REQUIRED | Argument::STRING, 'New base of the pull-request (must exist in remote "upstream")')
            ->addOption('skip-help', null, Option::NO_VALUE | Option::BOOLEAN, 'Skip the help message posted to the PR.')
            ->setHandler(function () {
                return new Handler\SwitchBaseHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['process']
                );
            })
            ->end()

            ->beginCommand('branch-alias')
            ->setDescription('Set/get the "primary" branch-alias. Omit alias argument to get the current alias.')
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
                    $this->container['config']
                );
            })
            ->end()

            ->beginCommand('upmerge')
            ->setDescription('Merges current branch to the next version branch (eg. 1.0 into 1.1)')
            ->addArgument('branch', Argument::OPTIONAL | Argument::STRING, 'Base branch to checkout and start with, uses current when omitted')
            ->addOption('all', null, Option::NO_VALUE | Option::BOOLEAN, 'Merge all version branches from lowest into highest')
            ->addOption('dry-run', null, Option::NO_VALUE | Option::BOOLEAN, 'Show which operations would have been performed (without actually merging)')
            ->addOption('no-split', null, Option::NO_VALUE | Option::BOOLEAN, 'Skip splitting of repositories (when they are configured)')
            ->setHandler(function () {
                return new Handler\UpMergeHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['process'],
                    $this->container['branch_splitsh_git']
                );
            })
            ->end()

            ->beginCommand('release')
            ->setDescription('Make a new release for the current branch')
            ->addArgument('version', Argument::REQUIRED | Argument::STRING, 'Version to make (supports relative, eg. minor, alpha, ...)')
            ->addOption('all-categories', null, Option::NO_VALUE | Option::BOOLEAN, 'Show all categories (including empty)')
            ->addOption('no-edit', null, Option::NO_VALUE | Option::BOOLEAN, 'Don\'t open the editor for editing the release page')
            ->addOption('title', null, Option::REQUIRED_VALUE | Option::NULLABLE | Option::STRING, 'Custom title for the release (added after version)')
            ->addOption('pre-release', null, Option::NO_VALUE | Option::BOOLEAN, 'Mark as pre-release (not production ready)')
            ->setHandler(function () {
                return new Handler\ReleaseHandler(
                    $this->container['style'],
                    $this->container['git'],
                    $this->container['github'],
                    $this->container['config'],
                    $this->container['process'],
                    $this->container['editor'],
                    $this->container['branch_splitsh_git'],
                    $this->container['release_hooks']
                );
            })
            ->end()
        ;
    }
}
