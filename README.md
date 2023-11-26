# HuPKit

In short HuPKit allows project(s) maintainers to easily manage their GitHub repositories,
merging pull requests, creating new releases, merging older versioned branches into newer
once and much more.

**Note:** On October 23rd the repository was moved to it's own organization, and renamed to HuPKit.
The old PHP namespace has been lest unchanged.

## Features

* Checkout an issue (as local working branch).
* Merge pull-requests with preservation of all information (description and GitHub discussion).
* (Up)Merge version branches without mistakes.
* Create new releases with a proper changelog, and no gaps in version numbers.
* (Automatically) Split a monolith repository into READ-ONLY repositories.

This tool is designed for project maintainers with a good knowledge of Git and GitHub.

If you have some special needs, please see the contributing section below.

## Requirements

You need at least PHP 8.1, Git 2.10 and a GitHub account (GitHub Enterprise is possible).
Composer is assumed to be installed and configured in your PATH.

## Installation

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

## Basic usage

Run `hupkit help` for a full list of all available commands and options.

**Note:** All commands except `help`, `repo-create` and `self-diagnose` require
you are in a Git repository, and have Git remote `upstream` existing and pointing
to the GitHub head repository (from which all work is coordinated, not your fork).

See the [usage](https://hupkit.github.io/hupkit/usage.html) documentation for full instructions.

## Versioning

For transparency and insight into the release cycle, and for striving
to maintain backward compatibility, this package is maintained under
the Semantic Versioning guidelines as much as possible.

Releases will be numbered with the following format:

`<major>.<minor>.<patch>`

And constructed with the following guidelines:

* Breaking backward compatibility bumps the major (and resets the minor and patch)
* New additions without breaking backward compatibility bumps the minor (and resets the patch)
* Bug fixes and misc changes bumps the patch

For more information on SemVer, please visit <http://semver.org/>.

## Contributing

HuPKit is open-source and community driven, but to prevent becoming
to bloated not all requested features will be actually accepted.

*The purpose of HuPKit is to ease the daily workflow of project maintainers,
not to replace already sufficient functionality. Creating an issue is easier
with the web interface then using a limited CLI application.*

**Support for other adapters, like BitBucket or GitLab will only ever happen once
all adapters support the same level of functionality and stability and performance
is not negatively affected.**

## License

HuPKit is provided under the [MIT license](LICENSE).

## Credits

This project is maintained by Sebastiaan Stok (aka. [@sstok](https://github.com/sstok)).

HuPKit was inspired on the GH Tool used by the Symfony maintainers, 
no actual code from GH was used.
