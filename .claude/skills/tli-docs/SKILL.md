---
name: tli-docs
description: >-
  How the documentation in docs/*.md is written and kept up to date on TurboLab.it: in Italian, built around
  structure maps and project-specific facts, without the lists and the generic explanations that rot. Use this
  skill whenever a file under docs/ is created or edited, whenever a change makes a doc stale ("update the docs",
  "document this", "aggiorna la documentazione"), and before committing a feature or a fix that changes how
  something works. It carries the house style, calibrated on the maintainer's own edits, and a checker for
  titles and for links to files that no longer exist.
---

# Writing the project's docs

`docs/` holds the long explanations: how a part of the site works, why it is built that way, what bites. They are
written **in Italian**, for a developer who knows Symfony and doesn't know this project, and they are public on
GitHub. A doc is updated **in the same commit** as the change that makes it stale.

`docs/assets-frontend.md` is the reference: when in doubt about form, do what it does.

## What a doc is made of

Keep, because it is what nobody can get from the code in a minute:

- **Structure maps**: the folders or the pieces involved, each with a short note on what it is for. Above all the
  non-obvious ones.
- **The project's own facts**: the wrapper script that is really used (`scripts/watch.sh`, not the generic
  command), what runs by itself on deploy, how nginx serves the result.
- **Gotchas, in full**: the trap, the literal command, the literal error message. This is the most valuable part
  of a doc: someone will search for that error.
- **Counts as orientation** ("i 10 entry point") and a few illustrative names followed by "...".
- **Why**, wherever a choice isn't the obvious one.

Cut, because it goes stale or says nothing:

- **Exhaustive lists of names**: every entry point, every file, every import. The count and three examples.
- **How the framework works**: "ogni entry = 1 JS + eventuale CSS" is just Encore. Say what is ours.
- **Configuration copied out**: link the config file, don't transcribe its options.
- **Generic commands** that any Symfony or Yarn project has.
- Sentences that only confirm, and emoji that only decorate (✅, ✏️ next to every item).

## Form

- **First line**: the title, linking to the document itself on GitHub:
  `# [Gestione degli asset frontend](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/assets-frontend.md)`
- **Then one sentence saying what the document covers**: "Questo documento tratta …".
- **Headings that carry information**, the flow included:
  `## Asset "regolari" (Webpack Encore) - assets/ ➡ public/build/`. Two blank lines before a `##`.
- **Numbered lists for pipelines and steps, bullets for enumerations.** A folder map is a bullet per entry:
  `` `dictionaries/` — `stopwords-it.txt` (elenco di stopword italiane) ``.
- **A file or a class is a link to it on GitHub**, with its path or `Class::method` as the text:
  `[scripts/build.sh](https://github.com/TurboLabIt/TurboLab.it/blob/main/scripts/build.sh)`. A folder uses
  `/tree/main/`.
- **Cross-references in one section at the end**, `## 🔗 Vedi anche`: a bullet per document,
  `- [Titolo](link) — perché c'entra`. Not a "Vedi:" line in every paragraph. Link the other document, don't
  repeat it.
- **Functional emoji are fine**: ➡ for "becomes" or "leads to", ⚠️ for a warning, 📚 for an external reference,
  🛑 for "don't".
- **Be exact about who did what**: "modificato da Newspark", not "da noi", when that is the case.

## When editing an existing doc

- Change what the code change made false, and what is clearly the same kind of thing nearby. **Don't rewrite the
  rest**, and don't strip a doc down to a skeleton: a first "lean" pass once removed the structure the maintainer
  wanted.
- When the maintainer corrects something, apply that correction and its close relatives, then stop.
- A record of the past (an audit entry, a migration that is over) stays as written: add what happened next,
  don't rewrite history.

## What doesn't go in docs/

- **Secrets and private details**: the docs are public. No credentials, no personal data, nothing about internal
  mailboxes or servers beyond what is already there.
- **Security findings**: `docs/security-audit.md` is the one doc that is local and never committed.
- **The editorial guidelines for news**: they are an article of the site (https://turbolab.it/26), not a doc.
- **Procedures for the agents**: those are the skills in `.claude/skills/`. A doc may say a skill exists and
  link it.

## Check before committing

```bash
python3 -I .claude/skills/tli-docs/scripts/check-docs.py              # every doc
python3 -I .claude/skills/tli-docs/scripts/check-docs.py docs/bug.md  # one
```

It reports a title that doesn't link to its own document, and every link to a file or folder of the repository
that isn't there any more: the usual fate of a doc after a rename. It reads the working tree and nothing else.
Run it after renaming or deleting a file too: some doc probably links it.
