Release hooks
=============

The [release](commands/release.md) command makes a new release for the current branch.
By default this creates a new signed Git tag, published a GitHub release page,
and updates the split repositories.

But sometimes you need to perform a special operation before and/or after the release process.
This might vary from updating the `composer.json` branch-alias, to creating a pull request for
the new release.

Now instead of doing this manually HuPKit allows to hook-into the release process, but executing
a custom callback before and/or after a new release is created.

Both the pre and post hooks work the same way, but are executed at different stages.

## The Script

Add a PHP  script named either `pre-release.php` or `post-release.php` at the root folder
of the "_hubkit" [configuration branch](config.md#local-configuration).

**Caution:** Prior to HuPKit v1.2 scripts were expected in the ".hubkit" folder at the root folder
of the repository. Make sure to use the latest available release to prevent unexpected behavior.

With the following contents:

```php
<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {
    // Place the hooks logic here.

    // The $container provides access to the HuPKit application Service Container
    // with among following services: github, git (git.config, git.branch), process, filesystem, style, editor, logger.
    //
    // See \HubKit\Container for all services and there corresponding classes.
    // Note: Only the services listed above are covered by the BC promise.
    
    // !! CAUTION !!
    //
    // Hooks are loaded from a temporary location *outside of the repository*, use the `__DIR__`
    // constant to load files related to the script, use `$container['current_dir']` 
    // to get the actual location to the project repository.
    //
};
```

**Note:** The hook is executed in the HuPKit application's context, you have full access to entire applications flow!

While possible it's' best not to load external dependencies as there is currently no promise this will work
when HuPKit uses similar dependencies. In this case it might be better to use the `process` service to execute
a separate command.

Below you can find some examples how to use these hooks.

### Updating the `composer.json` branch-alias (pre-release)

```php
<?php

declare(strict_types=1);

// pre-release.php (in the _hubkit branch)

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {

    $container->get('logger')->info('Updating composer branch-alias');
    $container->get('process')->mustRun(['composer', 'config', 'extra.branch-alias.dev-'.$branch, sprintf('%d.%d-dev', $version->major, $version->minor)]);

    // Caution: Make sure to commit the changes. HuPKit will refuse to continue if there are dangling changes.

    /** @var \HubKit\Service\Git\GitBranch $gitBranch */
    $gitBranch = $container->get('git.branch');

    $gitBranch->add('composer.json');
    $gitBranch->commit('Update composer branch-alias');

    /** @var \HubKit\Service\Git $git */
    $git = $container->get('git');
    $git->pushToRemote('upstream', $branch);
};
```

### Updating the `composer.json` branch-alias (post-release)

```php
<?php

declare(strict_types=1);

// post-release.php  (in the _hubkit branch)

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {

    $container->get('logger')->info('Updating composer branch-alias');
    $container->get('process')->mustRun(['composer', 'config', 'extra.branch-alias.dev-'.$branch, sprintf('%d.%d-dev', $version->major, $version->minor)]);

    // Caution: Make sure to commit the changes. HubKit will refuse to continue if there are dangling changes.

    /** @var \HubKit\Service\Git\GitBranch $gitBranch */
    $gitBranch = $container->get('git.branch');

    if ($gitBranch->isWorkingTreeReady()) {
        return; // Nothing to commit, composer is already up-to-date
    }

    $gitBranch->add('composer.json');
    $gitBranch->commit('Update composer branch-alias');

    /** @var \HubKit\Service\Git $git */
    $git = $container->get('git');
    $git->pushToRemote('upstream', $branch);
};
```

### Creating a pull request for the release (pre-release)

**Caution:** This technique is not to be used as-is, understand the risk and be sure to apply enough
error protections.

```php
<?php

declare(strict_types=1);

// pre-release.php  (in the _hubkit branch)

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;
use Symfony\Component\Console\Helper\ProgressIndicator;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {

    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger');
    /** @var \HubKit\Service\GitHub $github */
    $github = $container->get('github');
    /** @var \HubKit\Service\Git\GitBranch $gitBranch */
    $gitBranch = $container->get('git.branch');

    /** @var \Symfony\Component\Console\Style\SymfonyStyle $style */
    $style = $container->get('style');

    $logger->info('Preparing new release branch');
    $gitBranch->checkoutNew($releaseBranch = 'release-'.$version->full);

        $logger->info('Updating composer branch-alias');
        $container->get('process')->mustRun(['composer', 'config', 'extra.branch-alias.dev-'.$branch, sprintf('%d.%d-dev', $version->major, $version->minor)]);

        // WARNING if nothing was changed this will crash the entire process, make sure to use something like:
        //
        // if ($gitBranch->isWorkingTreeReady()) {
        //     return;
        // }

        $gitBranch->add('composer.json');
        $gitBranch->commit('Update composer branch-alias');

    $gitBranch->pushToRemote('origin', $releaseBranch);

    $pr = $github->openPullRequest($branch, $releaseBranch, 'Release v'.$version->full, 'This might be a good place for a changelog.');

    $style->warning([
        'Pull request '.$pr['html_url'].' was opened for this release.',
        'The process will automatically continue once this pull request is merged.',
        '!! DO NOT ABORT THE COMMAND !!'
    ]);

    $progress = new ProgressIndicator($style);
    $progress->start('Waiting for pull request to be merged.');

    // Wait till the pull-request is merged. This might crash if the API limit is exceeded.
    //
    // Alternatively you can merge the pull request directly, but make sure you use a proper CI.
    while ($github->getPullRequest($pr['number'])['state'] === 'open') {
        sleep(30); // sleep for 30 seconds

        $progress->advance();
    }

    if ($github->getPullRequest($pr['number'])['merged'] === false) {
        $progress->finish('Pull request was closed. Aborting.');

        exit(1);
    }

    $progress->finish('Pull request was merged, continuing.');
    $gitBranch->pullRemote('upstream', $branch);
};
```

### Final words

The `post-release` script might be used to automatically publish a blog post
or reset the version information in a PHP file, to prepare for the next release.

For more advanced usage in either the pre or post phase you might want to use something
like https://github.com/liip/RMT#actions (be sure to set `vcs` to none to prevent conflicts).
