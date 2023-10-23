Usage
=====

## Configuration

HuPKit works by a number of conventions which cannot be changed, the configuration
contains your authentication credentials, and set-up for special operations like
repository splitting and upmerge.

Assuming you already copied `config.php.dist` to `config.php`. Open `config.php`
and fill-in your authentication credentials.

**Tip:** Whenever you change the configuration it's advised to run `hupkit self-diagnose`
afterwards to check if everything is configured properly.

### Working with GitHub Enterprise

HuPKit supports GitHub Enterprise, and therefor you can add multiple
hub configurations by there hostname. The default one is `github.com`.

```phph
<?php

return [
    'schema_version' => 2, // Config-schema version, only change this when requested

    // HuPKit supports GitHub Enterprise, and therefor you can add multiple
    // hub configurations by there hostname. The default one is `github.com`.
    //
    // Before you can authenticate, get a new token at: https://github.com/settings/tokens/new
    //
    // Use a unique and distinct name like: `hupkit on computer-1 at 2016-11-01 14:54 CET`
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

Say you have an GitHub Enterprise on `development.example.com`, first add the `development.example.com`
hub configuration to your `config.php` file:

```phph
<?php

return [
    'schema_version' => 2, // Config-schema version, only change this when requested

    // HuPKit supports GitHub Enterprise, and therefor you can add multiple
    // hub configurations by there hostname. The default one is `github.com`.
    //
    // Before you can authenticate, get a new token at: https://github.com/settings/tokens/new
    //
    // Use a unique and distinct name like: `hupkit on computer-1 at 2016-11-01 14:54 CET`
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
place it in your 'development.example.com' hub configuration and run `hupkit self-diagnose`
to test if everything is working.

Whenever you use hupkit in a Git repository it will automatically detect the
correct hub configuration by remote "upstream".

## Self Diagnoses

The `self-diagnose` checks your system is ready to use HuPKit and gives recommendations
about changes you should make.

**Note:** It's recommended to run this command from a local Git repository,
as more information is shown then.

## Repository splitting

A special feature of HuPKit is the ability to manage split monolith repositories,
each split-repository holds the contents of a smaller portion of the main monolith
repository.

See [Managing Split Repositories](split-repositories.md) for all details.

## Commands

Run `hupkit help` for a full list of all available commands and options.

**Note:** All commands except `help`, `repo-create` and `self-diagnose` require
you are in a Git repository, and have Git remote `upstream` existing and pointing
to the GitHub main repository (from which all work is coordinated, not your fork).

For the `repo-create` command you may need to provide which hub configuration you
want to use. By default 'github.com' is used, use `--host=development.example.com`
for the Enterprise example shown above.

Command names and options are in lowercase.

* [branch-alias](commands/branch-alias.md)
* [changelog](commands/changelog.md)
* [checkout](commands/checkout.md)
* [merge](commands/merge.md)
* [release](commands/release.md)
* [repo-create](commands/repo-create.md)
* [upmerge](commands/upmerge.md)

Further there are some minor commands to help you in your daily operation
as a maintainer.

### take

Take an issue (no pull request) to work on. In practice this checkouts the issue as new branch.

```bash
$ hupkit take 22
```

By default the "master" branch is used as base, use the `--base` option to use
a different one. Eg. `--base=1.x` for `upstream/1.x`.

```bash
$ hupkit take --base=1.x 22
```

### switch-base

Switch the base of a pull request (and performs a rebase to prevent unwanted commits).

```bash
$ hupkit switch-base 22 1.6
```

#### Conflict resolving

Switching the base of a branch may cause some conflicts.

When this happens you can simple resolve the conflicts as you would with using `git rebase --continue`,
then once all conflicts are resolved. Run the `switch-base` command (with the original parameters)
again and it will continue as normal.

**Do not push these changes manually as this will not update the pull-request target base.**

### cache-clear

Clears the cache HuPKit uses to split repositories and store resolved configuration branches.

**Tip:** Use the `-v` option to show how much space was retrieved.
