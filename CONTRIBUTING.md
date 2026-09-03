# Contributing to LoginLdap

Bug reports and pull requests are welcome at
[matomo-org/plugin-LoginLdap](https://github.com/matomo-org/plugin-LoginLdap).

## Pre-push checks

This repository ships a pre-push hook in `.git-hooks-matomo/` that runs PHPStan over the files a push
changes, so a finding shows up before CI does. Git does not pick it up on its own — point `core.hooksPath`
at the directory once per clone:

```
git config core.hooksPath .git-hooks-matomo
```

`add-git-hooks-to-plugins.sh` in [matomo-developer-tools](https://github.com/innocraft/matomo-developer-tools)
does that across every plugin in a Matomo checkout, and the DDEV environment script calls it during setup.

The hook is the canonical copy from
[plugin-ci-workflows](https://github.com/matomo-org/plugin-ci-workflows/blob/main/hooks/pre-push), and the
PHPStan workflow fails if the two drift apart. Sync it with:

```
cp path/to/plugin-ci-workflows/hooks/pre-push .git-hooks-matomo/pre-push
```
