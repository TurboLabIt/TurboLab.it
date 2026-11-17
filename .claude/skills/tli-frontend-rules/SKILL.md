---
name: tli-frontend-rules
description: >-
  The house rules for TurboLab.it's frontend: plain CSS, where a style goes, type weights with the system font,
  how links are told apart, what a CSS comment is for, how scripts load and in which order, images in e-mails. Use
  this skill whenever CSS, a template's markup, typography, colours, a script tag or an e-mail template is
  touched: "restyle this", "make the title bolder", "add a script", "fix the contrast", "change the newsletter
  layout", or any visual change. These are the maintainer's choices, each made after seeing the alternative:
  follow them, and when one seems wrong, say so instead of working around it.
---

# Frontend house rules

The site is built on a purchased theme (Newspark) over Bootstrap 5, with jQuery and plain JavaScript modules,
bundled by Webpack Encore. `docs/assets-frontend.md` maps where everything is. The rules below are what the map
doesn't say: choices made after trying the other way.

## Styles

- **Plain CSS only.** No SCSS, no preprocessor, anywhere.
- **A style goes in our own file for that area** (`assets/styles/<area>.css`, imported by the entry that needs
  it), as an override. The theme's stylesheet in `assets/styles/newspark/` is third-party: leave it, unless the
  change is the removal of something it does. `assets/app.js` imports Bootstrap first, then the theme, then ours:
  the cascade depends on that order.
- **Article content is styled once, for readers and for the editor**: `#tli-article-body` and
  `.ck.ck-content.ck-editor__editable` on the same rule, in `article.css`.
- **Override rules carry no commentary.** No audit numbers, contrast ratios or "why this beats that" next to
  the rule: the reasoning goes in `docs/` (`accessibility-audit.md`, `assets-frontend.md`). A comment stays when
  it guards something that looks removable and isn't, like a reserved height that prevents a layout shift.

## Type

- **The system font stack**, no web font: nothing to download, nothing that swaps and moves the layout.
- **Large text is never 700.** System bold faces are much heavier than the theme's original font: at 20-30 px a
  bold title looks clumsy. Headings are 500; card and list titles are 600 at most. Bold stays for what it is in
  running text: a `<strong>`.

## Links and colour

- **No underlines in dense strips of metadata** (author, date, section under a title): they read as noise. A
  link there is told apart by colour, with at least 3:1 contrast against the text around it, on top of the usual
  4.5:1 against the background.
- An accessibility fix prefers colour, weight or size to an underline, outside body text. What was decided, and
  why, is in `docs/accessibility-audit.md`: read it before "fixing" a contrast or a link style again.

## Layout stability

The site's Core Web Vitals were fixed once and are meant to stay fixed: **the result is the contract, not the
mechanism**. Anything that arrives late must have its space reserved: an ad slot, an embed, an image without
dimensions. After a change to the page's head, the fonts, an ad or anything above the fold, measure it
(`tli-lighthouse`) and look at it on a phone (`tli-browser-test`).

## Scripts

- **jQuery is a global**, loaded by a `<script>` in `templates/base.html.twig`. It is not a package: don't add it
  to `package.json`. Inside a bundle, `import $ from 'jquery'` resolves to that global through webpack's
  `externals`.
- **Our bundles are deferred; the theme's `main.js` is not.** So `main.js` runs **before** every bundle and can't
  use anything a bundle provides. A jQuery plugin that is bundled is also initialised in the bundle that imports
  it (`assets/js/slider.js` does it for every slider), never in `main.js`.
- **Page-specific code goes in that page's entry** (`assets/<entry>.js`, one of the entries in
  `webpack.config.js`); what every page needs goes in `app.js`.
- **Nothing built or downloaded is committed**: `public/build/`, the generated social icons, a copy of a library.
  They are produced at build time.

## E-mail

- **Images in an e-mail are PNG**: use the `…MetaUrl()` getters (`Article.spotlightOrDefaultMetaUrl(…)`), the
  same that feed social previews. Mail clients don't show AVIF. The web copy of the newsletter is a normal page
  and keeps the normal getters.
- E-mail templates are in `templates/email/` and `templates/newsletter/email.html.twig`, with their own styles in
  `assets/styles/email/`: mail clients ignore most modern CSS, so test a change in a real client.

## Words

Everything a visitor reads is in Italian, error messages included. Code, comments and commit messages are in
English.

## Check

```bash
python3 -I .claude/skills/tli-frontend-rules/scripts/check-frontend.py
```

It checks the rules a script can check: no preprocessor sources, jQuery not packaged, bundles deferred, PNG
images in e-mail templates. The rest needs your eyes: after any visual change, look at the page in a browser,
at desktop and at phone width.
