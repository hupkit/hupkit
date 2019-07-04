Managing Split Repositories
===========================

A special feature of HubKit is the ability to handle monolith project developments,
instead of having separate Git repositories for each package all are housed in a 
central repository from where all work is coordinated.

**Before you continue make sure [splitsh-lite](https://github.com/splitsh/lite) is 
installed and can be found in your `PATH` environment (no `alias`!).**

### Configuration

To make this "repository splitting" work, a number of targets must 
be configured. Each target consists of a prefix (directory path),
target repository and some additional options like if tag-synchronizing is enabled.

**Suffice to say the repositories must exist! They are not automatically created,
use the [create-repo](commands/create-repro.md) command with the correct arguments
to create the repositories.**

In your global `config.php` set the following:

```php
    ...

    // Configuration for repository splitting.
    // Structure is expected to be: [hostname][organization/source-repository]
    // With 'split' being a list of paths (relative to repository root, and no patterns)
    //    and the value e.g. an 'push url' or `['url' => 'push url', 'sync-tags' => false]`.
    //
    // All configured targets are split when requested. Missing directories are ignored.
    //
    // The push remote is automatically registered.
    // The 'sync-tags' can be configured for all split targets, and per target.
    //
    'repos' => [
        'github.com' => [
            'park-manager/park-manager' => [
                'sync-tags' => true,
                'split' => [
                    'src/Bundle/CoreBundle' => 'git@github.com:park-manager/core-bundle.git',
                    'src/Bundle/TestBundle' => 'git@github.com:park-manager/test-bundle.git',
                    'src/Bundle/UserBundle' => 'git@github.com:park-manager/user-bundle.git',
                    'src/Component/Core' => 'git@github.com:park-manager/core.git',
                    'src/Component/Model' => 'git@github.com:park-manager/model.git',
                    'src/Component/Security' => 'git@github.com:park-manager/security.git',
                    'src/Component/User' => 'git@github.com:park-manager/user.git',
                    'src/Component/WebUI' => 'git@github.com:park-manager/webui.git',
                    'src/Module/Webhosting' => 'git@github.com:park-manager/webhosting.git',
                    'src/Bridge/Doctrine' => 'git@github.com:park-manager/doctrine-bridge.git',
                ],
            ],
        ],
    ],
```

*And don't forget to change the values to suite your own.*

To test if the configuration is correct run `hubkit split-repo --dry-run`
to see what would have happened.

Everything correct? Then run `hubkit split-repo` (this may take some time).

**Tip:** Managing the configuration of split-repositories is tedious work, in HubKit v2.0
this will be made much more developer friendly.

## Splitting during merge/release

Once the repository splitting is configured, you want to make sure the split repositories 
are up-to-date.

After a [pull request is merged](merge.md) you are automatically asked if you want 
to split the monolith repository, this process may take some time depending of number 
of commits and targets. 

If you need to merge more then one pull-request you properly want to hold-of
the split operation till you're done. 

**Caution:** Splitting is automatically skipped when the `--no-pull` option is provided.

The release command works a little different here, when making a new release the 
split operation is always performed! You cannot skip this. However you can skip
the synchronizing of tags to certain split repositories by setting the `sync-tags` 
config to false.

```php
    ...

    'repos' => [
        'github.com' => [
            'park-manager/park-manager' => [
                'sync-tags' => false, // disable for all (or)
                'split' => [
                    // Disable/enable per repository target
                    'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => true],
                ],
            ],                
        ],
    ],
```

Existing tags in split repositories are always ignored. 
