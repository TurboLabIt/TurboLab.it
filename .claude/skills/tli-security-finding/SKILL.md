---
name: tli-security-finding
description: >-
  What to do with a security weakness in TurboLab.it, from the moment it is suspected to the moment it is fixed
  and guarded: keep it out of public channels, check whether it is already known or accepted, prove it on a dev
  copy, record it in the local audit file, fix it, add its regression test. Use this skill whenever a
  vulnerability, a missing permission check, an injection, an XSS, a CSRF, a leak, or anything "an attacker could"
  comes up: found while working, reported by a tool or by a review after a push, or asked for ("is this
  exploitable?", "audit this", "fix finding #N"). Not for ordinary bugs: those go through tli-issue.
---

# Handling a security finding

The repository, the forum and the GitHub issues are public, and a fix is live only after a deploy. So a
security problem has a path of its own, and the first rule is about silence.

**Until it is fixed, a finding exists in two places only: the conversation with the user, and the local audit
file.** No forum topic, no GitHub issue (the `tli-issue` skill sends them here), no hint in a commit message or
a code comment written before the fix.

## 1. Is it already known?

```bash
python3 -I .claude/skills/tli-security-finding/scripts/audit.py status
```

`docs/security-audit.md` is the single record of every finding: open, postponed, accepted, resolved. It is
**local to the box and never committed** (`.gitignore` lists it). The script reads it and prints numbers,
severities and statuses, never the contents. Then read the file itself:

- **An accepted risk (wontfix) or a postponed one is a decision**, with its reason written down. Don't raise it
  again unless something new changes the reasoning, and then say what is new.
- A checkout without the file answers so: the file is kept by whoever maintains the site. Tell the user what you
  found, and don't recreate the file.

## 2. Prove it before believing it

A weakness read in the code is a hypothesis. Several past findings turned out false when tried: a premise that
doesn't hold in production, a check done elsewhere, a path nobody can reach. So:

- **Reproduce it on a dev copy**, without destroying anything: the `tli-logged-in-check` skill gives a session as
  any kind of user, `tli-browser-test` a real browser. Never try it against production.
- **Find who can reach it**: anyone, any registered user (registration is open), an author, only staff. And
  where: production runs in `prod` mode with no profiler; some weaknesses exist on a dev box only.
- **Rate it on that**, not on the scariest reading. The file's scale is 🔴 Critico, 🟠 Alto, 🟡 Medio or Basso,
  ⚪ Basso or Info. A rating that changes after verification keeps the old one: "(era 🟠 Alto)".
- **If you couldn't try it, say so**: an analysis by reading only is recorded as such.

## 3. Record it and tell the user

Take the next number (`audit.py next`) and add the finding to `docs/security-audit.md`, in Italian like the rest
of the file: a row in the table of open problems and a card
`### N. <severity> — <title>` with what is wrong, how it was verified, what an attacker gets, and the fix you
propose. Then tell the user: the finding, its real severity, the proposal. Whether to fix now, postpone or accept
is their decision; a postponed or accepted one moves to its section with the reason.

## 4. Fix it where it belongs

- **Permissions live in the Sentinel classes**, enforced in the controller with the `enforce…()` method. A
  control that is merely not shown, or hidden with CSS, is not a gate: the request can be written by hand.
- **Check on the server what the client sends**, again, even when the template already filtered it. Read what
  decides a permission from the entity, never from the request.
- **Fail closed**: a missing or empty secret must refuse, not let through. Compare secrets with `hash_equals()`.
- **Sanitize on input, escape on output**: article markup goes through the `HtmlProcessor` classes; a `|raw` in a
  template needs a reason that survives a hostile title.
- **The smallest change that closes it.** A wider cleanup is a separate commit.

## 5. Guard it with a test

One test per fixed finding, in `tests/Security/<Topic><Issue>Test.php`, with the header the others have:

```php
/**
 * 🔒 REGRESSION GUARD — docs/security-audit.md finding #N
 * ("<the finding's title>", RESOLVED <date>).
 *
 * What it asserts, what the response was while vulnerable, what it is now.
 */
```

- **It must fail without the fix**: put the old code back, run it, see it fail, restore. A test that passes both
  ways guards nothing.
- **Add a control**: the legitimate request, from the same kind of user, still works. A fix that blocks everyone
  passes the attack test too.
- A test over real HTTP against the running site (what nginx or a special page does, which the test kernel never
  sees) skips itself when the site can't be reached, like the existing ones.

## 6. Close the loop

1. In the audit file, move the card to the resolved section: `✅ risolto`, **Fix (date)** with what was done and
   any deliberate choice, and "Guardato da" with the test and what it proves.
2. Commit with the `tli-commit` skill. The message says what is now enforced. The audit file stays out: the
   helper refuses it anyway.
3. **Tell the user that production is exposed until the next deploy**, in so many words, with the severity.

## What never goes in public text

Commit messages, code comments, docs and tests are read by everyone. They may say what was closed, once it is
closed. They never say how to reach a control that is hidden from ordinary users, anything about internal
mailboxes, servers, keys or accounts, or a working recipe against something that is not deployed yet.

## Findings from tools

An automated review of a push, a scanner, a dependency alert: each claim is a hypothesis like any other. Check
it against the code, fix what is real in a new commit, and say why the rest doesn't apply. Never rewrite a
pushed commit to hide a mistake: the fix is the record.
