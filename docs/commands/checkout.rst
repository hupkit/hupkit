checkout
========

Checkout a pull request by number. This creates a new branch based of the user
and branch name to prevent conflicts with your local branches, either
``sstok--great-new-feature``.

.. code-block:: terminal

    hupkit checkout 123

.. note::

    If the branch already exists, and is related to the pull request,
    it's updated instead.

Unless the author of the pull request disabled this feature, it's possible
to push new changes to the user's fork using the ``git push author-name`` command.

.. note::

    HuPKit already configures the upstream of the branch for you,
    but always to be careful with forced pushes.
