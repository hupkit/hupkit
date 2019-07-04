upmerge
=======

Merge the current branch to the next possible version branch (either merge 1.0 into 1.1).

**Caution:** Split repositories are (currently) [not automatically updated](https://github.com/park-manager/hubkit/issues/61) after an upmerge operation.

Some projects follow a 'merge bug fixes to the lowest supported branch first' approach,
and then (up)merge the lower branches into newer branches. Usually: `1.0 -> 2.0 -> master`.

But this process is tedious (boring) and error prone, the `upmerge` command makes
this boring work, as simple and save as possible.

The process ensures your local source *and* target branch are up-to-date, with
the recommended flags (`--no-ff` and `--log`), and pushes to upstream.

To use this command first checkout the lowest supported branch, either `1.0`.
And run the upmerge command.

```bash
$ git checkout 1.0
$ hubkit upmerge
```

That's it! The command automatically detects which branch `1.0` is to be merged into.

**Caution:** The `upmerge` command uses the Semantic Versioning schematics, versions
are automatically detected based on there precedence. **Don't use this command when
you use GitFlow!**

Need to merge more then one branch? Use `--all` option to merge the current branch
into the "next preceded version" branch, and that one into the it's next, and finally
then into the "master" branch.

```bash
$ git checkout 1.0
$ hubkit upmerge --all
```

## Conflict resolving

By merging branches into each other, you might cause some merge conflicts.

When this happens you can simple resolve the conflicts as you would with using
the `git mergetool`, then once all conflicts are resolved. Run the `upmerge`
command again and it will continue as normal.
