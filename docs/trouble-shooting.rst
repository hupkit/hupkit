Trouble Shooting
================

When HuPKit isn't working as expected.

First make sure you are using the latest stable release, older versions
are not covered by the backward compatibility policy.

When you experience trouble with repository splitting, run the ``clear-cache``
command to remove any corrupted repositories.

Self Diagnose
-------------

Run the ``self-diagnose`` command to get some information on misconfigurations,
or non locatable executables.

Local Configuration
-------------------

When you run into a local configuration error, and are not currently in
the "_hubkit" configuration branch, run HuPKit with the env ``HUBKIT_NO_LOCAL=true``
like ``HUBKIT_NO_LOCAL=true hupkit edit-config``.

And clear the cache using the ``clear-cache`` command afterwards.
