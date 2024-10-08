merge
=====

Merge a pull request with preservation of the original title/description and comments.
Plus some additional information for :doc:`changelog` rendering.

To merge pull request ``#22`` in your repository simple run:

.. code-block:: terminal

    hupkit merge 22

And choose a category (feature, refactor, bug, minor, style, security).

.. note::

    You may get a warning that some checks are pending, depending on your
    repository's branch protection you may not be able to merge then.

Updating Your Local Base
------------------------

Once a pull request is merged your local base branch (if existent) is
automatically updated. Use the ``--no-pull`` option to skip pulling
changes to your local base branch.

Automatic Cleanup
-----------------

If you are the author of the pull request, your "feature" branch is automatically
removed as it's no longer needed. Use the ``--no-cleanup`` option to skip this.

.. note::

    The "feature" branch is only removed when it's fully merged to the target branch.

Pat On The Back
---------------

Once the pull request is merged the author (unless you are merging your own)
automatically gets little "pat on the back" for their work.

You can change the (default) message ``Thank you @author`` using the ``--pat`` option.

.. code-block:: terminal

    hupkit merge 22 --pat=':beers: @author !'

Or use the ``--no-pat`` option to skip it for this merge.

.. caution::

    The ``!`` character has a special meaning in the shell, use single quotes
    to prevent expansion.

Squashing
---------

When there are multiple commits you are automatically asked to squash
the pull request before merging.

Use the ``--squash`` option to squash the pull request before merging.

Message Validating
------------------

Before merging, all commit messages are checked for common problems,
including work-in-progress commits, and messages with profanity.

.. note::

    The validator only performs some simple checks, 'clever' ways of
    bypassing the validation rules might not be detected.

