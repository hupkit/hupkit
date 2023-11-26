HuPKit
======

In short HuPKit allows project(s) maintainers to easily manage their GitHub repositories,
merging pull requests, creating new releases, merging older versioned branches into newer
once and much more.

You need at least PHP 8.1, Git 2.10 and a GitHub account (GitHub Enterprise is supported).
HuPKit works but is not fully tested on Windows.

**Note:** On October 23rd 2023 the repository was moved to it's own organization, and renamed 
to HuPKit. The old PHP namespace has been left unchanged. In version 2.0 this will change.

HuPKit is provided under the [MIT license](https://github.com/hupkit/hupkit/LICENSE) and maintained by Sebastiaan Stok 
(aka. [@sstok](https://github.com/sstok)).

Features
--------

* Taking issues to work on, and checking out an existing pull request
  to either fix manually or changing the base branch (rebase) with ease.

* Merging pull requests with preservation of all information
  And additional metadata storage for Changelog rendering;

* Upmerging changes to newer branches;

* Release new version with fault checking, automatic Changelog rendering
  and monolith repository-split;

* Monolith repository splitting to READ-ONLY repositories;

* Additional support for custom hook scripts before/after release;

* And finally per branch configuration of maintenance marking, repository splitting
  tag-synchronizing and upmerging;

This tool is designed for project maintainers with a good knowledge of Git, PHP and GitHub.
If you have some special needs, please see the contributing section below.

Installation
------------

HuPKit is a PHP application, you don't install it as a dependency
and you don't install it with Composer global.

To install HuPKit first choose a directory where you want to keep the installation.
Eg. `~/.hupkit` or any of your choice.

**Caution:** Make sure you don't use a directory that is accessible by
others (like the web server root) as this may expose your API access-token!

Download HuPKit by cloning the repository:

```bash
mkdir ~/.hupkit
cd ~/.hupkit
git clone https://github.com/hupkit/hupkit.git .
```

Checkout the [latest version](https://github.com/hupkit/hupkit/releases). Eg.

```bash
git checkout tags/1.0.0 -b version-1.0.0
```

And install the dependencies:

```bash
./bin/install
```

### Special note for Windows users

**HuPKit has not been tested on Windows yet, it should work.
But you may encounter some problems.**

Note that HuPKit expects a Unix (alike) environment.
You are advised to use the Git console or Bash shell (Windows 10+).

Please open an issue in the issue-tracker when something is not working.
Or open a pull-request when you can fix the problem :+1:

### Updating

Updating HuPKit is very easy. Go to the HuPKit installation
directory, and run `./bin/upgrade`.

Done, you now have the latest version.

Basic Usage
-----------

Before you can use HuPKit a number of things must be configured first,
you need a GitHub authentication token, Git must be configured in your 
PATH-env, and PHP must be accessible.

All commands except `help`, `repo-create` and `self-diagnose` require
you are in a Git repository, and have Git remote `upstream` existing 
and pointing to the GitHub main repository (from which all work is 
coordinated, not your fork).

### Configuring

See the [Configuration](config.md) section how to configure your
GitHub credentials, and set-up repository splittings.

### Commands

Once done you follow-up with the following articles:

* [Repository Splitting](split-repositories.md)
* [Creating a new Release](commands/release.md)
* [Upmerging](commands/upmerge.md)
* [Pull request base switching](commands/switch-base.md)
* [Merging a pull request](commands/merge.md)
* [Checkout](commands/checkout.md)
* [Take](commands/take.md)
* [Creating a Repository](commands/repo-create.md)
* [Changelog](commands/changelog.md)
* [Branch aliasing](commands/branch-alias.md)

Run `hupkit help` for a full list of all supported commands, and there options.

If something doesn't work as expected you can find useful tips 
in the [troubleshooting guide](troubleshooting.md).

And finally for the hook-scripts you can find all the available public 
services in the [Container services](container-services.md) reference guide.

[composer]: https://getcomposer.org/doc/00-intro.md
