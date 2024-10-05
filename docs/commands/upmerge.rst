upmerge
=======

Merge the current branch to the next possible version branch (either merge 1.0 into 1.1).

Some projects follow a 'merge bug fixes to the lowest supported branch first' approach,
and then (up)merge the lower branches into newer branches. Usually ``1.0 -> 2.0 -> main``.

But this process is tedious (boring) and error prone, the ``upmerge`` command makes
this boring work, as simple and save as possible.

The process ensures your local source *and* target branch are up-to-date, with
the recommended flags (``--no-ff`` and ``--log``), and pushes to upstream.

.. tip::

    When using a monolithic repository set-up the ``upmerge`` command
    also perform a :doc:`repository splitting </split-repositories>`.

To use this command first checkout the lowest supported branch, either ``1.0``.

And run the upmerge command:

.. code-block:: terminal

    git checkout 1.0
    hupkit upmerge

That's it! The command automatically detects which branch ``1.0`` is to be merged into.

.. caution::

    The ``upmerge`` command uses the Semantic Versioning schematics,
    versions are automatically detected based on there precedence.

    **Don't use this command when you use GitFlow!**

Need to merge more then one branch? Use the ``--all`` option to merge
the current branch into the "next preceded version" branch, and that one
into it's next, and finally then into the "main" branch.

.. code-block:: terminal

    git checkout 1.0
    hupkit upmerge --all

.. note::

    The "main" branch is resolved from the ``main_branch`` in the repository
    configuration.

Conflict Resolving
------------------

By merging branches into each other, you might encounter some merge conflicts.

When this happens you resolve the conflicts as you would with using the
``git mergetool``, except now you you **don't** run ``merge --continue``!

Once all conflicts are resolved, run the ``upmerge`` command again, and it
should continue as normal.

Skipping Specific Branches
--------------------------

When you work with multiple long-term support branches you don't want
to merge from unmaintained branches.

Set the ``upmerge`` option to ``false`` for the unmaintained branches,
or disable the branch completely.

.. code-block:: php

    'branches' => [
        // ...

        '1.3' => ['upmerge' => false],

        // Or mark the branch as unmaintained/disabled
        '1.3' => false,
    ]

See :doc:`/config` for full details.
