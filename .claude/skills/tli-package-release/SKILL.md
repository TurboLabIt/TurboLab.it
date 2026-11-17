---
name: tli-package-release
description: >-
  Ship a change made in one of the project's own Composer packages (turbolabit/*: service-entity-plus-bundle,
  paginatorbundle, php-encryptor, php-symfony-messenger, php-symfony-basecommand, php-foreachable, php-traits) so
  that production actually gets it. Use this skill whenever code under vendor/turbolabit/ or in ../php-packages
  was changed, or is about to be: "ship the package", "bump the lock", "release the bundle", "why doesn't
  production have my change", or a fix whose real place is in a package. It carries the status check (lock,
  working copy, Packagist), the lock-only bump, and a test of what production would install.
---

# Shipping a change in one of our packages

The project uses a few packages of its own, each a separate public repository, all required as `dev-main`. Two
facts shape everything here:

- **Production installs from `composer.lock`** at every deploy. A package you pushed changes nothing there until
  the lock names its new commit.
- **A dev box doesn't use the lock for them.** After Composer runs, `scripts/cache-clear.sh` replaces
  `vendor/turbolabit/*` with links to the working copies in `../php-packages/`. So editing a file under
  `vendor/turbolabit/` edits the package's own repository, your change works at once on the dev box, and a
  forgotten lock bump never shows here. It shows on production, as the old behaviour.

`scripts/packages.py` tells where each package stands and does the mechanical parts.

## 1. Change the package, in its own repository

Work in the working copy (`../php-packages/<folder>`, where `vendor/turbolabit/<name>` points). It is a repository
of its own, with its own history, public on GitHub: commit and push it there, on `main`. The `tli-commit` helper
works from inside it too, also where the working copies sit on a shared folder that shows every file as
executable: it records a file's mode the way git is configured to there. Keep a package general: what only this site needs belongs in the project.

## 2. Wait for Packagist

```bash
P=.claude/skills/tli-package-release/scripts/packages.py
python3 -I $P status
```

For every package it shows the commit in the lock, the one Packagist serves, and the working copy's, with what to
do next: commit, push, wait, or bump. Packagist picks a push up in seconds; until it does, a bump would lock the
old commit again.

## 3. Bump the lock, and only the lock

```bash
python3 -I $P bump service-entity-plus-bundle
```

It runs `composer update turbolabit/<name>` with `--no-install --no-scripts --no-plugins --no-audit`: the lock
moves to the new commit, `vendor/` and its links stay as they are, and no other package is touched. It refuses
when the working copy has work that isn't pushed, or when there is nothing to bump. A package that only other
packages require (`php-traits`, `php-foreachable`) is bumped by naming it, like the others.

## 4. Test what production will get

The project's tests on the dev box already ran against your change, through the links. What they can't see is
what a clean install does. When the change touches something the project's code is compiled against (a method
signature, an interface, a typed constant, a class that moved), run:

```bash
python3 -I $P prod-test composer.lock <the project files that go with the change>
```

It takes the last commit, lays those files over it, installs from the lock without the dev packages into a
temporary folder, and loads every class of the project and of its packages once. A mismatch is a fatal error at
class load, and it shows here instead of on production.

## 5. One commit: the lock and the code that needs it

Commit `composer.lock` **together with** the project code that depends on the new package, with the `tli-commit`
skill. The sharp case is a typed constant: `const SortDirection` in a package against `const string` in a class
of the project that overrides it is a fatal error the moment the class loads, so the two must reach production
in the same deploy. A bump that needs no project change is a commit of the lock alone.

## What must stay true

- **`composer.json` has no `repositories` entry of type `path`.** If one comes back, the next lock names local
  folders, and production's install fails on a path that doesn't exist there. The links are made after Composer
  runs, on dev only, precisely so that there is one lock and it is the Packagist one.
- **Nothing under `vendor/` is ever committed**, links included.
- **The cooldown doesn't hold `dev-main`**: the 5-day rule applies to tagged releases of other people's packages,
  not to these.
- A package change reaches production with the deploy that follows the lock bump, not before.
