Park-Manager HubKit
===================

HubKit was created to ease the de development workflow of the Park-Manger project.
In short HubKit allows project(s) maintainers to easily manage there GitHub repositories.

* Take-on issues with ease.
* Checkout an existing pull-request.
* Merge pull-request with preservation of all information (description and replies).
* Keep your repository labels and common files (README, LICENSE, styling configuration)
  in sync.
* Merge version branches without mistakes.
* Create new releases with auto-generated changelog and without gaps in version numbers.

This tool is designed for project maintainers with a good knowledge of Git and GitHub.
If you have some special needs please the contributing section below.

**This project is still very young and in active development. Use at your own risk!.**

Requirements
------------

You need at least PHP 7.0, Git 2.10 and a GitHub account (enterprise possible).

Installation
------------

HubKit is an PHP application, you don't install it as an dependency
and you don't you install it with Composer global.

To install HubKit first choose a directory you want to keep the installation.
Eg. `~/.hubkit` or any of your choice.

**Caution:** Make sure you don't use a directory that is accessible by
others (like the web server root) as this may expose your API access-token!

Download HubKit by cloning the repository:

```bash
mkdir ~/.hubkit
cd ~/.hubkit
git clone https://github.com/park-manager-bot/hubkit.git .
```

And install the dependencies:

```bash
composer install -o --no-dev
```

Copy (don't rename) `config.php.dist` to `config.php`, and open it in your
favorite editor.

HubKit supports GitHub Enterprise, and therefor you can add multiple
hub configurations by there hostname. The default one is `github.com`.

```
'github' => [
    'github.com' => [ // hostname of the hub
        'username' => '', // fill-in your github username
        'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
    ],
//    'hub.mycorp.com' => [ // hostname of your GitHub Enterprise installation
//        'username' => '', // fill-in your github username
//        'api_token' => '', // fill-in the new GitHub authentication token (NOT YOUR PASSWORD!)
//    ],
],
```

You need to change the value of `api_token` with a GitHub access-token.

You can create a new token at: https://github.com/settings/tokens/new or (https://hub.mycorp.com/settings/tokens/new).
See the instructions in `config.php` for required scopes (**DON'T SELECT ALL SCOPES!**)   

Almost done. You'd properly want to create a command alias, rather then typing
`~/.hubkit/bin/hubkit` all the time. Add it to `~/.bash_profile` or 
`~/.oh-my-zsh/custom/example.zsh` if you use oh-my-zsh.

```bash
alias hk=~/.hubkit/bin/hubkit
```

Now run `hk self-diagnose` to check if everything is working correctly.
If all is green you are ready to use HubKit!

### Special note for Windows users

**HubKit has not been tested on Windows yet, it should work.
But you may encounter some problems.**

Note that HubKit expects a Unix (alike) environment.
You are advised to use the Git console or Bash shell (Windows 10+).

On Windows make sure to use `/c/Users/` rather then `c:/users/` for the alias.

Please open an issue in the issue-tracker when something is not working.
Or open a pull-request when you can fix the problem :+1:

### Updating

Updating HubKit is very easy, simple run `~/.hubkit/bin/update`.
Assuming that `~/.hubkit` is your installation directory.

Basic usage
-----------

Run `hk help` for a full list of all available commands and options.

**Note:** All commands except `help` and `diagnose` require you are in a Git repository,
and have Git remote `upstream` existing and pointing to the GitHub repository.

Versioning
----------

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

Feature request
---------------

HubKit is designed specifically for the maintenance workflow of the Park-Manager project.
But in the spirit of free-software it's made available to everyone.

This project is open-source, but doesn't accept feature requests
outside the scope of the project. Support for BitBucket or GitLab will never happen. 

We do accept pull-request for improvements and support for third-party
integrations like CodeClimate support for repository-config synchronization.

If you need special features there outside the scope of this project
it's properly better to "source fork" this project and adjust it to 
your own needs.

*A source fork is nothing more then cloning the repository and then
creating a new GitHub repository, rather then using the "Fork button".*

License
-------

HubKit is provided under under [MIT license](LICENSE).
