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

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {
    $container->get('logger')->info('Updating composer branch-alias');
    $container->get('process')->mustRun(['composer', 'config', 'extra.branch-alias.dev-'.$branch, sprintf('%d.%d-dev', $version->major, $version->minor)]);

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
