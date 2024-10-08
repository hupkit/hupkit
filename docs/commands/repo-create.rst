repo-create
===========

Create a new minimal empty GitHub repository.
Wiki and Downloads are disabled by default.

.. code-block:: terminal

    hupkit repo-create hupkit/hupkit

This creates the "hupkit" repository in the "hupkit" organization. To create a
private repository (may require a paid plan) use the ``--private`` option.

.. tip::

    Use the ``split-create`` command to automatically create
    repositories for split targets.

Special Options
---------------

The ``repo-create`` command has a number of special options:

* ``--private``: Create private repository (may require a paid plan);

* ``--no-issues``: Disable issues, when you have a global issue tracker
  or using a third party solution like Jira.

* ``--host``: Use a specific host configuration, either ``--host=development.example.com``.

.. note::

    The ``--host`` optional is mostly usable when you need to create
    a repository a custom GitHub enterprise host.
