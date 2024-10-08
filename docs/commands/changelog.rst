changelog
=========

Generate a changelog, formatted according to http://keepachangelog.com/
with all *traceable* changes between releases (the specified) commits.

.. important::

    The changelog generating only works because of some conventions used in HuPKit.

    Only pull requests that were merged with the :doc:`merge` command (not the merge button
    in the web UI!) will be properly categorized and labeled.

    Other merges might be either ignored or show-up in the "Changed" section.

The commit range is automatically determined using the last tag on the (current)
branch and the current branch. Eg. ``v1.0.0..main``, but you can provide your
own range as you would with ``git log``, except now you get a nice changelog!

.. code-block:: terminal

    hupkit v1.0.0..main

Will produce something like:

.. code-block:: markdown

    ### Added
    - Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/hupkit/hupkit/issues/93)

    ### Changed
    - Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/hupkit/hupkit/issues/56)

    ### Deprecated
    - Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/hupkit/hupkit/issues/55)

    ### Removed
    - [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/hupkit/hupkit/issues/52)

To prevent the changelog from being to cluttered empty sections are left out,
but if you prefer, you can include these empty sections using the ``--all`` option.


.. code-block:: markdown

    ### Security
    - nothing

    ### Added
    - Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/hupkit/hupkit/issues/93)

    ### Changed
    - Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/hupkit/hupkit/issues/56)

    ### Deprecated
    - Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/hupkit/hupkit/issues/55)

    ### Removed
    - [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/hupkit/hupkit/issues/52)

    ### Fixed
    - nothing

.. note::

    Security section is placed higher than its original spec to ensure
    these changes are noticed.

Single-line Formatting
----------------------

Alternatively you can use the ``--online`` option to get a changelog without sections.

.. code-block:: markdown

    - Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/hupkit/hupkit/issues/93)
    - Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/hupkit/hupkit/issues/56)
    - Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/hupkit/hupkit/issues/55)
    - [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/hupkit/hupkit/issues/52)

Labeling Detection
------------------

HuPKit automatically uses the following labels (case insensitive) for special conventions,
like detecting breaking changes and deprecations:

* ``deprecation`` puts the change in the ``Deprecated`` section
* ``deprecation removal`` puts the change in the ``Removed`` section
* ``bc break`` marks the change with ``[BC BREAK]``

Lastly all merge commits (for pull requests) use as title the
``category #number title (author-id)`` convention,
like ``feature #68 [Release] add support for pre/post scripts (sstok)``.
