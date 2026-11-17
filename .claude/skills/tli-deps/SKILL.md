---
name: tli-deps
description: >-
  How third-party dependencies (Composer and Yarn) are added, updated and kept safe on TurboLab.it: always the
  latest stable release that is at least 5 days old, resolved on the dev box only and shipped to production
  through the lock files. Use this skill whenever composer.json, composer.lock, package.json or yarn.lock is
  involved: "add this package", "update the dependencies", "composer update", a lock file that changed by itself,
  a Dependabot alert or pull request, a dev box left without vendor/, or a deploy that failed on install. It
  carries a status report, a lock comparison and a check that tells whether a full resolve would work before
  anything is removed.
---

# Dependencies: latest stable, five days old, through the lock

Three rules, by the project's choice:

- **Always the latest stable version.** Never an old pin, never a version copied from somewhere else: check what
  is current and take that.
- **Nothing younger than 5 days.** A release that turns out to be compromised is usually pulled within days, so
  both package managers hold new releases back: `config.policy.cooldown.period` in `composer.json` and
  `npmMinimalAgeGate` in `.yarnrc.yml`. "Latest stable" therefore means the latest one past the cooldown. The
  period is settled: don't propose another.
- **Only the dev box resolves.** Production and staging install exactly what the lock files say, at every
  deploy. The cooldown and the choice of versions happen on dev; the lock carries the result.

`scripts/deps.py` shows where things stand:

```bash
D=.claude/skills/tli-deps/scripts/deps.py
python3 -I $D status           # the cooldown in force, vendor/ present or not, what the locks changed since the last commit
python3 -I $D lock-diff <rev>  # the same list of packages, old → new, against another commit
python3 -I $D resolve-check    # would a full Composer resolve work right now? (on a copy: nothing is touched)
```

## Locks that change by themselves

On a dev box the dependencies are re-resolved from scratch as part of normal work:

- `scripts/cache-clear.sh` removes `vendor/` and `composer.lock`, then installs: a full resolve, under the
  cooldown.
- The dev asset build bumps the direct ranges in `package.json` and removes `yarn.lock` before installing. That
  is on purpose: with the old lock, a dependency of a dependency would stay at any version that still fits its
  range, security fixes included.

So `composer.lock` and `yarn.lock` showing new versions in `git status` is routine, whoever caused it. `status`
lists what moved. **Commit it at once, alone** (`composer update`, `yarn update` or `composer+yarn update`),
with `package.json` when its ranges moved too: the `tli-commit` skill. Don't leave it lying, and don't raise it
with the user.

## Adding or upgrading one

```bash
composer require vendor/package        # or: yarn add package
```

Both take the newest release past the cooldown by themselves. Then:

- **The manifest and its lock go in the same commit**, with the code that uses the dependency. A `package.json`
  committed without its `yarn.lock` makes the next deploy resolve on production, which is exactly what must not
  happen: if that resolve fails, the build stops and the deploy ends red.
- **A dependency that is added is used.** `sass`, `sass-loader` and `webpack-notifier` were removed because
  nothing called them: don't bring them back without wiring them up.
- **Nothing fetched is committed**: not `vendor/`, not a built asset, not a copy of a library. What the project
  needs is installed or generated at install time, from pinned versions.
- One of **our own packages** (`turbolabit/*`) is not a third-party dependency: the `tli-package-release` skill.

## When a resolve fails

**Composer, on a dev box, after a security release.** For up to 5 days after a fix is published, advisories
block every older version while the cooldown holds the fix back: no version satisfies both, and a full resolve
fails. `scripts/cache-clear.sh` has removed `vendor/` and the lock by then, and the box is left without them.

- Before running `scripts/cache-clear.sh`, or telling someone to: `python3 -I $D resolve-check`. It tries the
  same resolve on a copy of `composer.json` and says whether it passes and what it would change.
- If the box is already without `vendor/`: `git checkout composer.lock && composer install` brings back what
  production runs. Or `composer update --no-cooldown`, which takes the fix although it is young (`install` has
  no such option): then look at what it picked before committing the lock.

**Yarn** has no such lockout: it never drops a vulnerable version while resolving, so the fix simply arrives
with the first dev build after its cooldown. A release that is too young fails only when no older version fits
the range: widen nothing, wait.

**On a deploy**: a failed install or a failed build leaves the previous `public/build` in place and the site up,
and ends the deploy red. Read why it failed; the usual cause is a manifest committed without its lock.

## Alerts and pull requests

- **Dependabot security updates stay on.** That is settled too. An alert on a dependency of a dependency is
  normally fixed by the next dev build, which resolves everything again.
- **Every Dependabot pull request gets merged or closed**: closed when a dev build has already fixed the alert.
  One left open for 90 days pauses Dependabot's security updates for the whole repository, silently.
  `gh api repos/TurboLabIt/TurboLab.it/automated-security-fixes` tells whether they are paused.
- If alerts pile up, check that dev builds really run and really remove `yarn.lock` first.

## Keep in mind

- **Composer puts `config.policy` in the lock's content hash** (since 2.11). Changing the cooldown therefore
  needs `composer update --lock --no-install --no-scripts --no-plugins --no-audit` in the same commit, or every
  install warns that the lock is out of date.
- **A lock diff is checked before it is committed**: versions, references, hashes. `lock-diff` shows the packages;
  a diff with conflict markers or a hand edit isn't a bump.
- Dependencies reach production with the deploy that follows the commit of the lock, not before.
