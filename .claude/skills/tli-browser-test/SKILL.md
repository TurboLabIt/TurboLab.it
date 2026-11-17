---
name: tli-browser-test
description: >-
  Look at a TurboLab.it page in a real browser, without a screen: headless Chrome driven from the command line.
  Use this skill whenever a change has to be seen or tried rather than read: "take a screenshot", "does it work on
  mobile", "test the hover", "click it and see", "any JavaScript error?", "how does it look logged in", or after
  touching CSS, JavaScript or a template, when the tests can't tell whether the page still looks and behaves
  right. It carries a ready driver (screenshots, hover, real clicks, typing, phone emulation, a logged-in tab)
  and the traps that make a hand-written one fail on this site.
---

# Testing a page in a browser

PHPUnit checks what the server sends. What the visitor then sees and does (a hover that opens, a click that
works, a layout that holds at 390 pixels) only a browser can tell. This skill drives **Chrome for Testing**,
headless, over the DevTools Protocol, with two files that need nothing installed beyond Node 22:

- `scripts/browser.mjs`: the common checks, from the command line;
- `scripts/chrome.mjs`: the library behind it, for a scenario of your own.

It is for the project's own sites: `https://turbolab.it` and its subdomains, the dev copies above all. Any other
address is refused unless you pass `--any-site`, because on a dev box the browser may run without its sandbox.

## The common checks

```bash
B=.claude/skills/tli-browser-test/scripts/browser.mjs
node $B check https://dev0.turbolab.it/1939                    # status, title, JavaScript errors, failed requests
node $B shot  https://dev0.turbolab.it/1939 --out <file>.png   # the top of the page, 1280x900
node $B eval  https://dev0.turbolab.it/1939 'document.title'   # any expression, printed as JSON
```

Options, for all three:

| Option | What it does |
|---|---|
| `--mobile` | a phone: 390x844, touch, no hover |
| `--hover <css>`, `--click <css>` | first move the pointer onto that element, or click it for real |
| `--wait-for '<js>'`, `--wait <ms>` | wait until the expression is true, then that long |
| `--full`, `--selector <css>` | `shot` only: the whole page, or one element |
| `--state <file>` | a logged-in tab (below) |
| `--keep-consent` | leave the consent dialog in (it is blocked otherwise) |

`check` exits with code 1 on a status of 400 or more, or on any JavaScript error. Save screenshots in your
scratchpad and look at them with the Read tool: a screenshot nobody looked at proves nothing.

## Logged in

`--state` takes the session file that `forum.py login` writes (the `tli-issue` skill):

```bash
python3 -I .claude/skills/tli-issue/scripts/forum.py login --site dev0     # prints the state file
node $B shot https://dev0.turbolab.it/1939 --out <file>.png --state <state>
python3 -I .claude/skills/tli-issue/scripts/forum.py logout <state>
```

The tab gets that session's cookies and its User-Agent, which phpBB checks on every request. The login state
shows in the page a moment after the load, when the userbar arrives: wait for it
(`--wait-for "/Ciao\\s+\\S+!/.test(document.body.innerText)"`) rather than looking for the username in the HTML.

## A scenario of your own

For more than one step, write a short script in your scratchpad and import the library by its full path:

```js
import { launch } from '/var/www/turbolab.it/.claude/skills/tli-browser-test/scripts/chrome.mjs';

const browser = await launch();                      // launch({ touch: true }) for a phone
const page = await browser.newPage();                // newPage({ mobile: true }), newPage({ state: '<file>' })
try {
  await page.goto('https://dev0.turbolab.it/1939');
  await page.hover('.tli-social-share a');
  const width = await page.evaluate("document.querySelector('.tli-social-share a').getBoundingClientRect().width");
  await page.screenshot('<scratchpad>/pill.png', { selector: '.tli-social-share' });
  console.log({ width, errors: page.errors });
} finally {
  await browser.close();                             // always: it removes the profile and stops the browser
}
```

A page has `goto`, `evaluate`, `waitFor`, `hover`, `click`, `type`, `pressEnter`, `setFiles`, `screenshot`,
`rect`, `send` (any DevTools Protocol method) and `once` (the next event of a kind); `errors`, `failedRequests`
and `response` say how the load went. The file's comments describe each one.

## What the driver already handles

Each of these broke a hand-written driver at least once. They are in the library, so don't redo them:

- **The sandbox**: dev0 refuses unprivileged user namespaces, and Chrome then dies with "No usable sandbox!". The
  flag is added only where the kernel has that restriction.
- **A profile and a browser of its own per run**, removed on close. Other sessions run their own Chrome on the
  same box: never `pkill` Chrome, and never reuse a profile folder.
- **The protocol over a pipe**, not over a TCP port that any local user could attach to.
- **dev0's self-signed certificate**: that one certificate is trusted, so a real TLS error still shows.
- **The consent dialog**: removed from the page, it puts itself back, and its backdrop takes every click. Its
  hosts are blocked at network level instead.
- **Hover**: headless Chrome reports a pointer that can't hover, so `:hover` rules and hover media queries never
  apply. The mouse is declared at launch (and a coarse, hover-less pointer with `touch`).
- **Smooth scrolling**: the site scrolls with an animation, so measuring and then clicking misses. `rect`,
  `hover` and `click` scroll instantly, measure in the same step, and re-aim until the element is under the
  pointer.
- **Real gestures**: `click` sends mouse events, which the page counts as a user's. `element.click()` in
  `evaluate` doesn't, and autoplay or pop-up rules then behave differently.
- **The User-Agent**: a normal Chrome's plus `tli-browser-test/1.0`. The default headless one is treated as a
  crawler by the site.

## What is still yours to mind

- **Deferred scripts**: the site's bundles run after the load event. `goto` waits half a second more; for
  anything that arrives later (the userbar, an AJAX modal) use `waitFor`.
- **An embedded player can take the focus** while the page loads and scroll the page to itself. `shot` goes back
  to the top; in your own script, scroll where you need before measuring.
- **An emoji shown as an empty box** in a screenshot is the box's missing emoji font, not the site.
- **dev0 is slower than production and its numbers move**: this skill is for behaviour, not for timings.
- **One browser at a time**, closed when done: dev0 has little memory, and other sessions use it too.
- **A dev copy only**, unless the user asks for production: there a visit is a real one.

Forms, file uploads, modals, drag and drop, embedded videos and the article editor each have a trap of their
own: read `references/recipes.md` before scripting one of them.
