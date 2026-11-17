---
name: tli-ckeditor-plugin
description: >-
  Add or change a button, a format or a behaviour of the article editor (CKEditor 5) on TurboLab.it: a custom
  plugin in assets/js/ckeditor-plugins/, its registration, its icon, the server-side allowlist that decides what
  survives a save, its styles, its tests. Use this skill whenever the editor's toolbar or the markup authors can
  produce is touched: "add a button to the editor", "let authors insert X", "new format in the editor", "why is my
  tag stripped on save", or a change to an existing plugin. It carries the checklist, proven end to end with a
  throwaway plugin, and the traps: a tag the editor shows and the server silently drops, icons that lose their
  colour, a toolbar that must never collapse.
---

# Adding a plugin to the article editor

The editor is CKEditor 5, started on the article page itself for whoever may edit it. It is **restrictive on
purpose**: H2, paragraphs, lists, a few inline formats and code blocks, so that authors can't build layouts out
of markup. A new format is therefore two decisions before it is code: does it serve the authors, and may its
markup be stored. Then it is five places to touch, and they must agree.

## 1. The plugin

`assets/js/ckeditor-plugins/<name>.js`, one file per feature, default-exporting a `Plugin` that requires its
parts. Start from the existing one closest to what you need:

| Kind | Read | Parts |
|---|---|---|
| an inline format (`<ins>`, `<code>`) | `update.js`, `istruzioni.js` | Editing (schema + conversion + command), Command, UI |
| a block with options | `codice.js` | builds on CKEditor's own feature, adds the button and a dropdown |
| inserts something chosen in a modal | `youtube.js`, `link-article.js`, `link-file.js` | UI + a modal template + a server endpoint |
| an action, no markup | `save.js`, `advise.js`, `publishing-status.js` | UI only |
| a behaviour without a button | `data-uri-upload.js`, `table-paste-guard.js` | listens to the editor's events |

For markup, the Editing part does three things, and **all three are needed**:

- extends the schema (`editor.model.schema.extend('$text', { allowAttributes: '…' })` for an inline format);
- converts **in** (`conversion.for('upcast')`): without it, CKEditor doesn't know the tag and drops it when an
  article that already has it is opened, so the first save erases it;
- converts **out** (`conversion.for('downcast')`).

The button is registered with `editor.ui.componentFactory.add('<tliName>', …)`, bound to the command's `value`
and `isEnabled`, with an Italian label or tooltip. Name commands and buttons `tli…`.

## 2. Registration

In `assets/js/article-edit-ckeditor.js`: import the plugin, add it to `plugins` under "TLI plugins", and put
its button name in `toolbar.items`, in the group where an author would look for it.

**`shouldNotGroupWhenFull: true` stays.** The toolbar wraps onto more lines on a narrow screen; it must never
collapse into a "more" menu, which is hard to use on a phone or zoomed in. Every command stays visible.

## 3. The icon

A text button (`withText: true`, a short label like `UPD`) needs no icon. Otherwise the SVG goes in
`templates/article/editor/toolbar-icons.html.twig`, inside `#tli-toolbar-icons`, with a class of its own, and the
plugin takes it with `$('#tli-toolbar-icons .<class>')[0].outerHTML`.

**A colour goes on each `<path>`, as `fill="#…"`.** CKEditor's icon handling drops a `fill` on the `<svg>`
element and never matches a CSS rule on a class of yours: the icon comes out black. An icon in the text colour
uses `fill="currentColor"`. Font Awesome's solid glyphs (viewBox 512) look heavier than Bootstrap Icons' (viewBox
16) at toolbar size: pick the weight of the neighbours.

## 4. What the server keeps

The body is sanitized on every save by `App\Service\HtmlProcessorForStorage`, against a list:
`ALLOWED_TAGS` (and `ALLOWED_TAGS_EXTENDED` for the few articles flagged for it). **A tag or an attribute that
isn't there is removed, silently**: the editor shows it, the author saves, and it is gone on reload.

- Adding to that list is the real decision: it is what every author can then store. Add the narrowest thing
  (`tag`, or `tag[attribute]`), never a generic `class` or `style`.
- When the format has a set of values (the code languages), the list lives on the server and is handed to the
  editor (`data-code-languages` on `#tli-article-body`), so the editor can never offer what the server strips.
- Markup that needs rewriting on the way out (a link to an article, a file, a video) is stored as a placeholder
  and expanded by `HtmlProcessorForDisplay`: follow `link-article.js` and its storage tests.
- Anything touching article markup belongs in those two classes, not in a template.

## 5. Styles

The body is styled once for readers and for the editing view, in `assets/styles/article.css`, with both
selectors on the same rule:

```css
#tli-article-body kbd,
.ck.ck-content.ck-editor__editable kbd { … }
```

Hang the rules for what is inside the body on `#tli-article-body`, not on the `.tli-article-main-content`
wrapper: other pages share that wrapper, and their own markup would pick the rule up.

## 6. Build, try, test

```bash
yarn dev                      # a few seconds; it rewrites public/build, which other sessions use too
```

Then try it for real, as an author would:

1. a session as an author or an editor on a dev copy (`tli-logged-in-check`), on a draft, not on article 1939;
2. the page in the browser (`tli-browser-test`): the button is in the toolbar, a click applies the format to a
   selection, `document.querySelector('.ck-editor__editable').ckeditorInstance.getData()` has the markup, and
   there is no JavaScript error;
3. save (`#tli-ckeditor-save`), reload: the markup is still there. This is the step that catches a missing
   allowlist entry or a missing upcast.

And pin it with a test in `tests/Editor/`, in the style of `CodeBlockStorageTest.php`: what survives storage,
what is stripped, that the stored form is stable across saves, and that a hostile value of any new attribute
doesn't get through.

## 7. Write it down

`docs/assets-frontend.md` maps the assets, the plugins' folder included. A format with behaviour of its own (what
it stores, what it rewrites, who may use it) gets its paragraph in the doc of the feature it belongs to, in the
same commit: the `tli-docs` skill.
