merge
=====

Merge a pull request with preservation of the original title/description and comments.
Plus some additional information for the [changelog](changelog.md) command.

To merge pull request `#22` in your repository simple run:

```bash
$ hubkit merge 22
```

And choose a category (feature, refactor, bug, minor, style, security).

**Note:** You may get a warning that some checks are pending, depending on your
repository's branch protection you may not be able to merge then.

Once the pull request is merged your local branch (if existent) is automatically
updated. Use the `--no-pull` option to skip pulling changes to your local base branch.

## Bat on the back

Once the pull request is merged the author (unless you are merging your own)
automatically gets little "pat on the back" for there work.

You can change the (default) message `Thank you @author` using the `--pat` option.

```bash
$ hubkit merge 22 --pat=':beers: @author !'
```

Or use the `--no-pat` option to skip it for this merge.

**Caution:** The `!` has a special meaning in the shell, use single quotes
to prevent expansion.

## Squash

Use the `--squash` option to squash the pull request before merging.


