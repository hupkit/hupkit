Configuration
=============

.. caution::

    Since HuPKit v1.4 using global configuration for repositories is deprecated.

    The examples in this chapter expect you are using HuPKit v1.4, any older
    versions are no longer supported.

If you are still using a version prior to v1.4 see :doc:`upgrade` how to
to update your configuration.

Schema Version
--------------

The ``schema_version`` defines the configuration schema version.

At this moment only ``1`` and ``2`` are accepted, note that version
``1`` is deprecated and will no longer work in HuPKit v2.0+.

As some advanced features require local configuration it's best to use
``schema_version=2``.

Authentication
--------------

Before HuPKit can be used to manage your repositories you first need to configure
the GitHub authentication credentials.

Get a new token at: https://github.com/settings/tokens/new

Use a unique and distinct name like: ``hupkit on computer-1 at 2016-11-01 14:54 CET``
with scopes: ``repo, user:email, read:gpg_key``.

HuPKit supports GitHub Enterprise, and therefor you can add multiple
hub configurations by there hostname; The default host is ``github.com``.

.. code-block:: php

    // config.php

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

.. _repository-configuration:

Repository Configuration
------------------------

Specific features like repository splitting, or disabling upmerge for
some branches only work when the configuration for a repository is set-up.

The configuration (and hook-scripts) are kept in a 'orphaned' branch "_hubkit".

.. caution::

    For historical reasons the branch is named ``_hubkit``, not ``_hupkit``,
    in HuPKit v2.0 this will change.

*An orphan branch has no relation to any other branch, it's fully separate,
and thus has no common parent. This same technique is also used by GitHub for
pages stored separate from the main branch.*

.. important::

    **No authentication credentials must be stored in the ``_hupkit`` branch!**

Initialize Configuration For a Repository
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

To create the HuPKit configuration branch run the ``init-config`` command.

The command will automatically import any existing global configuration for
the repository from the HuPKit main ``config.php`` file, and all files stored
in the ".hubkit" directory (of the current branch).

.. note::

    The root ``.gitignore`` file is automatically copied from your current
    branch to ensure no unrelated files are accidentally added when working
    on the ``_hubkit`` branch.

The ``_hubkit`` branch sits separate from your normal Git workflow and shares
no parenting to any existing branch.

The ``config.php`` file contains the configuration _only_ for the current repository.

.. code-block:: php

    <?php
    // config.php (in the `_hubkit` branch)

    return [
        'schema_version' => 2,

        // Adapter configuration (optional, when cannot be resolved automatically)
        // 'adapter' => 'github', //Defaults to github, currently no other adapters supported
        // 'host' => 'github.com',
        // 'repository' => 'organization/repository-name',

        // Which branch is considered the main branch. Either: 'main', 'master', '2.0', or 'trunk'
        // This configuration is used (among) for upmerging as the last last branch in the list.
        //
        // If not set this is automatically guessed at application boot, in v2.0 this will default to 'main'.
        //'main_branch' => null,

        // See Branches section below for supported configuration
        'branches' => [],

        // Branches-alias for 'named' branches
        //
        // This only applies to branches that are not explicitly versioned (like 1.0, 1.5)
        // The value should be a stable major.minor version (not: 0.5, master, 1.5-dev, 1.2-beta1) but 1.0, 1.5, 23.4
        // 'branches_alias' => [
        //     // 'main' => '1.0',
        //     // 'dev/main' => '1.0',
        //     // '1.x' => '1.5',
        // ],

        //'pull_request' => [
               // Either:
               //  * 'all' (default)
               //  * 'changed-only' (only split changed files matched in prefixes)
               //  * 'none' (don't split, same as using the `--no-split` command option)
        //    'split' => 'all',
        //],

        //'release' => [
              // Either:
              // 'all' (default)
              // 'changed-only' (only create split releases for prefixes that have changed since the last release)
        //    'split' => 'all',

              // Either: true (default; enabled), false (explicitly disabled), or null (use Git config 'tag.gpgSign')
        //    'signed' => true,
        //],
    ];

.. caution::

    It's best not to disable signing of Git tags.

Branches
~~~~~~~~

A branch name can be either any valid branch-name, a minor-version pattern
``2.x`/`v2.*``, or a regexp (without anchors or options) like ``/[1-2]\.\d+/``.


There is one special branch-name ``:default`` which allows to set-up the defaults
all branches (see below).

.. note::

    When an actual branch can be confused for a pattern (``1.x``) use ``#1.x`` instead.

    Using the ``#branch-name`` works for all branches and considers the branch-name
    as a literal instead of a pattern or regexp.

The ``:default`` branch defines the default configuration for _all_ branches, and
is later merged with the configuration of a specific branch. Use ``'ignore-default' => true``
for a specific branch configuration to ignore inherited defaults.

When no specific branch is found like 'main' or '2.1' the correct configuration
is resolved by traversing all (in order of listing) the patterns and regexp
until a matching version is found.

If no configuration was found the ``:default`` configuration is used.

Use ``false`` as value for a branch to mark that branch as unmaintained.
An unmaintained branch skips upmerging to *and* from this branch.

Using this option will give a warning whenever this branch is used for either merging,
releasing, taking an issue, etc.

.. tip::

    Use regexes like ``/[12]\.\d+/`` or ``/main|dev/trunk|(12\.0)/`` to use multiple
    versions or names at once (no anchors or head grouping, ``/main|master/`` not
    ``/(main|12\.0)/``).

    As the configuration file is still Php it's also possible use variables for sharing
    configuration between branches.

Each branch has the following options:

=================== ========= ============ =============================================================================================================
Name                Type      Default      Description
=================== ========= ============ =============================================================================================================
``sync-tags``       Boolean   ``true``     _Only when 'split' targets are *configured*; whether new tags should be synchronized when creating a release
``ignore-default``  Boolean   ``false``    Whether the ':default' configuration should be ignored
``upmerge``         Boolean   ``true``     Set to false to disable upmerge for this branch configuration, and continue with next possible version
``split``           array     ``[]``       See [Repository splitting](#splitting) for details.
``maintained``      Boolean   ``true``     ``true`` when maintained, use ``false`` as config value shorthand


.. code-block:: php

    // config.php

    return [

        // ...

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
    ];

.. note::

    A branch cannot start with Git refs ``{heads, tags, remotes, notes}/``, or be ``HEAD``.

.. _splitting-config:

Repository splitting (`split` config)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A list of paths (relative to repository root, *no patterns*) and the value,
either a 'push url' or an array with following options:
``['url' => 'push url', 'sync-tags' => false]``.

.. note::

    Missing directories are ignored with a warning. In HuPKit v2.0 this
    behavior is bound to change, use the branches configuration to ensure
    to paths aren't missing.


    Splits are expected to exist, see also :doc:`split-repositories`.

.. code-block:: php

    return [
        // ...

        'branches' => [
            ':default' => [
                // ...

                'split' => [
                    'src/Module/CoreModule' => 'git@github.com:hubkit-sandbox/core-module.git',
                    'src/Module/WebhostingModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
                    'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
                ],
           ],
        ],
    ];

See also :doc:`split-repositories`

Editing Repository Configuration
--------------------------------

.. caution::

    Always run the ``self-diagnose`` command to check your configuration,
    and see if there any configuration violations before switching back
    to the main branch or synchronizing with the remote (see below).

    If the application fails start due to a configuration error run HuPKit
    with the env ``HUBKIT_NO_LOCAL=true`` like ``HUBKIT_NO_LOCAL=true hupkit self-diagnose``.

To change the contents of the _hubkit branch run the ``edit-config`` command.

In practice this checksout the ``_hubkit`` branch, but also ensures no files
would be overwritten by this checkout (like a git-ignored config.php file.)

Synchronize Configuration
-------------------------

To update the configuration by either pushing your changes or pulling-in
the latest version from "upstream" simply run the ``sync-config`` command.

Only if your branches have diverged to much you need to resolve any
conflicts manually. Once done run the command again.

Use the ``self-diagnose`` command to check your configuration and see if any
update (push or pull) is needed.

Remote names
------------

By default HuPKit uses "upstream" as the remote-name for the main-repository.
If for any reason you need to change this, either because you use a different
convention or only use "origin" without a fork, you can use a local configuration
file to change this.

Add a ".hk_remotes" file at the root-level of your repository with the following contents:

.. code-block:: ini

    ; Commented line

    main = upstream
    fork = origin

The config "main" is the main repository which all team-members pull and push from,
"fork" is your own fork (if any).

.. note::

    This is about remote *names* not actual repository url's.

The file format is similar to an ini-file, a line can contain either a
comment (``; Comment``) *or* a variable ("main" or "fork"), no overwrites,
no different names (than shown), and comments can only be used on their
own line (not at the end of a variable declaration).

.. important::

    This file should be ignored by Git as it only applies to your own local
    ``.git/config`` file and remote names.

Use the ``-v`` flag with any command to see which value is used.

Whats Next?
-----------

Run the ``self-diagnose`` command to ensure everything is configured correctly.

.. tip::

    This command is best run from a Git repository.
