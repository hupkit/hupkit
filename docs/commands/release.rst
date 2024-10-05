release
=======

Creates a new release for the current branch. This creates a (signed) Git tag and GitHub release-page.

The ``release`` command automatically generates a changelog and allows to add additional
info using your favorite editor. Use the ``--no-edit`` option to skip the editing.

.. tip::

    You can change the content later using the GitHub web interface.

.. code-block:: bash

    $ hupkit release 1.0.0

Validation
----------

A version is expected to follow the SemVer format, either ``v1.0.0``, ``0.1.0`` or ``v1.0.0-BETA1``.

The leading ``v`` is automatically added when missing, and the meta version (alpha, beta, rc) is
changed to uppercase. And versions are validated against gaps to prevent mistakes.

.. tip::

    The version is automatically expended, meaning ``1.0`` is expended to ``1.0.0``.

Special Options
---------------

The ``release`` command has a number of special options:

* ``--all-categories``: Show all categories (including empty) in the changelog;

* ``--no-edit``: Don't open the editor for editing the release page, accept the changelog as-is;

* ``--pre-release``: Mark the release as a pre-release (not production ready);

* ``--title='A Bright New Year'``: Append a custom title to release name (after the version number);

.. note::

    A make sure that when the title contains special characters these are escaped
    accordingly. Or use single quotes (as shown) to prevent shell script expansion.

    https://stackoverflow.com/questions/15783701/which-characters-need-to-be-escaped-when-using-bash

Release Hooks
-------------

The ``release`` command allows to executes a script prior (pre) and/or after (post) after the release
operation. See :doc:`/release-hooks` for details.

Notes on Git tag Signing
------------------------

By default a Git tag of each release is cryptographically signed, using the ``release.signed``
repository configuration this can be disabled (for all releases) or set to automatic.

.. caution::

    Signing a Git tag ensures a release can always be verried to come from a
    trusted source. And is considered a best practice for secure distribution
    pipeline.

Make sure you have a signing key configured, and that your gpg/pgp application is
set-up properly.

Run ``self-diagnose`` command to test if this set-up correctly for you.

See also: https://git-scm.com/book/tr/v2/Git-Tools-Signing-Your-Work
