branch-alias
============

Set/get the "master" branch-alias. Omit the `alias` argument to get the current alias.

To set a branch-alias for the "master" branch use:

```bash
$ hupkit branch-alias 1.0
```

To get the branch-alias for the "master" branch use the command without any arguments:

```bash
$ hupkit branch-alias
```

**Note:** In the past "master" was used as the default name, which has changed in recent
years to main. HuPKit will try and detect whether main or master is used.

## Composer branch-alias

When a composer.json file is present with a branch-alias, this is used instead.
