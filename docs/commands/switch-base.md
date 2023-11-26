switch-base
-----------

Switch the base of a pull request (and performs a rebase to prevent unwanted commits).

```bash
$ hupkit switch-base 22 1.6 # [pr-number] [new-base]
```

#### Conflict resolving

Switching the base of a branch may cause some conflicts.

When this happens you can simple resolve the conflicts as you would with using `git rebase --continue`,
then once all conflicts are resolved. Run the `switch-base` command (with the original parameters)
again and it will continue as normal.

**Do not push these changes manually as this will not update the pull-request target base.**
