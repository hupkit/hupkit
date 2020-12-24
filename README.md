# Park-Manager HubKit

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/4b94233d-799f-4276-91fb-2acd4a34f15e/mini.png)](https://insight.sensiolabs.com/projects/4b94233d-799f-4276-91fb-2acd4a34f15e)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/park-manager/hubkit/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/park-manager/hubkit/?branch=master)
[![Build Status](https://travis-ci.org/park-manager/hubkit.svg?branch=master)](https://travis-ci.org/park-manager/hubkit)

HubKit was created to ease the development workflow of the Park-Manager project.
In short HubKit allows project(s) maintainers to easily manage there GitHub repositories.

Feel free to use it for your own projects.

## Features

* Checkout an issue (as local working branch).
* Merge pull-requests with preservation of all information (description and GitHub discussion).
* (Up)Merge version branches without mistakes.
* Create new releases with a proper changelog, and no gaps in version numbers.
* (Automatically) Split a monolith repository into READ-ONLY repositories.

This tool is designed for project maintainers with a good knowledge of Git and GitHub.
If you have some special needs, please see the contributing section below.

## Requirements

You need at least PHP 7.2, Git 2.10 and a GitHub account (GitHub Enterprise is possible).
Composer is assumed to be installed and configured in your PATH.

## Installation

HubKit is a PHP application, you don't install it as a dependency
and you don't install it with Composer global.

To install HubKit first choose a directory where you want to keep the installation.
Eg. `~/.hubkit` or any of your choice.

**Caution:** Make sure you don't use a directory that is accessible by
others (like the web server root) as this may expose your API access-token!

Download HubKit by cloning the repository:

```bash
mkdir ~/.hubkit
cd ~/.hubkit
git clone https://github.com/park-manager/hubkit.git .
```

Checkout the [latest version](https://github.com/park-manager/hubkit/releases). Eg.

```bash
git checkout tags/1.0.0 -b version-1.0.0
```

And install the dependencies:

```bash
./bin/install
```

### Special note for Windows users

**HubKit has not been tested on Windows yet, it should work.
But you may encounter some problems.**

Note that HubKit expects a Unix (alike) environment.
You are advised to use the Git console or Bash shell (Windows 10+).

Please open an issue in the issue-tracker when something is not working.
Or open a pull-request when you can fix the problem :+1:

### Updating

Updating HubKit is very easy. Go to the HubKit installation
directory, and run `./bin/upgrade`.

Done, you now have the latest version.

## Basic usage

Run `hubkit help` for a full list of all available commands and options.

**Note:** All commands except `help`, `repo-create` and `self-diagnose` require
you are in a Git repository, and have Git remote `upstream` existing and pointing
to the GitHub head repository (from which all work is coordinated, not your fork).

See the [usage](https://www.park-manager.com/hubkit/usage.html) documentation for full instructions.

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

HubKit was designed specifically for the maintenance workflow of the Park-Manager project.
In the spirit of free-software it's made available to everyone.

HubKit is open-source and community driven, but to prevent becoming
to bloated not all requested features will be actually accepted.

*The purpose of HubKit is to ease the daily workflow of project maintainers,
not to replace already sufficient functionality. Creating an issue is easier
with the web interface then using a limited CLI application.*

**Support for other adapters, like BitBucket or GitLab will only ever happen once
all adapters support the same level of functionality and stability and performance
is not negatively affected.**

## License

HubKit is provided under the [MIT license](LICENSE).

## Credits

This project is maintained by Sebastiaan Stok (aka. [@sstok](https://github.com/sstok)),
creator of Park-Manager.

HubKit was inspired on the GH Tool used by the Symfony maintainers,
no actual code from GH was used.

HubKit is not to be confused with [Hub](https://hub.github.com/) (from GitHub).
