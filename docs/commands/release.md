release
=======

Make a new release for the current branch. This creates a signed Git tag and GitHub release-page.

The `release` command automatically generates a changelog and allows to add
additional info using your favorite editor. Use `--no-edit` to skip the editing,
you can change the content later using the GitHub web interface.

```bash
$ hubkit release 1.0.0
```

A version is expected to follow the SemVer format, eg. `v1.0.0`, `0.1.0` or `v1.0.0-BETA1`.
The leading `v` is automatically added when missing and the meta version (alpha, beta, rc) is
upper cased.

**Tip:**

> The version is automatically expended, so `1.0` is expended to `1.0.0`.
>
> And versions are validated against gaps, so you can't make a mistake.

## Special options

The `release` command has a number of special options:

* `--all-categories`: Show all categories (including empty) (same as `--all` of the [changelog](changelog.md) command);

* `--no-edit`: Don't open the editor for editing the release page, accept the changelog as-is;

* `--pre-release`: Mark the release as a pre-release (not production ready);

* `--title="A Bright New Year"`: Append a custom title to release name (after the version number);

## Release hooks

The `release` command allows to executes a script prior (pre) and/or after (post) after the release
operation. See [Release Hooks](../release-hooks.md) for details.

*Note:* You need at least HubKit version 1.0.0-BETA18 to use release hooks.

## Notes on Git tag signing

The Git tag of each release is cryptographically signed, **this cannot be disabled**.
Make sure you have a signing key configured, and that your gpg/pgp application is
set-up properly.

Run `hubkit self-diagnose` to test if this set-up correctly for you.

See also: https://git-scm.com/book/tr/v2/Git-Tools-Signing-Your-Work
