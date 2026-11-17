---
name: tli-dev0-doctor
description: >-
  Find out why a TurboLab.it dev box is slow, flaky or failing before blaming the code: memory, the debugger
  hooked into every request, a search engine that doesn't answer, a scheduled job running, a reload that hasn't
  taken effect yet. Use this skill whenever something misbehaves on dev0 or on a dev copy without an obvious
  cause: "the site is slow", "tests fail at random", "MySQL has gone away", "search returns nothing", "my nginx
  change doesn't apply", "it passes alone and fails in the suite", and before any timing, benchmark or full test
  run. It carries a read-only check of the box and the list of what each symptom has turned out to be.
---

# Is it the code, or the box?

A dev box is small, shared and busy: 4 cores and 7 GB of RAM for the site, its database, a search engine, the
scheduled jobs that fire every minute, and every session working on it. Most of the mysterious failures seen
here were the box. Look at it first:

```bash
python3 -I .claude/skills/tli-dev0-doctor/scripts/doctor.py           # about 3 seconds
python3 -I .claude/skills/tli-dev0-doctor/scripts/doctor.py --quick   # without timing the pages
```

It changes nothing. Each line is `ok`, a `NOTE` (know it before you measure or test) or a `PROBLEM` (explains a
failure by itself): memory and swap, who holds them, whether the kernel killed something, the services, how
Xdebug is hooked in, whether the search engine answers, how long a page and a plain file take, what else is
running, disk, and whether forum e-mail is off.

## What each symptom has turned out to be

| Symptom | Cause | What to do |
|---|---|---|
| Every page takes 2 seconds more, files are instant | Xdebug set to connect to a debugger at every request, and something listening on its port that never answers | The doctor says so. The fix is `start_with_request=trigger`: it is the developer's debugging setup, so propose it |
| "MySQL server has gone away", "Connection refused", bursts of 500 in a test run | The kernel killed MySQL for lack of memory; it restarts by itself in seconds | Not a code bug. Free memory, then run again. Never two full test runs at once |
| A search test fails in the suite and passes alone | Under memory pressure a search takes longer than Meilisearch's cut-off, and it returns degraded results | The same: it is the box. Run that test alone to confirm |
| Search fails everywhere, since a system upgrade | Meilisearch was upgraded and refuses its older database: it restarts in a loop | `journalctl -u meilisearch` shows a database-version message. The cure is `upgrade_db = true` in its configuration: `docs/search.md` |
| A full test run takes several times longer than usual | Cold image cache after `scripts/cache-clear.sh`, a short memory, or the Xdebug wait above | The suite takes about 2 minutes on a healthy box. Compare like with like: warm cache, same memory state |
| An nginx change "doesn't work" right after the reload | The reload is graceful: the command returns while the old workers still answer | Ask again in a short loop until the answer changes, or read the loaded configuration with `sudo nginx -T`. Never judge from one immediate request |
| A PHP change isn't picked up | On a dev box the files are re-read by themselves. Production and staging never re-read them: there, only a PHP-FPM reload or restart does it | A reload is enough: it empties the opcode cache exactly as a restart does |
| One timing is much worse than the others | A scheduled job fired: they run every minute | Take several timings and compare medians: the `tli-lighthouse` skill does it for page loads |
| Every page fails after a build or an update | `vendor/` or `public/build` is missing or half-made | The doctor says which. `vendor/`: the `tli-deps` skill. Assets: `yarn dev` |

## Before measuring or testing anything

- **Check memory first.** With less than about 800 MB available, or most of the swap in use, results are noise
  and tests fail at random. A full test run, a browser and a build each need room.
- **Look at who else is there.** Other sessions share the checkout, the database and the CPU. Their test run
  makes yours flaky, and yours theirs. `scripts/test-runner.sh` restarts nginx and clears the cache for everyone,
  and a build rewrites `public/build`.
- **Don't trust a single run**, of a timing or of a failing test. Run it again, alone.
- **`var/log/dev.log` is never rotated**: it holds months. A line found there may be very old: filter by date.

## Changing the box

The doctor only looks, and so does this skill. The box's configuration lives in the repository, in
`config/custom/dev/` (the MySQL and PHP-FPM settings that keep memory low, the nginx files), and
`scripts/cache-clear.sh` installs it. A change there is a change to everyone's dev box: propose it, with what
you measured, and let the maintainer decide. Restarting a service that is down is fine; so is anything on the
copy's data, which is thrown away and re-imported from production from time to time.
