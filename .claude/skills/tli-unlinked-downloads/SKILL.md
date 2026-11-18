---
name: tli-unlinked-downloads
description: >-
  Fix the TurboLab.it articles listed under "File da controllare:" on /scarica/da-controllare: each one has a
  Download (a file or an external URL) attached that its text never links, so readers only find it in the list
  under the article. Use this skill whenever that list comes up: "fix the files to check", "sistema i file da
  controllare", "link the attached files", "Download non linkati", articles saying "in allegato all'articolo" with
  no link, issue #101, or the list growing again. It works on that section of the page only (not the orphans, not
  the files missing on disk or in the database), logged in with an administrator account over plain HTTPS, saving
  the way the editor saves. It carries Zane's rules for how each article is fixed, a script that lists, shows,
  checks, saves and verifies, and the guard against the text that the page hides.
---

# Linking the Downloads of "File da controllare"

`FilesToArticles` (in `scripts/uploaded-assets-maintenance.sh`, every 3 hours on production, at minute 3) compares
each article's text with the Downloads attached to it (`article_file`) and writes the ones attached but never linked
to `var/files-orphans.json`; `/scarica/da-controllare` shows them, to logged-in users, under **File da controllare:**.
That's "collegato ma non linkato" in `docs/downloadable-files.md`: accepted, but the text should link them. An
article leaves the list at the first run after its text links every Download listed for it.

Most of them are TLI1's: it showed the attachments in a box, so authors wrote "in allegato all'articolo trovate…"
and linked nothing. The other sections of the page (orphans, files missing on disk or in the database) are not this
skill's.

## The account

The articles are other people's, so this needs an **administrator's account** (an editor's works too), kept in
`/etc/turbolab.it/account-turbolab.it-admin.conf`. It's written by `forum.py setup --account admin` (the `tli-issue`
skill's script), with the same format, flow and rules as the user's own account in that skill's "The account":
never print it, never put the password in a command, and if `login --account admin` exits with code 3, ask the user
(AskUserQuestion) how they want to give it. Never fall back to the user's own account file: it isn't the one chosen
for this, and it can't edit other people's articles anyway.

```bash
S=.claude/skills/tli-issue/scripts/forum.py
D=.claude/skills/tli-unlinked-downloads/scripts/downloads.py
python3 -I $S login --account admin                 # production; prints the state file
python3 -I $S logout <state>                        # always, even after a failure
```

## How an article is fixed

Zane's rules, from his own fixes (576, 1094, 2708), the forum topic of issue #101
(https://turbolab.it/forum/viewtopic.php?t=13724) and his answers of 2026-11-18:

1. **Link each Download where the text talks about it**, with a line of its own right after that paragraph:
   `<p><strong>» Download:</strong> <a href="…/scarica/N">Title</a></p>`, exactly what the editor's "Usa come riga
   » Download:" inserts. One per Download, at the mention where the reader needs it, usually the first one.
2. **An aside gets an inline link instead**: a passing mention, in brackets or at the end of a sentence about
   something else, like Zigul's SoftDetective installation reports ("(in allegato trovate il report…)"), gets the
   link on its own words ("in allegato…"). The same when a line would split a paragraph from what it introduces: a
   paragraph that ends with ":" before a list, an image or some code.
3. **The author's words stay as they are**, "in allegato all'articolo" included. Only where nothing in the text
   announces the Download, end the paragraph with a short lead-in in the article's own voice, then the line: "Un
   esempio già pronto è scaricabile da qui:" (Zane, on 576).
4. **A link that already points straight at the same thing** (the program's page on a store, the file on its
   site) is pointed at the Download instead (`repoint`): its address never changes, and its source can be replaced
   in one place for every article.
5. **A Download the text never mentions**: leave the article alone and tell the user. Never detach or delete a
   Download: on 997, whose Download was never cited and pointed at a dead address, Zane kept the association, to
   replace its source with a copy of the file.
6. **Titles stay as they are**: the link text of a line is the Download's title, even "Zen_reports".
7. **Nothing else changes.** The save itself still does what every save does: it recomputes the abstract, and the
   save-time text fixers correct the whole text ("internet" into "Internet", "un'attimo" into "un attimo").
   `apply` reports what they changed.

Apply the plan on production directly: Zane asked for no confirmation article by article (2026-11-18). What gets
saved is in the `--dry-run` output first.

## The procedure

1. `python3 -I $S login --account admin`.
2. `python3 -I $D list <state>`: the articles under "File da controllare:" and their Downloads, with the date the
   list was generated.
3. For each article, `python3 -I $D show <state> --article N`: the text as the editor gets it, block by block,
   with the blocks that may mention a Download marked and the ones around them (`--all` for every block, `--full`
   for whole blocks), and the hidden-text check. Decide by the rules above.
4. Write the plan: a JSON file in your scratchpad (below).
5. `python3 -I $D apply <state> --plan <plan> --dry-run`: every change, each inserted line with the paragraph it
   follows, and nothing saved. Read it all.
6. `python3 -I $D apply <state> --plan <plan>`: it saves each article the way the editor does, reads the page
   again and checks that every Download is linked. `--article N` applies one article of the plan. An article whose
   page no longer has one of the plan's Downloads attached, or already links it, is skipped: the list is up to 3
   hours old, and someone may have been there in the meantime.
7. `python3 -I $S logout <state>`.
8. Tell the user, article by article: what was linked and how, what the save's text fixers changed, what was left
   and why, and whatever else turned up (a sentence cut short, a dead Download). The articles leave the list at
   the next `FilesToArticles` run: `list` again after it.

## The plan

A JSON object: article ID, then its operations. Blocks are pointed at by quoting some of their words, as `show`
prints them but without the link targets, and a quote must match exactly one block. Only the articles under "File da
controllare:" and only their listed Downloads are accepted.

```json
{
  "4358": [{"op": "link", "file": 488, "in": "l'installazione è semplice e veloce", "text": "in allegato all'articolo"}],
  "4407": [{"op": "line", "file": 490, "after": "che trovate allegato all'articolo, utilizzerò Powershell in futuro"},
           {"op": "line", "file": 507, "after": "Ho inserito in allegato all'articolo un nuovo script"}],
  "4335": [{"op": "line", "file": 486, "after": "significa che l'immagine ISO è già disponibile",
            "lead_in": ". Altrimenti, puoi scaricarla da qui:"}],
  "3662": [{"op": "repoint", "file": 475, "href": "https://apps.microsoft.com/store/detail/lively-wallpaper/9NTM2QC6QWS7"}]
}
```

| op | what it does | fields |
|---|---|---|
| `line` | the `» Download:` line, as a paragraph | `file`; `after` or `before` (words of a block); `lead_in`, optional, text added at the end of the `after` paragraph, punctuation included |
| `link` | turns words of a block into the link | `file`; `in` (words of the block); `text`: the words to link, once in that block, in one run of text (no bold or italics inside), outside links |
| `repoint` | points a link of the text at the Download | `file`; `href`: the link's target as `show` prints it, which only one link of the text may have |
| `restore` | puts back text that the page hides (below) | `text`, as `show` prints it; `after` or `before` |

## Text the page hides

Since PHP 8.5 (libxml's HTML5 parser, 2026-06), the page drops any text that the stored body has outside an element
(TLI1 bodies have plenty: 555 published articles on 2026-11-18). Readers don't see it, the editor never gets it, and
the next save, by hand or by this script, deletes it for good. So `show` and `apply` compare the editor's copy with
the body stored in this checkout's database (`stored-body.php`, on a dev copy only). Words the page hides make
`apply` skip the article, unless the plan puts them back with a `restore` that `show` prints ready to use, at the
place the stored body has them: that's the author's own text, made visible again.

Against production, the comparison is with the dev copy, which holds only while the copy has the article as
production has it: `apply` checks the dates and the words, and skips the article as **stale** when they differ.
`scripts/db-download.sh` refreshes the copy. Without any dev copy, `--skip-hidden-check` saves anyway: only
knowingly, since that's the case where text can go.

## Rehearsing on dev0

dev0 has the same articles, and its list is whatever its own last `FilesToArticles` run found: rebuild it with
`php bin/console FilesToArticles`, which on a dev copy is harmless. Log in with `forum.py login --site dev0
--account admin`, or open a session as any editor with the `tli-logged-in-check` skill, then `apply --dry-run`. A real `apply` there is for testing the script: it changes the
copy that the production check reads, and those articles then come out stale until the copy is refreshed, or the
articles' rows are put back from a dump in `backup/db-dumps/`.

## Traps

- **A draft answers HTTP 503**, and the editor still gets it: `show` says it's not published, the fix is the same.
- **A list that lags behind**: the site's list is only as new as its last `FilesToArticles` run, and as the code
  that ran it. A fix of that command shows on a dev copy before production has it: on 2026-11-18 production still
  hid everything changed in the last 9 months (a rule meant for the images), which dev0 already listed. Then take
  the list from the dev copy and work on production with it: `show` and `apply` with `--list-from <dev0 state>`
  (a `dev-session.php` session, or `forum.py login --site dev0 --account admin`, after
  `php bin/console FilesToArticles` there). The Downloads keep their IDs, now at production's addresses, and each
  must still be attached to its article on production, which `show` says and `apply` checks.
- **An external Download can be dead** (997's pointed at a Microsoft file gone 404). Linking it sends readers to an
  error: tell the user, who can replace its source ("Sostituisci", in the editor) before it's linked.
- **The save endpoint takes no token**: the login and the right to edit the article are what it checks, and, on
  production and staging, the `X-Requested-With` header that jQuery sends (`ajaxOnly()`): the script sends it too.

## The scripts

- `scripts/downloads.py`: Python 3, standard library only. The session is `forum.py`'s (the `tli-issue` skill: the
  same state file, the same safeguards on hosts and TLS), imported from its folder.
- `scripts/stored-body.php`: the stored body and dates of some articles, read-only, from this checkout's database. It
  starts like every dev-copy helper, with `dev-copy.php` from the `tli-logged-in-check` skill, which refuses anything
  but a dev copy.
