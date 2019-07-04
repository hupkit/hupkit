# Park-Manager HubKit

HubKit was created to ease the development workflow of the Park-Manger project.
In short HubKit allows project(s) maintainers to easily manage there GitHub repositories.

Feel free to use it for your own projects.

## Features

* Checkout an issue (as local working branch).
* Merge pull-requests with preservation of all information (description and GitHub discussion).
* (Up)Merge version branches without mistakes.
* Create new releases with a proper changelog, and no gaps in version numbers.

This tool is designed for project maintainers with a good knowledge of Git and GitHub.
If you have some special needs, please see the contributing section below.

## Requirements

You need at least PHP 7.1, Git 2.10 and a GitHub account (GitHub Enterprise is possible).
Composer is assumed to be installed and configured in your PATH.

## Installation

HubKit is a PHP application, you don't install it as a dependency
and you don't you install it with Composer global.

> Currently there is no Phar version available, this is being worked-on.

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

See the [usage](usage.md) for all commands and usage instructions.

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

**Support for other adapters like BitBucket or GitLab will only ever happen once
all adapters support the same level of functionality and stability and performance
is not negatively affected.**

### Rejected feature requests

If you have some special requirements that are outside the scope of HubKit
it's properly better to "source fork" this repository and adjust it to
your own needs (*don't forget to change the name or indicate your providing
a modified version. To prevent confusion.*). But always keep the original credits!

*A source fork is nothing more then Git cloning the repository and then
creating a new (GitHub) repository, rather then using the "Fork button".*

## License

HubKit is provided under the [MIT license](LICENSE).

## Credits

This project is maintained by Sebastiaan Stok (aka. [@sstok](https://github.com/sstok)),
creator of Park-Manager.

HubKit was inspired on the GH Tool used by the Symfony maintainers,
no actual code from GH was used.

HubKit is not to be confused with [Hub](https://hub.github.com/) (from GitHub).
