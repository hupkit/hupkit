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
use the `split-create` command to create the repositories.**

See [Configuration](config.md) how to configure your splits.

To test if the configuration is correct run `hubkit split-repo --dry-run`
to see what would have happened.

Everything correct? Then run `hubkit split-repo` (this may take some time).

## Splitting during merge/release

Once the repository splitting is configured, you want to make sure the split repositories
are up-to-date.

After a [pull request is merged](commands/merge.md) you are automatically asked if you want
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
    // ...
    [
        ':default' => [
            'sync-tags' => true, // disable for all (or)
            'split' => [
                // Disable/enable per repository target
                'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => true],
            ],
        ],
   ],
```

Existing tags in split repositories are always ignored.
