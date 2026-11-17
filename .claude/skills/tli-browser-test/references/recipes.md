# Recipes and traps for longer browser scenarios

Notes from past sessions on this site. Each one cost a failed run. `page` is a page of `scripts/chrome.mjs`;
`page.send(method, params)` calls any DevTools Protocol method.

## Forms and typing

- **Enter**: a key event without its text fires `keydown` only, and the form doesn't submit, so an "Enter submits"
  test passes for nothing or times out. `page.pressEnter()` sends the text too.
- **Replacing a field's value**: focus it and select its content in `evaluate`
  (`element.focus(); element.select()`), then `page.type('…')`.
- **Re-runs that create things**: titles are unique site-wide (a second run gets a 409), and identical files are
  recognised by their md5. Use a random suffix and random content at every run.

## File inputs

`page.setFiles('<css of the input>', ['/absolute/path'])` fires a real `change` event, so handlers bound through
jQuery run, and "pre-fill on pick" logic can be tested.

## Modals

Bootstrap modals **ignore `hide()` while they fade in**. An AJAX modal whose content arrives in under 300 ms looks
ready before its close button works: wait about 800 ms after the content appears before clicking `.btn-close`.

## Drag and drop

- A real file drop: `Input.dispatchDragEvent` with `dragEnter`, then `dragOver`, then `drop`, each with
  `data: { items: [], files: ['/absolute/path'], dragOperationsMask: 1 }` and the pointer's `x`, `y`. The page
  gets a genuine `dataTransfer.files`.
- `dragCancel` fires **no** `dragleave`, unlike a real Esc: to test "the highlight clears", drag over a point
  outside the target instead.
- Headless Chrome never navigates to a dropped file. Assert on `defaultPrevented` and `dropEffect`, and read
  `dataTransfer.types` synchronously: inside a `setTimeout` it is already empty.
- An upload's progress state: slow the network with
  `Network.emulateNetworkConditions { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: 25000 }`.

## The article editor

The live CKEditor instance is `document.querySelector('.ck-editor__editable').ckeditorInstance`: its model can be
read and changed through `evaluate` (`editor.getData()`, `editor.setData(…)` to put things back,
`editor.model.change(…)`).

## Embedded videos and other frames

- A YouTube embed reports its state through `postMessage` only **after** it receives
  `{"event":"listening","id":"x","channel":"widget"}`: without that handshake, silence proves nothing.
- A cross-origin frame runs in a process of its own: the address it really loaded is not in `Page.getFrameTree`.
  List `Target.getTargets` and keep those of type `iframe`. The `src` attribute can differ from what is loaded,
  for instance after a history restore.
- To test going back and forward as a full reload, launch with
  `launch({ args: ['--disable-features=BackForwardCache'] })`: with the back/forward cache on, a restored page is
  a frozen one, and bugs in the frames' own history stay hidden.
- Autoplay rules depend on a user gesture: test them with `page.click`, and don't launch with
  `--autoplay-policy=no-user-gesture-required` when the rule itself is what you are testing.

## Screenshots

`page.screenshot(file, { selector })` and `{ fullPage: true }` already convert to document coordinates. If you
call `Page.captureScreenshot` yourself with a `clip`, add `scrollX` and `scrollY` to the element's rectangle and
set `captureBeyondViewport: true`, or the crop lands elsewhere after any scroll.
