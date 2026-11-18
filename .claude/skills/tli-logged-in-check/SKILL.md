---
name: tli-logged-in-check
description: >-
  See a TurboLab.it page the way a given user gets it, on a dev copy: an administrator, an editor, an article's
  author, a registered user, a new user, or nobody. Use this skill whenever a change depends on who is looking:
  "check it as an editor", "what does a normal user see", "does the author get the edit button", "is this hidden
  from anonymous visitors", after touching permissions, the article editor, anything behind a login, or any code
  that prints a title or a username. It carries a dev-only helper that opens a session as any user without their
  password, the fixtures made for these checks, and the traps of telling who is logged in.
---

# Checking a page as a given user

The test suite can't do this over HTTP: `loginAsSystem()` in `tests/BaseT.php` logs in at the service layer only,
and a request through the test client ends up anonymous. So permission logic is tested by driving the service,
and what a logged-in person actually gets on the page is checked by hand, on a dev copy, with this skill.

**Dev copies only.** Nothing here is for production: there, the only session is the user's own login, through
the `tli-issue` skill's `forum.py`, and only for what the user asked.

## 1. Pick who to be

What an account may do comes from its forum groups (`App\Entity\PhpBB\User::getRoles()`):

| Kind | Who | What changes for them |
|---|---|---|
| administrator | a founder, or the `ADMINISTRATORS` group | everything an editor can, plus admin-only pages |
| editor | `TLI-Staff` or `GLOBAL_MODERATORS` | edits every article, publishes |
| author | whoever is in the article's authors | edits that article, can't publish it |
| registered user | `REGISTERED` | writes articles and posts |
| new user | `NEWLY_REGISTERED` | the same, but first posts wait for approval |
| anonymous | no session | cached pages, no forms |

```bash
X=.claude/skills/tli-logged-in-check/scripts/dev-session.php
php $X who                 # a few real accounts of each kind in this copy, with their IDs
```

For an author, take the ID from the article:
`php bin/console dbal:run-sql "SELECT user_id FROM article_author WHERE article_id = 1939"`.

## 2. Open a session as that user

```bash
php $X start 2             # an ID or a username: prints the state file
php $X end <state>         # always, when done: removes the session and the file
```

`start` writes the session straight into this copy's forum database, no password involved, and a state file in
the same format `forum.py login` writes. It refuses to run unless `APP_ENV` is `dev` and the copy's own address is
a subdomain of turbolab.it, and it works on the database of this checkout only. That check and the kernel it boots
are in `scripts/dev-copy.php`, which every dev-copy helper requires (`stored-body.php` of the
`tli-unlinked-downloads` skill too): a new one starts from there, not from a copy of it.

If the check is about the login itself (the form, a wrong password, the cookies), use the real thing instead:
`forum.py login --site dev0`, in the `tli-issue` skill.

## 3. Look

```bash
S=.claude/skills/tli-issue/scripts/forum.py
python3 -I $S whoami <state>                          # who the site thinks this session is
python3 -I $S get <state> /1939 --out <file>.html     # the page as the server sends it to that user
node .claude/skills/tli-browser-test/scripts/browser.mjs shot https://dev0.turbolab.it/1939 --out <file>.png --state <state>
```

`get` is for what the server decides: is the form there, is the button there, is the field escaped. The browser
(`tli-browser-test`) is for what happens after: the editor loading, a click, a modal. For the anonymous view, the
same commands without a state: `curl`, or the browser without `--state`.

What tells an editable article from a read-only one in the HTML: the title is a `<textarea>` with
`data-tli-editable-id="title"`, and `#tli-article-body` carries `data-ckeditor-license-key`.

## Fixtures made for this

- **Article 1939** (`/1939`): its title is packed with edge cases: quotes, `&`, `< >`, accents, an emoji,
  backslash, pipe. **Fetch it whenever you touch titles, meta tags, escaping or a `|raw`**, and check that every
  special character is inert where it lands: the `<h1>`, the `<title>`, and above all the `content="…"` of
  `og:title` and `twitter:title`, where a raw `"` closes the attribute. Don't upload test files to it.
- **Tag 12600**: a tag with the same kind of title, for tag pages and breadcrumbs. Tag 1290 has a plain `&`.
- **User 4015**: its username is a script-injection attempt. Its author page (`/utenti/…`) and anything that
  prints an author's name must show it as text.
- **A draft to edit**: IDs change when the database is re-imported, so ask for one:
  `php bin/console dbal:run-sql "SELECT a.id, a.title FROM article a JOIN article_author aa ON aa.article_id = a.id WHERE a.publishing_status = 0 AND aa.user_id = 5103 LIMIT 5"`
  (drafts of the System account, user 5103). A draft answers HTTP 503 by design and still shows the editor to its
  authors. An author can change only the files they uploaded: create your own test files during the run.

## Traps

- **The header's login state is loaded by JavaScript** (`/ajax/userbar`), so that cached pages stay the same for
  everyone. In the HTML the server sends, a username proves nothing: authors' names are all over the homepage.
  Ask `whoami`, look for a marker the server prints only for that user (a form's `_csrf_token`, the editable
  title), or in the browser wait for the greeting.
- **A page whose route skips authentication** still looks logged in in a browser, because the userbar asks on its
  own. What the server did for that request is the question, not what the header shows.
- **Pages aren't cached in dev** (`isCachable()` is false there): what an anonymous visitor gets from the cache on
  production can't be seen on a dev copy. Cache logic is tested at unit level.
- **Before anything that notifies people** (a post, a reply, a private message): the dev copy has real users'
  addresses. `phpbb_config.email_enable` must be 0, as the `tli-issue` skill explains.
- **Leftovers are fine, sessions aren't**: test articles and uploads on a dev copy don't matter. An open session
  as an administrator does: `end` it.
