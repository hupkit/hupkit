take
----

Take an issue (no pull request) to work on. In practice this checkouts the issue as new branch.

```bash
$ hupkit take 22
```

By default the default branch (either main) is used as base, use the `--base` option to use
a different one. Eg. `--base=1.x` for `upstream/1.x`.

```bash
$ hupkit take --base=1.x 22
```
