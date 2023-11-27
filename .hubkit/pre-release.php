<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {
    $versionResolver = static function (Version $version) {
        if ($version->stability !== Version::STABILITY_STABLE) {
            return sprintf('%d.%d-dev', $version->major, $version->minor);
        }

        return sprintf('%d.%d-dev', $version->major, $version->minor + 1);
    };

    $container->get('logger')->info('Updating composer branch-alias');
    $container->get('process')->mustRun(['composer', 'config', 'extra.branch-alias.dev-'.$branch, $versionResolver($version)]);

    /** @var \HubKit\Service\Git\GitBranch $gitBranch */
    $gitBranch = $container->get('git.branch');

    if ($gitBranch->isWorkingTreeReady()) {
        return; // Nothing to, composer is already up-to-date
    }

    $gitBranch->add('composer.json');
    $gitBranch->commit('Update composer branch-alias');

    /** @var \HubKit\Service\Git $git */
    $git = $container->get('git.branch');
    $git->pushToRemote('upstream', $branch);
};
