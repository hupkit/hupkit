Upgrade HuPKit
==============

Upgrade to v1.2.0-BETA6
-----------------------

The pre/post release hook scripts are now expected to be stored in the _"hubkit" configuration branch.

*Using the ".hubkit directory at the root of the repository is still supported, but will no longer work
in HuPKit v2.0.*

**Caution:** Hooks are loaded from a temporary location *outside of the repository*, use the `__DIR__`
constant to load files from the the temp-location, use `$container['current_dir']`
to get the actual location to the project repository.

The services already use the correct current location (the repository root).

**For example:**

```php
<?php

// pre-release.php (in the "_hubkit" branch)

declare(strict_types=1);

use Psr\Container\ContainerInterface as Container;
use Rollerworks\Component\Version\Version;

return function (Container $container, Version $version, string $branch, ?string $releaseTitle, string $changelog) {

    $container->get('logger')->debug('Working at: ' . $container['current_dir']);
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
```

Upgrade to v1.2.0-BETA4
-----------------------

The configuration format was changed to allow for more advanced features, change
the `schema_version` to 2 and update the new structure.

**Note:** The old configuration format still works (until the next major release of HuPKit) but will give a warning.

For config.php the new structure as follows, see the [Configuration](config.md) chapter for all options.

```diff
-    'schema_version' => 1,
+    'schema_version' => 2,

-//    'repos' => [
-//        'github.com' => [
-//            'park-manager/park-manager' => [
-//                'sync-tags' => true,
-//                'split' => [
-//                    'src/Bundle/CoreBundle' => 'git@github.com:park-manager/core-bundle.git',
-//                    'src/Bundle/UserBundle' => 'git@github.com:park-manager/user-bundle.git',
-//                    'doc' => ['git@github.com:park-manager/doc.git', 'sync-tags' => false],
-//                ],
-//            ],
-//        ],
-//    ],
+
+    'repositories' => [
+        'github.com' => [
+            'park-manager/park-manager' => [
+                'branches' => [
+                    ':default' => [
+                        'sync-tags' => true,
+                        'split' => [
+                            'src/Module/CoreModule' => 'git@github.com:hubkit-sandbox/core-module.git',
+                            'src/Module/WebhostingModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                            'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
+                        ],
+                    ],
+                    '1.0' => false, // Disabled
+                    '2.x' => [
+                        'upmerge' => false,
+                        // Split's is inherited and merged from ':default'
+                        'split' => [
+                            'src/Module/DomainRegModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                        ],
+                    ],
+                    '3.x' => [
+                        'sync-tags' => null, // Inherit from the root configuration.
+                        'split' => [
+                            'src/Module/WebhostingModule' => false,
+                            'src/Module/DomainRegModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                        ],
+                    ],
+                    '4.1' => [
+                        'ignore-default' => true, // Nothing is inherited from ':default'
+                        'sync-tags' => false,
+                        'src/Bundle/CoreBundle' => 'git@github.com:hubkit-sandbox/core-module.git',
+                        'src/Bundle/WebhostingBundle' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                        'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
+                    ],
+                ],
+            ],
+        ],
+    ],
```

