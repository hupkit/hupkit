take
----

Take an issue (no pull request) to work on. In practice this checkouts
the issue as new branch using the title of the pull request.

.. code-block:: terminal

    hupkit take 22

By default the default branch (either main) is used as base, use the
``--base`` option to use a different one, either ``--base=1.x`` for
``upstream/1.x``.

.. code-block:: terminal

    hupkit take --base=1.x 22
