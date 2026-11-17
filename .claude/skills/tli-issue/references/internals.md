# How the bug icon works, and what to do when a step fails

Read this when a step of the procedure fails, or before changing the script. The full description, in Italian, is in
`docs/bug.md`.

## The click

The 🪲 shows only to logged-in users, only in forums 6 (`Forum::ID_TLI`) and 26 (`Forum::ID_COMMENTS`), and never on
the first post of a comments topic, which mirrors the article. Clicking it, then *OK* in the modal, posts `postId`
to `/forum-integration/ajax/new-issue` (`ForumController::newIssue`; `ajaxOnly()` requires `X-Requested-With` in
prod). The controller is thin, and `src/Service/Issue.php` does the rest:

1. `readGuideRequired()`: a `Visit` of article 49 by that account in the last 14 days (`READ_GUIDE_AGAIN_AFTER_DAYS`).
   Only the page's JavaScript records a visit (`assets/js/visit-on-load.js` posts `cmsType=article`, `cmsId=49` to
   `/ajax/count-visit`), and `Visit::isCountable()` ignores 127.0.0.1 and crawler User-Agents (headless browsers'
   defaults included). From a PC to production the visit counts; from a dev box to itself it never does.
2. `rateLimiting()`, prod only: 3 issues per 10 minutes per account **or** IP (`BugRepository::TIME_LIMIT_*`).
3. `createFromForumPostId()`: the issue title is the post's subject (minus a leading "Re: "). The body links the post,
   names its author and the reporter, and ends with a NOTE asking to reply on the forum. `GitHub::createIssue` calls
   the API with the `TLI-Bug` token only in prod: elsewhere it returns a fake issue `00-test`, and the title gets the
   environment tag. A `Bug` row records the issue.
4. `updatePost()`: posts to `/issue-add-to-post/`, which nginx rewrites to `public/special-pages/issues.php`. That page
   answers only to 127.0.0.1, and appends `🪲 Issue #N su GitHub` to the post with `submit_post('edit')`.

Then `.github/workflows/lock-forum-issues.yml` locks the issue a few seconds after it's opened.

## The login

`forum.py login` posts `username` and `password` to `/ajax/login/` (nginx → `public/special-pages/login.php`,
`X-Requested-With` required), exactly like the site's login modal. The answer: 200 OK, 401 unknown or inactive
account, 403 wrong password, 429 too many attempts, 500 server error. Without "remember me", phpBB creates no
autologin key, and `login.php` sets an encrypted fallback cookie that `phpBBCookiesAuthenticator` accepts instead
(the `sid` + `u` + fallback path). phpBB checks the User-Agent of every request against the session's
(`browser_check`), so the script always sends the same one.

The password and the session cookies only go to turbolab.it and its subdomains (`site_host()`): `login` refuses any
other `--site`, and every other command refuses a state file that names another host. A dev copy answers as
`<name>.turbolab.it` (the project's dev nginx config), so nothing legitimate needs more.

phpBB counts failed logins: after `max_login_attempts` (5) the account can log in only through the forum's own form,
with its question (`login.php` answers 429), and failures are counted per IP too. That's why the script never
retries.

`logout` posts the userbar's own logout form (`app_logout`, with its `_csrf_token`). Symfony then closes the phpBB
session server-side through `/ajax/logout/` (loopback only, `session_kill()`) and clears the cookies. A session left
open expires after 24 hours of inactivity (`session_length`).

## Posting

`forum.py` sends what the form sends: every hidden and text field, the checked boxes and radios, the selects, and
`post=Invia`. It waits 2 seconds first, because `check_form_key()` refuses a form sent back within the second it was
created. A new topic answers 302 to `viewtopic.php?t=N`, and the post ID is the first `id="pNNN"` anchor on that
page; a reply answers with `?p=N`. A post that needs a moderator's approval gets no redirect: phpBB answers 200 with
"Il messaggio è stato inviato correttamente, ma dovrà essere approvato da un moderatore…" and a meta refresh to the
post, and `forum.py` exits with code 4. Accounts without the admin flood exemption must wait the forum's flood
interval (15 seconds) between two posts.

## Errors

| What you see | Meaning | What to do |
|---|---|---|
| `login`: exit code 3 | no credentials file | SKILL.md, "The account" |
| `login`: HTTP 403, "Password errata" | wrong password in the credentials file; on a dev copy, also a password changed on production after the copy's database was imported | ask the user which: they fix the file (`setup --force`), or the copy gets a new import; don't retry |
| `login`: HTTP 401 | unknown username, or account not active | check `DEV_TLI_USERNAME` with the user |
| `login`: HTTP 429, "Hai eseguito troppi tentativi" | 5 failed logins | only the user can log in, from the forum in a browser |
| `login`: "--site takes prod, dev0 or https://<name>.turbolab.it" | the host isn't turbolab.it or a subdomain | use the dev copy's `<name>.turbolab.it` address; never work around it |
| `login`: NOTE "an earlier login never logged out" | an older login's state file is still in the temp directory | `logout <that state file>`, unless another session is using it |
| "Sembra che tu non abbia letto 📚 la guida di recente…" (403) | no visit to /49 in the last 14 days | `visit-guide`, then `bug` again |
| `visit-guide`: "visit not counted" | request from 127.0.0.1, or a crawler User-Agent | expected on a dev box (SKILL.md, step 4) |
| "Stai aprendo troppe issue…" (429) | rate limit, by account or IP | wait until the time it gives |
| "L'issue è stata creata su GitHub (grazie!), ma si è verificato un errore durante l'aggiornamento del post…" | the issue exists, only the post wasn't edited | don't click again (a second issue): find it with `gh issue list -R TurboLabIt/TurboLab.it --author TLI-Bug --limit 3`, tell the user; the link can be added by editing the post |
| "Forum not allowed" (403) | the post isn't in forum 6 or 26 | post in forum 6 |
| `whoami`: anonymous | the session expired or was closed | `login` again |
| `topic`/`reply`: no posting form, with phpBB's "not authorised" message | the account can't post there | the user posts it |
| `topic`/`reply`: phpBB's flood message | two posts too close together | wait 15 seconds |
| `topic`/`reply`: exit code 4, HELD | the post waits for a moderator | stop; `bug` only after the approval |
| the issue isn't locked after a minute | GitHub Actions off at org or repo level | `docs/bug.md`, "Blocco della conversazione su GitHub" |
