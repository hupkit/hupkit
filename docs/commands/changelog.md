changelog
=========

Generate a changelog, formatted according to http://keepachangelog.com/
with all detectable changes between releases (the specified) commits.

**Caution:**

> The changelog auto formatting works because of some conventions used in HubKit.
>
> Only pull requests that were merged with the [merge](merge.md) command (not the merge button!)
> will be properly categorized and labeled, all others show-up in he "Changed" section.

The commit range is automatically determined using the last tag on the (current)
branch and the current branch. Eg. `v1.0.0..master`. But you can provide your
own range as you would with `git log`, except now you get a nice changelog!

```bash
$ hubkit v1.0.0..master
```

Will produce something like:

```markdown
### Added
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)

### Changed
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)

### Deprecated
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)

### Removed
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)
```

To prevent the changelog from being to cluttered empty sections are left out,
but if you prefer you can include these empty sections using the `--all` option.

```markdown
### Security
- nothing

### Added
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)

### Changed
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)

### Deprecated
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)

### Removed
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)

### Fixed
- nothing
```

**Note:** Security is placed higher then the original spec to ensure they are noticed.

## Single line formatting

Alternatively you can use the `--online` option to get a changelog without sections.

```markdown
- Introduce a new API for ValuesBag ([sstok](https://github.com/sstok)) [#93](https://github.com/park-manager/hubkit/issues/93)
- Clean up ([sstok](https://github.com/sstok)) [#56](https://github.com/park-manager/hubkit/issues/56)
- Great new architecture ([sstok](https://github.com/sstok), [someone](https://github.com/someone)) [#55](https://github.com/park-manager/hubkit/issues/55)
- [BC BREAK] Removed deprecated API ([sstok](https://github.com/sstok)) [#52](https://github.com/park-manager/hubkit/issues/52)
```

### Labeling detection

HubKit automatically uses the following labels (case insensitive) for special conventions,
like detecting breaking changes and deprecations:

* `deprecation` puts the change in the `Deprecated` section
* `deprecation removal` puts the change in the `Removed` section
* `bc break` marks the change with `[BC BREAK]`

Lastly all merge commits (for pull requests) use the `category #number title (author-id)` convention
like `feature #68 [Release] add support for pre/post scripts (sstok)`.
