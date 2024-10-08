HuPKit
======

In short HuPKit helps project(s) maintainers with managing their GitHub repositories.

Merging pull requests, creating new releases, merging older versioned branches into newer
once's, and much more.

You need at least PHP 8.1, Git 2.10 and a GitHub account (GitHub Enterprise is supported).
`Composer`_ should be accessible in the PATH-env variable.

*HuPKit is not fully tested on Windows.*

.. note::

    On October 23rd 2023 the repository was moved to it's own organization, and renamed
    to HuPKit. The old PHP namespace has been left unchanged. In version 2.0 this will change.

HuPKit is provided under the `MIT license <https://github.com/hupkit/hupkit/LICENSE>`_ and
maintained by Sebastiaan Stok (aka. `@sstok <https://github.com/sstok>`_).

Features
--------

* Taking issues to work on, checking out an existing pull request
  to either fix manually, or changing the base pr base branch (rebase)
  with ease.

* Merging pull requests with preservation of all information,
  and additional metadata storage for :doc:`commands/changelog` rendering;

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

HuPKit is a PHP application, you don't install it as a dependency, and you
don't install it with Composer global either.

To install HuPKit first choose a directory where you want to keep the installation.
Either ``~/.hupkit`` or any of your choice.

.. caution::

    Make sure you don't use a directory that is accessible by
    others (like the web server root) as this may expose your API access-token!

Download HuPKit by cloning the Git repository:

.. code-block:: terminal

    mkdir ~/.hupkit
    cd ~/.hupkit
    git clone https://github.com/hupkit/hupkit.git .

Checkout the `latest version <https://github.com/hupkit/hupkit/releases>`_.

.. code-block:: bash

    git checkout tags/1.0.0 -b version-1.0.0

And install the dependencies:

.. code-block:: terminal

    ./bin/install

Special note for Windows users
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**HuPKit has not been tested on Windows yet. It should work,
but you might encounter some problems.**

Note that HuPKit expects a Unix (alike) environment.
You are advised to use the Git console or Bash shell (Windows 10+).

.. tip::

    Please open an issue in the issue-tracker when something is not working.

    Or open a pull-request when you can fix the problem.

Updating
~~~~~~~~

Updating HuPKit is very easy. Go to the HuPKit installation
directory, and run ``./bin/upgrade``.

Done, you now have the latest version.

Basic Usage
-----------

Before you can use HuPKit, a number of things must be configured first:

- You need a GitHub authentication token;
- Git must be configured in your PATH-env;
- PHP must be accessible from the PATH-env.

All commands, except: ``help``, ``repo-create`` and ``self-diagnose``,
require you are in a Git repository and have Git remote ``upstream`` existing,
and pointing to the GitHub main repository from which all work is coordinated
(not your fork).

Configuring
~~~~~~~~~~~

See the :doc:`config` section how to configure your
GitHub credentials, and set-up repository splittings.

Commands
~~~~~~~~

Now that HuPKit is set-up you can follow-up with the following articles:

.. toctree::
    :maxdepth: 1

    split-repositories
    commands/release
    commands/upmerge
    commands/switch-base
    commands/merge
    commands/checkout
    commands/take
    commands/repo-create
    commands/changelog

Run ``hupkit help`` for a full list of all supported commands, and there options.

If something doesn't work as expected you can find useful tips
in the :doc:`trouble-shooting`.

And finally for the hook-scripts you can find all the available public
services in the :doc:`container-services` reference guide.

.. _`composer`: https://getcomposer.org/doc/00-intro.md
