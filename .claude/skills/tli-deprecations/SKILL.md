---
name: tli-deprecations
description: >-
  Find the deprecations that are really live in the TurboLab.it code, which neither the green test suite nor the
  logs tell reliably. Use this skill whenever deprecations are the question: "are there deprecations", "is it ready
  for the next PHP / Symfony / Doctrine version", after a composer update or a PHP upgrade, when a log shows a
  "Deprecated:" line, or before saying that one is fixed. It carries a runner that lists every deprecation the
  tests trigger (also the ones raised in vendor code), a probe that finds every call site of a Doctrine
  deprecation, a template check and a dated summary of the dev log.
---

# Finding live deprecations

The test suite fails on a deprecation (`failOnDeprecation`), and yet a green suite doesn't mean there are none.
Four things hide them:

- **The suite only fails on those raised in `src/`.** With `ignoreIndirectDeprecations`, a deprecation raised in
  `vendor/` or in one of our packages stays silent even when a line of the project caused it: a float passed to a
  package method that wants an int, a string where Doctrine wants an enum. That setting is deliberate and stays
  as it is: this skill is how to look past it when you need to, not a reason to change the configuration.
- **Doctrine reports each kind once per process.** A log names the first offender; fix it and the next one
  appears. One reported mapping once turned out to be 9 mappings and 40 query-builder calls.
- **The dev log is never rotated.** It reaches back months, so a line found in it may have been fixed long ago.
- **A tool only sees code that runs.** A line no test reaches triggers nothing.

`scripts/deprecations.py` deals with each. It changes nothing in the project: the test configuration is copied to
a temporary folder and adjusted there.

```bash
D=.claude/skills/tli-deprecations/scripts/deprecations.py
python3 -I $D suite tests/Editor tests/Smoke/HomeTest.php   # every deprecation those tests trigger, once each
python3 -I $D suite                                         # the whole suite (minutes, heavy: see below)
python3 -I $D doctrine tests/Editor                         # each Doctrine deprecation with all its call sites
python3 -I $D twig                                          # deprecated syntax in templates/
python3 -I $D log --days 14                                 # the dev log: each message, first and last day seen
```

## Which one, when

- **After an upgrade, or to answer "are there any"**: `suite`, on the tests that cover what changed, or on
  everything when the question is general. It lists file, line and message, also for what the committed
  configuration ignores.
- **A Doctrine deprecation**: `doctrine`. It loads a probe before everything else that switches Doctrine's
  once-per-process rule off and records, for every hit, the first line outside Doctrine that made the call: ours,
  or a package's. Fix them all in one pass instead of one per run.
- **A "Deprecated:" line seen in a log**: `log` first. It shows when each message was first and last written. A
  message whose last day is weeks old is most likely fixed already: check that its file and line still match
  today's source before reporting it.
- **Templates**: `twig`.

## Before you trust a clean result

- **Each run proves itself.** `suite` and `doctrine` add a control test that triggers a deprecation on purpose,
  and fail if it isn't listed: "none found" is only said when finding works. If you build a check of your own,
  do the same: a filter that matches nothing looks exactly like a clean result.
- **A fix is proven by bringing the line back.** Run again with the fixed file restored from the last commit: the
  deprecation must reappear, at that line.
- **Code that no test runs isn't covered.** For a pattern that can come back unnoticed, a runtime check isn't
  enough: add a static one. `tests/Convention/DoctrineSortDirectionTest.php` reads the source of `src/`, `tests/`
  and our packages for string sort directions, and fails closed when it can't tell; `ImmutableDatesTest.php`,
  next to it, does the same for the mutable `DateTime`, and reads every entity's mapping from Doctrine.
- **Read the raw output of a command whose result you filter.** A command that died on a wrong option and an
  empty `grep` once passed for a clean run.

## Where the fix goes

- In the project when a project line causes it, even if the message names a vendor file.
- In the package when one of ours raises it: the `tli-package-release` skill ships it.
- A deprecation that only an upgrade of someone else's package removes is noted, not patched in `vendor/`.

## On the dev box

The whole suite takes minutes and a lot of memory on dev0, and it writes to the same database other sessions
test against. Name the tests when you can, and don't start a full run while another one is going. Production and
staging write their logs as JSON: `log` reads the dev format only.
