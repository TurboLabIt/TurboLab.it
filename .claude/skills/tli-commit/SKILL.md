---
name: tli-commit
description: >-
  How a change is committed and pushed on TurboLab.it: straight to main, as soon as it is ready and tested, with
  only your own files in it and nothing secret. Use this skill every time you are about to commit or push in this
  repository: "commit", "commit and push", "push it", a fix or a feature that is finished, a lock file that changed.
  It carries the helper that commits exactly the paths you name (other sessions share this checkout), scans the
  added lines for secrets (the repository is public) and pushes; plus the message style, what never gets
  committed, and what to do when the push is refused.
---

# Committing on TurboLab.it

The checkout in `/var/www/turbolab.it` is the one that gets committed, and it goes **straight to `main`**: no
branch, no pull request, no asking. Three things make that safe, and this skill is about them:

- **Only finished work.** A change is committed when it is ready and tested: one commit per unit of work, with its
  `docs/*.md` in the same commit. Half-done work, failing tests, or something waiting for the user's decision
  stays uncommitted.
- **Only your own files.** Other sessions may be editing, staging and committing in this same checkout. A blanket
  `git add -A` or `git commit -a` would publish their half-done work under your message.
- **Nothing secret.** The repository is public on GitHub, and nobody reviews a commit before it is pushed.

What to commit: `$ARGUMENTS`. If that's empty, or not expanded, it's the work you have just finished.

## 1. Know what is yours

`git status --short` right before committing. Name the files you changed, one by one: a file you never touched is
someone else's, even when it looks related. Never stash, reset, check out or reformat it.

## 2. Write the message

In the style of `git log`: **one descriptive line**, `area: what changed and why`, as long as it needs to be, in
plain English. It says what the user or the site gets, then why the old behaviour was wrong, then how it was
checked (`Tests: …`). Not a list of files.

- A fix for an issue says `Closes #N`: the `tli-issue-close` skill has the rest of that procedure.
- A lock-file bump has its own short messages: `composer update`, `yarn update`, `composer+yarn update`.
- After a blank line, the attribution trailer of whoever wrote it.
- Nothing that helps an attacker: what was fixed, never how it could be exploited.

Save it as a file in your scratchpad.

## 3. Commit and push with the helper

```bash
C=.claude/skills/tli-commit/scripts/commit.py
python3 -I $C --scan <path> [<path>…]                       # what would go in, and what the scan finds
python3 -I $C --message-file <message> <path> [<path>…]     # commit exactly those paths, then push
```

A path is a file, or a folder: for a folder, what changed under it. The helper:

- builds the commit from the last commit plus **those paths as they are on disk**, in a private index, so nothing
  another session changed or staged can slip in, and whatever it staged stays staged;
- refuses a file that `.gitignore` keeps local (`CLAUDE.md`, `docs/security-audit.md`, `.env.local`, `var/`,
  `vendor/`, `public/build/`…): don't force one in. Vendor, generated and built code is never committed, under
  any circumstance: it is fetched or generated at install time;
- scans the added lines for keys, tokens, passwords, addresses and long random strings, hiding their values. With a
  finding it commits nothing and exits with code 3. Read each one: a real secret comes out of the file; something
  meant to be public (the project's own contact address, a documented example) goes in with `--accept-findings`,
  and your report says why;
- fast-forwards first when the remote is ahead, and moves `main` only if nobody committed in the meantime
  (otherwise: run it again);
- pushes, and says so. `--no-push` keeps the commit local.

The scan knows shapes, not meanings. Before committing, read your own diff for what it can't see: a customer's
name, an internal hostname, a path that tells too much, steps to reproduce a weakness that isn't fixed everywhere.

## 4. Lock files

`composer.lock` or `yarn.lock` showing a version bump in the working tree (dev0 is the only box that resolves:
`scripts/cache-clear.sh` and the dev build do it) is committed and pushed **at once, in a commit of its own**,
whoever caused it, without raising it with the user. With it goes the manifest (`composer.json`, `package.json`)
only when the same update rewrote its version ranges. Check first that the diff really is a bump: versions,
references, hashes. A lock change that ships a `turbolabit/*` package the code now depends on goes with that code.

## 5. When it doesn't go through

- **"have diverged"**: there are commits here and others on the remote. That needs a rebase on a clean tree: stop
  and tell the user. Never stash someone else's work to get one.
- **The push is refused**: don't force it. Fetch, look at what is there, tell the user.
- **A NOTE about local commits not pushed yet**: they are another session's, and your push sends them too. Say so
  in your report.
- **After the push, a security review may come back with findings.** Read them: they are often right. Fix what is
  real in a new commit; never rewrite a pushed commit.

## Keep in mind

- `CLAUDE.md` and everything else `.gitignore` lists are local on purpose: update them, never commit them.
- The full test run (`scripts/test-runner.sh`) restarts nginx and clears the cache, and `yarn build` rewrites
  `public/build`: both hit the other sessions too. Run the tests your change needs.
- A fix reaches production only with the next deploy, which is the user's to run.
