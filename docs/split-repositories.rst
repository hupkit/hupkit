Managing Split Repositories
===========================

A special feature of HuPKit is the ability to handle monolith project developments,
instead of having separate Git repositories for each package, all are housed in a
central repository from where all work is coordinated.

.. note::

    Before you continue make sure `splitsh-lite`_ is installed and can be
    found in your ``PATH`` environment (no shell alias!).

Configuration
-------------

To make this "repository splitting" work, a number of targets must be configured.
Each target consists of a prefix (directory path), target repository and some
additional options like if tag-synchronizing is enabled.

.. tip::

    Suffice to say, the split target repositories must exist!

    They are not created automatically, but you can use the  ``split-create``
    command to create the repositories, any already existing ones are ignored,
    missing ones created.

See :ref:`Repository splitting configuration <splitting-config>`, how to
configure your splits.

To test if the configuration is correct run the ``split-repo --dry-run``
command to see what would have happened.

Everything correct? Then run the ``split-repo`` command.

.. note::

    Depending on the total amount of commits, split targets, and your
    internet speed, this process might take some time to complete.

Splitting After a Pull Request Merge
------------------------------------

Once the repository splitting is configured, you want to make sure the
split repositories are up-to-date.

You can either run the ``split-repo`` command at any time, or split
the repository after you :doc:`merge a pull request <commands/merge>`_,
this process may take some time depending of number of commits and targets.

.. tip::

    If you need to merge more than one pull-request you'd properly want
    to hold-of the split operation until you're done.

    Use ``no-split`` command option to skip the splitting, or set the
    ``pull_request.split`` repository configuration to ``none``.

.. caution::

    Splitting is automatically skipped when the ``--no-pull`` command option
    is provided.

Splitting With a Release
------------------------

The ``release`` command works a little different here. When making a new release the
split operation is always performed! You cannot skip this. However you can skip
the synchronizing of tags for certain split repositories by setting the ``sync-tags``
config to false.

.. code-block:: php

    return [
        'branches' => [
            ':default' => [
                'sync-tags' => true, // disable for all (or...)
                'split' => [
                    // Disable/enable per repository target
                    'src/Bundle/CoreBundle' => ['url' => 'git@github.com:park-manager/core-bundle.git', 'sync-tags' => true],
                ],
            ],

            // ...
        ],
   ];

Existing tags in split repositories are always ignored.

Changed-only Releases
~~~~~~~~~~~~~~~~~~~~~

By default a new release will create tags for all split targets, but it's
possible to ignore creating tags for split targets which haven't changed
since the last release.

.. note::

    All split targets still use the version increments of the monolith repository.

    It's not possible to have package B with a different versioning when
    synchronizing of tags (for the target) is still enabled.


.. code-block:: php

    return [
        // ...

        'release' => [
            'split' => 'changed-only',

            // ...
        ],
   ];

.. _splitsh-lite: https://github.com/splitsh/lite
