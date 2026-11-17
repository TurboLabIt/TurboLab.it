#!/usr/bin/env node
/**
 * The common browser checks from the command line. For anything longer, write a script that imports chrome.mjs.
 *
 *   node browser.mjs check URL                      status, title, JavaScript errors, requests that failed
 *   node browser.mjs shot URL --out FILE.png        a screenshot of the viewport
 *   node browser.mjs eval URL 'EXPRESSION'          the value of a JavaScript expression in the page, as JSON
 *   node browser.mjs path                           where Chrome for Testing is
 *
 *   --state FILE       browse logged in: a session file written by `forum.py login` (the tli-issue skill)
 *   --mobile           a phone: 390x844, touch, no hover
 *   --hover CSS        move the pointer onto that element first      --click CSS    click it first
 *   --wait-for JS      wait until that expression is true             --wait MS      then wait that long
 *   --full             (shot) the whole page                          --selector CSS (shot) only that element
 *   --keep-consent     don't block the consent dialog
 *   --any-site         allow an address outside turbolab.it (the browser may run without its sandbox)
 */
import { parseArgs } from 'node:util';
import { chromePath, launch } from './chrome.mjs';

const { values: options, positionals: [command, address, expression] } = parseArgs({
  allowPositionals: true,
  options: {
    out: { type: 'string' }, state: { type: 'string' }, hover: { type: 'string' }, click: { type: 'string' },
    selector: { type: 'string' }, wait: { type: 'string' }, 'wait-for': { type: 'string' },
    mobile: { type: 'boolean' }, full: { type: 'boolean' }, 'keep-consent': { type: 'boolean' },
    'any-site': { type: 'boolean' },
  },
});

function usage(message) {
  console.error(`${message}\nusage: node browser.mjs check|shot|eval URL [EXPRESSION] [options], or: node browser.mjs path`);
  process.exit(2);
}

if (command === 'path') {
  console.log(chromePath());
  process.exit(0);
}
if (!['check', 'shot', 'eval'].includes(command) || !address) usage('which check, and on which address?');
if (command === 'shot' && !options.out) usage('shot needs --out FILE.png');
if (command === 'eval' && !expression) usage('eval needs the expression to evaluate');

// what a page says is its own text: printed without anything that could drive the terminal. That is control
// characters, Unicode line separators, and the marks that reorder text on screen (named by code point here:
// written as they are, nobody could see them in this file)
const REORDERING = String.fromCodePoint(0x202a, 0x202b, 0x202c, 0x202d, 0x202e, 0x2066, 0x2067, 0x2068, 0x2069);
const UNPRINTABLE = new RegExp(`[\\p{Cc}\\p{Zl}\\p{Zp}${REORDERING}]`, 'gu');
const shown = text => String(text).replace(UNPRINTABLE, ' ');

const browser = await launch({ touch: options.mobile });
let exitCode = 0;
try {
  const page = await browser.newPage({ mobile: options.mobile, state: options.state, keepConsentDialog: options['keep-consent'] });
  await page.goto(address, { anySite: options['any-site'] });
  if (options['wait-for']) await page.waitFor(options['wait-for']);
  if (options.hover) await page.hover(options.hover);
  if (options.click) await page.click(options.click);
  if (options.wait) await new Promise(done => setTimeout(done, Number(options.wait)));

  if (command === 'shot') {
    // an embedded player can take the focus while the page loads, and the page scrolls to it: back to the top,
    // unless the pointer was sent somewhere on purpose
    if (!options.hover && !options.click) await page.evaluate("window.scrollTo({ top: 0, left: 0, behavior: 'instant' })");
    console.log(await page.screenshot(options.out, { fullPage: options.full, selector: options.selector }));
  } else if (command === 'eval') {
    console.log(JSON.stringify(await page.evaluate(expression), null, 2));
  } else {
    const status = page.response?.status ?? 0;
    console.log(`HTTP ${status} ${shown(page.response?.url ?? address)}`);
    console.log(`title: ${shown(await page.evaluate('document.title'))}`);
    console.log(`JavaScript errors: ${page.errors.length}`);
    for (const error of page.errors.slice(0, 10)) console.log(`  ${shown(error).slice(0, 300)}`);
    console.log(`requests that failed: ${page.failedRequests.length}`);
    for (const [reason, count] of Object.entries(Object.groupBy(page.failedRequests, reason => reason))) {
      console.log(`  ${count.length} × ${shown(reason)}`);
    }
    if (status >= 400 || status === 0 || page.errors.length) exitCode = 1;
  }
} catch (error) {
  console.error(`FAILED: ${shown(error.message)}`);
  exitCode = 1;
} finally {
  await browser.close();
}
process.exit(exitCode);
