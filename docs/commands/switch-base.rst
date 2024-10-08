switch-base
-----------

Switch the base of a pull request (and performs a rebase ``--onto`` to prevent
unwanted commits).

.. code-block:: terminal

    hupkit switch-base 22 1.6 [pr-number] [new-base]

Conflict Resolving
------------------

Switching the base of a branch may cause some conflicts.

When this happens you resolve the conflicts as normal, with using the
``git rebase --continue``, except now you you **don't** run this command!

Once all conflicts are resolved. Run the ``switch-base`` command again
(with the original parameters) and it should continue as normal.

.. caution::

    Do not push these changes manually, as this will not update the
    pull-request target base.
