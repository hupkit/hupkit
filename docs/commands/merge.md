merge
=====

Merge a pull request with preservation of the original title/description and comments.
Plus some additional information for the [changelog](changelog.md) command.

To merge pull request `#22` in your repository simple run:

```bash
$ hupkit merge 22
```

And choose a category (feature, refactor, bug, minor, style, security).

**Note:** You may get a warning that some checks are pending, depending on your
repository's branch protection you may not be able to merge then.

Once the pull request is merged your local branch (if existent) is automatically
updated. Use the `--no-pull` option to skip pulling changes to your local base branch.

If you are the author of the pull request, your "feature" branch is automatically
removed as it's no longer needed. Use the `--no-cleanup` option to skip this.

**Note:** The branch is only removed when it's fully merged to the target branch.

## Bat on the back

Once the pull request is merged the author (unless you are merging your own)
automatically gets little "pat on the back" for there work.

You can change the (default) message `Thank you @author` using the `--pat` option.

```bash
$ hupkit merge 22 --pat=':beers: @author !'
```

Or use the `--no-pat` option to skip it for this merge.

**Caution:** The `!` has a special meaning in the shell, use single quotes
to prevent expansion.

## Squash

Use the `--squash` option to squash the pull request before merging.
