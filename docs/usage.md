Usage
=====

## Configuration

HubKit works by a number of conventions which cannot be changed, the configuration
mainly contains your authentication credentials and some personal preferences.

Assuming you already copied `config.php.dist` to `config.php`. Open `config.php`
and fill-in in your authentication credentials.

Whenever you changed the configuration it's advised to run `hubkit self-diagnose`
to check if everything is configured properly.

### Working with GitHub Enterprise

HubKit supports GitHub Enterprise, and therefor you can add multiple
hub configurations by there hostname. The default one is `github.com`.

```phph
<?php

return [
    'schema_version' => 1, // Config-schema version, only change this when requested

    // HubKit supports GitHub Enterprise, and therefor you can add multiple
    // hub configurations by there hostname. The default one is `github.com`.
    //
    // Before you can authenticate, get a new token at: https://github.com/settings/tokens/new
    //
    // Use a unique and distinct name like: `hubkit on computer-1 at 2016-11-01 14:54 CET`
    // with scopes: "repo, user:email, read:gpg_key"
    'github' => [
        'github.com' => [ // hostname of the hub
            'username' => '', // fill-in your github username
            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
        ],
//        'hub.mycorp.com' => [ // hostname of your GitHub Enterprise installation
//            'username' => '', // fill-in your github username
//            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
//            'api_url' => 'https://api.hub.mycorp.com/' // schema + hostname to API endpoint (excluding API version)
//        ],
    ],
];
```

Say you have an GitHub Enterprise on `development.example.com`, first add the development.example.com
hub configuration to your `config.php` file:

```phph
<?php

return [
    'schema_version' => 1, // Config-schema version, only change this when requested

    // HubKit supports GitHub Enterprise, and therefor you can add multiple
    // hub configurations by there hostname. The default one is `github.com`.
    //
    // Before you can authenticate, get a new token at: https://github.com/settings/tokens/new
    //
    // Use a unique and distinct name like: `hubkit on computer-1 at 2016-11-01 14:54 CET`
    // with scopes: "repo, user:email, read:gpg_key"
    'github' => [
        'github.com' => [ // hostname of the hub
            'username' => '', // fill-in your github username
            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
        ],
        'development.example.com' => [ // hostname of your GitHub Enterprise installation
            'username' => '', // fill-in your github username
            'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
            'api_url' => 'https://api.hub.mycorp.com/' // schema + hostname to API endpoint (excluding API version)
        ],
    ],
];
```

And obtain a new api-token at `https://development.example.com/settings/tokens/new`,
place it in your 'development.example.com' hub configuration and run `hubkit self-diagnose`
to test if everything is working.

Whenever you use hubkit in a Git repository it will automatically detect the
correct hub configuration by remote "upstream".

## Commands

Run `hubkit help` for a full list of all available commands and options.

**Note:** All commands except `help`, `repo-create` and `self-diagnose` require
you are in a Git repository, and have Git remote `upstream` existing and pointing
to the GitHub head repository (from which all work is coordinated, not your fork).

For the `repo-create` command you may need to provide which hub configuration you
want to use. By default 'github.com' is used, use `--host=development.example.com`
for the Enterprise example shown above.

Command names and options are in lowercase.

### branch-alias

Set/get the "master" branch-alias. Omit the `alias` argument to get the current alias.

To set a branch-alias for the "master" use:

```bash
$ hubkit branch-alias 1.0
```

To get the branch-alias for "master" use the command without any arguments:

```bash
$ hubkit branch-alias
```

### changelog

Generate a changelog, formatted according to http://keepachangelog.com/
with all changes between (the specified) commits.

**Note:** Security is placed higher then the original spec to ensure they
are noticed.

The commit range is automatically determined using the last tag on the (current)
branch and the current branch. Eg. `v1.0.0..master`. But you can provide your own range
as you would with `git log`, except now you get a nice changelog!

```bash
$ hubkit v1.0.0..master
```

Will produce something like:

```markdown
### Added
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)

### Changed
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)

### Deprecated
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)

### Removed
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)
```

To prevent the changelog from being to cluttered empty sections are left out,
but if you prefer you can include these using the `--all` option.

```markdown
### Security
- nothing

### Added
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)

### Changed
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)

### Deprecated
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)

### Removed
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)

### Fixed
- nothing
```

Alternatively you use the `--online` option to get a changelog without sections.

```markdown
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)
```

**Caution:** The changelog auto formatting works because of some conventions used
in HubKit. Only pull requests that were merged with the `merge` command (not the merge button!)
will be properly categorized and labeled, all others show-up in he "Changed" section.

### checkout

Checkout a pull request by number. This creates a new branch based on the user
and branch name to prevent conflicts with your local branches, eg. `sstok--great-new-feature`.
If the branch already exists it's updated instead.

Unless the author of the pull request disabled this feature. It's possible to push new changes
to user's fork by simply using `git push`. _HubKit already configured the upstream for your,
but remember to be careful with forced pushes._

### merge

Merge a pull request with preservation of the original title/description and comments.
Plus some additional information for the `changelog` command.

To merge pull request `#22` in your repository simple run:

```bash
$ hubkit merge 22
```

And choose a category (feature, refactor, bug, minor, style).

**Note:** You may get a warning that some checks are pending, depending on your
repository's branch protection you may not be able to merge then.

#### Bat on the back

Once the pull request is merged the author (unless you are merging your own)
automatically gets little "pat on the back" for there work.

You can change the (default) message `Thank you @author` using the `--pat` option.

```bash
$ hubkit merge 22 --pat=':beers: @author !'
```

Or use the `--no-pat` option to skip it for this merge.

**Caution:** The `!` has a special meaning in the shell, use single quotes
to prevent expansion.

#### Special options

The `merge` command has a number of special options:

* `--squash`: Squash the pull request before merging;

* `--no-pull`: Skip pulling changes to your local base branch;

* `--security`: Merge pull request as a security patch (uses the 'security' category);

### release

Make a new release for the current branch, this creates a signed Git tag and GitHub release-page.

The `release` command automatically generates a changelog and allows to add
additional info using your favorite editor. Use `--no-edit` to skip the editing,
you can change the content later using the GitHub web interface.

```bash
$ hubkit release 1.0.0
```

A version is expected to follow the SemVer format, eg. `v1.0.0`, `0.1.0` or `v1.0.0-BETA1`.
Leading `v` is automatically added when missing and the meta version (alpha, beta, rc) is
upper cased.

**Tip:**

> The version is automatically expended, so `1.0` is expended to `1.0.0`.
>
> And versions are validated against gaps, so you can't make a mistake.

#### Special options

The `release` command has a number of special options:

* `--all-categories`: Show all categories (including empty) (same as `--all` of the `changelog` command);

* `--no-edit`: Don't open the editor for editing the release page, accept the changelog as-is;

* `--pre-release`: Mark as pre-release (not production ready);

#### Notes on signing

The Git tag of a release is signed, *you can't disable this*. Make sure you have
a signing key configured and that gpg/pgp is set-up properly.

See also: https://git-scm.com/book/tr/v2/Git-Tools-Signing-Your-Work

### repo-create (deprecated)

Creates a new minimal empty GitHub repository.
Wiki and Downloads are disabled by default.

```bash
$ hubkit repo-create park-manager/hubkit
```

This creates the "hubkit" repository in the "park-manager" organization.
To create a private repository (may require a paid plan) use the `--private` option.

**Note:** This command is considered deprecated, it will not be removed
anytime soon, but will be moved to RepoKit (not publicly available yet).

#### Special options

The `repo-create` command has a number of special options:

* `--private`: Create private repository (may require a paid plan);

* `--no-issues`: Disable issues, usable when you have a global issue tracker or using
  a third party solution like Jira.

### self-diagnose

Checks your system is ready to use HubKit and gives recommendations
about changes you should make.

**Note:** It's recommended to run this command from a local Git repository,
as more information is shown then.

### take

Take an issue (no pull request) to work on, checkout the issue as new branch.

```bash
$ hubkit take 22
```

By default the "master" branch is used as base, use the `--base` option to use
a different one. Eg. `--base=1.x` for `upstream/1.x`.

### switch-base

Switch the base of a pull request (and perform a rebase to prevent unwanted commits).

```bash
$ hubkit switch-base 22 1.6
```

#### Conflict resolving

Switching the base of a branch may cause some conflicts.

When this happens you can simple resolve the conflicts as you would with using `git rebase --continue`,
then once all conflicts are resolved. Run the `switch-base` command (with the original parameters) 
again and it will continue as normal.

**Do not push these changes manually as this will not update the pull-request target base.**

### upmerge

Merge the current branch to the next version branch (eg. 1.0 into 1.1).

Most projects follow a 'merge bug fixes to the lowest supported branch first' approach,
and then merge the lower branch into newer branches. Usually: `1.0 -> 2.0 -> master`.

But this process is tedious (boring) and error prone, the `upmerge` command makes
this boring work, as simple and save as possible.

The process ensures your local source *and* target branch are up-to-date,
uses the correct flags (`--no-ff` and `--log`), and pushes to upstream.

First checkout the lowest supported branch, eg. `1.0`. And run the upmerge command.

```bash
$ git checkout 1.0
$ hubkit upmerge
```

That's it! The command automatically detects which branch `1.0` is to be merged into.

**Caution:** The `upmerge` command uses the Semantic Versioning schematics, versions
are automatically detected based on there precedence. **Don't use this command when
you use GitFlow!**

Need to merge more then one branch? Use `--all` option to merge the current branch
into the "next preceded version" branch, and that one into the it's next, and finally
them master branch.

#### Conflict resolving

Mering branches into each other you cause some merge conflicts.

When this happens you can simple resolve the conflicts as you would with using `git merge`,
then once all conflicts are resolved. Run the `upmerge` command again and it will
continue as normal.

## split-repo

Splits the repository into other repositories.

This operation is related to monolith project development, instead of having separate 
Git repositories for each package all are housed in a central repository from where 
all work is coordinated.

To make distribution of the packages possible they are split into "push only" repositories 
(no issues or pull requests).

**Warning:** Repository splitting only works when using HubKit, using the GitHub merge button 
does not automatically split the repository!

**Before you continue make sure [splitsh-lite](https://github.com/splitsh/lite) is 
installed and can be found in your path (no `alias`!).** 

Run `hubkit self-diagnose` to check your configuration.

### Configuration

To make this "repository splitting" work, a number of targets must 
be configured. Each target consists of a prefix (directory path),
target repository and optionally if tag-synchronizing is enabled.

**Suffice to say the repositories must exist! They are not automatically created,
use the `create-repo` command with the correct arguments to create the repositories.**

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

*And obviously change the values to suite your own.*

> Park-Manager core developers should contact the project lead for
> the correct configuration to use for `park-manager/park-manager`.

To test if the configuration is correct run `hubkit split-repo --dry-run`
to see what would have happened.

Everything correct? Then run `hubkit split-repo` (this may take some time).

### Splitting during merge/release

Once the repository splitting is configured, you want to make sure
the split repositories are up-to-date.

When running `merge` you are automatically asked if you want to split now,
this process may take some time depending of number of commits and targets.

**Note:** If you need to merge more then one pull-request you properly want to hold-of
the split operation till you're done. Changes never split when the `--no-pull` option
is provided.

The release command works a little different here, when making a new release the 
split operation is always performed! You cannot skip this. However you can skip
the synchronizing of tags to certain split repositories.

**Note:** The split operation is performed first, then split repositories
are tagged, and *then* the main repository is tagged. This ensures when something
goes wrong the main repository remains unaffected, existing tags in split repositories
are simple ignored. 
