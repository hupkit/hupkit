repo-create
===========

Create a new minimal empty GitHub repository. Wiki and Downloads are disabled by default.

```bash
$ hubkit repo-create park-manager/hubkit
```

This creates the "hubkit" repository in the "park-manager" organization. To create a
private repository (may require a paid plan) use the `--private` option.

# Special options

The `repo-create` command has a number of special options:

* `--private`: Create private repository (may require a paid plan);

* `--no-issues`: Disable issues, usable when you have a global issue tracker or using
  a third party solution like Jira.
