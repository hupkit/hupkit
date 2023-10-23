repo-create
===========

Create a new minimal empty GitHub repository. Wiki and Downloads are disabled by default.

```bash
$ hupkit repo-create hupkit/hupkit
```

This creates the "hupkit" repository in the "hupkit" organization. To create a
private repository (may require a paid plan) use the `--private` option.

**Tip:** Since HuPKit v1.2 use the `split-create` command to automatically create
repositories for split targets.

# Special options

The `repo-create` command has a number of special options:

* `--private`: Create private repository (may require a paid plan);

* `--no-issues`: Disable issues, usable when you have a global issue tracker or using
  a third party solution like Jira.
