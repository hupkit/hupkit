Configuration
=============

**The examples in this chapter assume schema_version 2 is used, schema_version 1 is still supported. 
But schema_version 2 uses some advanced features that could cause problems when not
all team members use the same configuration!** See [upgrade](upgrade.md) for more information.

Since HuPKit v1.2 it's possible to use a local configuration housed in the repository itself,
see below for more information.

Schema Version
--------------

The `schema_version` defines configuration schema version.

Since HuPKit v1.2 `schema_version` 2 should be used with the correct structure.

While `schema_version` 1 is still supported until HuPKit 2.0, it will
produce a warning message everytime HuPKit is used.

The new configuration schema provides for some powerful features including
a local configuration file and per branch configuration.

Authentication
--------------

Before HuPKit can be used to manage your repositories you first need to configure
the GitHub authentication credentials. Get a new token at: https://github.com/settings/tokens/new

Use a unique and distinct name like: `hupkit on computer-1 at 2016-11-01 14:54 CET`
with scopes: "repo, user:email, read:gpg_key".

HuPKit supports GitHub Enterprise, and therefor you can add multiple
hub configurations by there hostname. The default one is `github.com`.

```php
// ...
return [
    // ...
    'github' => [
        'github.com' => [ // hostname of the hub
            'username' => '', // fill-in your github username
            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
        ],
//        'hub.my-corp.com' => [ // hostname of your GitHub Enterprise installation
//            'username' => '', // fill-in your github username
//            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
//            'api_url' => 'https://api.hub.mycorp.com/' // schema + hostname to API endpoint (excluding API version)
//        ],
    ],
];
```

Repository Configuration
------------------------

Specific features like repository splitting or disabling upmerge for some branches
only work when the configuration for these repositories is set.

You can either a use local configuration (recommended) or store the configuration
in the main `config.php` file which also contains your credentials.

### Local Configuration

Since HuPKit v1.2 it's possible to use a local configuration housed in the repository itself.

The configuration (and hook-scripts) are kept in a 'orphaned' branch "_hubkit"
(please notice the hu**b** not hu**p**!).

_An orphan branch has no relation to any other branch, it's fully separate,
and thus no common parent. This same technique is also used by GitHub for
pages stored separate from the main branch._

**No authentication credentials must be stored in local configuration!**

This file contains the configuration _only_ for the current repository and supersedes
the repository configuration entry in the main `config.php` for _this_ repository.

```php
<?php
// config.php

return [
    'schema_version' => 2,

    // Adapter configuration (optional, when cannot be resolved automatically)
    'adapter' => 'github', //Defaults to github, currently no other adapters supported
    'host' => 'github.com',
    'repository' => 'organization/repository-name',

    // Since v1.3 - which branch is considered the main branch. Either: 'main', 'master', '2.0', or 'trunk'
    // This configuration is used (among) for upmerging as know the last branch in the list.
    //
    // If not set this is automatically guessed at application boot, in v2.0 this will default to 'main'.
    // Note: This configuration can only be used in local configuration
    //'main_branch' => null,

    // See branches section below for supported configuration
    'branches' => [],

    // Branches-alias for 'unstable' branches
    //
    // This only applies to branches that are not explicitly versioned (like 1.0, 1.5)
    // The value should be a stable major.minor version (not: 0.5, master, 1.5-dev, 1.2-beta1) but 1.0, 1.5, 23.4
    // 'branches_alias' => [
    //     // 'main' => '1.0',
    //     // 'dev/main' => '1.0',
    //     // '1.x' => '1.5',
    // ],
];
````

#### Initialize Configuration

To create the special branch that HuPKit uses to store the config.php file and some
additional scripts for special operations, run the `init-config` command.

The command will automatically import any existing configuration for the repository
from the main `config.php`, and all files stored in the ".hubkit" directory
(of the current branch).

**Note:** The .gitignore is automatically copied from your current branch to ensure
no unrelated files are accidentally added when working on this branch.

The _hubkit branch sits separate from your normal Git workflow and shares
no parenting to any existing branch.

Once you are done run the `sync-config` command to push your changes.

Use the `self-diagnose` command to check your configuration and see if any
updated is needed.

#### Editing

To change the contents of the _hubkit branch run the `edit-config` command.

**Note:** If this fails due to a configuration error run HuPKit with the
env `HUBKIT_NO_LOCAL=true` like `HUBKIT_NO_LOCAL=true hupkit self-diagnose`.

### Synchronize Configuration

To update the configuration by either pushing your changes or pulling-in
the latest version from "upstream" simply run the `sync-config` command.

Only if your branches have diverged to much you need to resolve any
conflicts manually. Once done run the command again.

### Configuration in the main config.php

The repository configuration structure starts with the hostname as the root, followed
by a list of repositories housed at the hub (like github.com) with their full-name
'organization/repository-name' like `rollerworks/search`.

```php
<?php
// config.php

return [
    'schema_version' => 2,
    'github' => [
        'github.com' => [ // hostname of the hub
            'username' => '', // fill-in your github username
            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
        ],
//        'hub.my-corp.com' => [ // hostname of your GitHub Enterprise installation
//            'username' => '', // fill-in your github username
//            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
//            'api_url' => 'https://api.hub.mycorp.com/' // schema + hostname to API endpoint (excluding API version)
//        ],
    ],

    'repositories' => [
        'github.com' => [
            'organization/repository-name' => [
                'branches' => [] // See branches section below for supported configuration
            ],
        ],
    ],
];
````

### Branches

Each repository has a 'branches' configuration to either set the configuration
for all branches using `:default`, or per specific branch `2.0`.

A branch name can be either `:default` (see below), any valid branch-name,
a minor-version pattern `2.x`/`v2.*`, or a regexp (without anchors or options)
like `/[1-2]\.\d+/`.

**Note:** When an actual branch is named like a pattern (`1.x`) use `#1.x` instead.

The `:default` branch defines the default configuration for _all_ branches, and
is later merged with the configuration of a specific branch. Use `'ignore-default' => true`
for a specific branch configuration to ignore inherited defaults.

When no specific branch is found like 'main' or '2.1' the correct configuration
is resolved by traversing all (in order of listing) the patterns and regexp
until a matching version is found.

If no configuration was found the `:default` configuration (if set) is used.

Use `false` to mark a branch as unmaintained and skip upmerging to *and*
from this branch, this will give a warning whenever this branch is used
for either merging, releasing, taking an issue, etc.

**Tip:** Use regexes like `/[12]\.\d+/` or `/main|dev/trunk|(12\.0)/` to use multiple 
versions or names at once (no anchors or head grouping, `/main|master/` not 
`/(main|12\.0)/`).

Each branch has the following options:

| Name             | Type    | Default   | Description                                                                                                      |
|------------------|---------|-----------|------------------------------------------------------------------------------------------------------------------|
| `sync-tags`      | Boolean | `true`    | _Only when 'split' targets are configured_,<br/>whether new tags should be synchronized when creating a release. |
| `ignore-default` | Boolean | `false`   | Whether the ':default' configuration should be ignored.                                                          |
| `upmerge`        | Boolean | `true`    | Set to false to disable upmerge for this branch configuration, and continue with next possible version.          |
| `split`          | array   | `[]`      | See [Repository splitting](#splitting) for details.                                                              |
| `maintained`     | Boolean | `true`    | `true` when maintained, use `false` as config value shorthand.                                                   |

```php
// ... At config path `repositories.[github.com].[organization/repository-name]`
// for the main config.php, and at the root for local configuration.

'branches' => [
    ':default' => [
        'sync-tags' => true,
        'split' => [
            // A path (relative to root, no patterns) and the url to split to (with additional options)
            // See below for all options.
            'src/Module/CoreModule' => 'git@github.com:hubkit-sandbox/core-module.git',
            'src/Module/WebhostingModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
            'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
        ],
    ],
    'main' => [ // Can be any valid branch-name: dev/trunk, master, development
        'src/Module/CustomerModule' => 'git@github.com:hubkit-sandbox/customer-module.git',
    ],   
    '1.0' => false, // Mark branch as unmaintained
    '2.x' => [ // '2.x' is a pattern equivalent to '/2\.\d+/'
        'upmerge' => false, // Disable upmerge for this branch, effectively all of the '2.x' range are skipped
        // Split's is inherited and merged from ':default'
        'split' => [
            'src/Module/DomainRegModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
        ],
    ],
    '#3.x' => [ // The actual branch name is 3.x would be confused for a pattern
        'sync-tags' => null, // Inherit from the root configuration.
        'split' => [
            'src/Module/WebhostingModule' => false,
            'src/Module/DomainRegModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
        ],
    ],
    '/4\.\d+/' => [ // Same as 4.x
        'ignore-default' => true, // Nothing is inherited from ':default'
        'sync-tags' => false,
        'src/Bundle/CoreBundle' => 'git@github.com:hubkit-sandbox/core-module.git',
        'src/Bundle/WebhostingBundle' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
        'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
    ],
],
```

**Note:** A branch cannot start with Git refs `(heads, tags, remotes, notes)/`, or be `HEAD`.

#### Repository splitting (`split` config)
<a name="splitting"></a>

A list of paths (relative to repository root, *no patterns*) and the value,
either a 'push url' or an array with following options
`['url' => 'push url', 'sync-tags' => false]`.

**Note:** Splits are expected to exist, see also [Managing Split Repositories](split-repositories.md).

```php
// ... At config path `repositories.[github.com].[organization/repository-name].[branch-name]`

'split' => [
    'src/Module/CoreModule' => 'git@github.com:hubkit-sandbox/core-module.git',
    'src/Module/WebhostingModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
    'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
],
```

**Note:** Missing directories are ignored with a warning. In HuPKit v2.0 this behavior is bound to change,
use the branches configuration to ensure to paths are missing.

Remote names
------------

By default HuPKit uses "upstream" as the remote-name for the main-repository.
If for any reason you need to change this, either because you use a different
convention or only use "origin" without a fork, you can use a local configuration
file to change this.

Add a ".hk_remotes" file at the root-level of your repository with the following contents:

```ini
; Commented line

main = upstream
fork = origin
```

The config "main" is the main repository which all team-members pull and push from,
"fork" is your own fork (if any).

**Note:** This is about remote _names_ not actual repository url's.

The file format is similar to an ini-file, a line can contain either a comment (`; Commment`) 
_or_ a variable ("main" or "fork"), no overwrites, no different names (than shown), 
and comments can only be used on their own line (not at the end of a variable declaration).

**Caution:** This file should be ignored by Git as it only applies to your own local
`.git/config` file.

Use the `-v` flag with any command to see which value is used.

### Whats next?

Run the `self-diagnose` command to ensure everything is configured correctly.
This command is best run from a repository.
