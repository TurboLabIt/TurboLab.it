#!/usr/bin/env node
/**
 * Lab Core Web Vitals with Lighthouse, measured the way that can be trusted on a busy dev box: several runs, the
 * addresses taken in turn, medians, and the server's own response time next to every figure.
 *
 *   node lighthouse.mjs URL [URL…] [--runs 5] [--desktop] [--block-consent] [--json FILE] [--keep DIR] [--any-site]
 *
 * With two or more addresses the runs alternate (A B A B…), so whatever disturbs the box disturbs both alike.
 * Lighthouse comes from npm through npx, at the version pinned below; Chrome is the one the tli-browser-test skill
 * uses, started with the same flags.
 */
import { spawn } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { parseArgs } from 'node:util';
import { CONSENT_DIALOG, chromeFlags, chromePath, siteUrl } from '../../tli-browser-test/scripts/chrome.mjs';

// a fixed version: two measurements are comparable only when the same Lighthouse made them. To move it, pick a
// stable release at least 5 days old (the project's cooldown) and say so when comparing with older figures
const LIGHTHOUSE_VERSION = process.env.LIGHTHOUSE_VERSION || '13.6.0';
const METRICS = [
  ['score', 'performance', value => String(Math.round(value))],
  ['fcp', 'first-contentful-paint', value => `${(value / 1000).toFixed(2)} s`],
  ['lcp', 'largest-contentful-paint', value => `${(value / 1000).toFixed(2)} s`],
  ['cls', 'cumulative-layout-shift', value => value.toFixed(3)],
  ['tbt', 'total-blocking-time', value => `${Math.round(value)} ms`],
  ['ttfb', 'server-response-time', value => `${Math.round(value)} ms`],
];

const { values: options, positionals: addresses } = parseArgs({
  allowPositionals: true,
  options: {
    runs: { type: 'string', default: '5' }, desktop: { type: 'boolean' }, 'block-consent': { type: 'boolean' },
    json: { type: 'string' }, keep: { type: 'string' }, 'any-site': { type: 'boolean' },
  },
});
const runs = Number(options.runs);
if (!addresses.length || !Number.isInteger(runs) || runs < 1 || runs > 30) {
  console.error('usage: node lighthouse.mjs URL [URL…] [--runs 5] [--desktop] [--block-consent] [--json FILE]');
  process.exit(2);
}
if (!/^\d+\.\d+\.\d+$/.test(LIGHTHOUSE_VERSION)) {
  console.error(`LIGHTHOUSE_VERSION must be an exact version like 13.6.0, not «${LIGHTHOUSE_VERSION}»`);
  process.exit(2);
}
let urls;
try {
  urls = addresses.map(address => siteUrl(address, { anySite: options['any-site'] }).href);
} catch (error) {
  console.error(`FAILED: ${error.message}`);
  process.exit(2);
}

// npx must not run inside the project: Yarn's Plug'n'Play would get in its way
const work = mkdtempSync(join(tmpdir(), 'tli-lighthouse.'));
process.once('exit', () => rmSync(work, { recursive: true, force: true }));
for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) process.once(signal, () => process.exit(1));

function lighthouse(url, file) {
  const args = ['--yes', `lighthouse@${LIGHTHOUSE_VERSION}`, url, '--only-categories=performance', '--output=json',
    `--output-path=${file}`, '--quiet', `--chrome-flags=${chromeFlags().join(' ')}`];
  if (options.desktop) args.push('--preset=desktop');
  if (options['block-consent']) args.push(...CONSENT_DIALOG.map(pattern => `--blocked-url-patterns=${pattern}`));
  return new Promise(done => {
    const child = spawn('npx', args, { cwd: work, env: { ...process.env, CHROME_PATH: chromePath() }, stdio: ['ignore', 'ignore', 'pipe'] });
    let stderr = '';
    child.stderr.on('data', chunk => { stderr = (stderr + chunk).slice(-1500); });
    const timer = setTimeout(() => child.kill('SIGKILL'), 240000);
    child.on('error', error => { clearTimeout(timer); done({ error: error.message }); });
    child.on('exit', code => {
      clearTimeout(timer);
      try {
        const report = JSON.parse(readFileSync(file, 'utf8'));
        if (report.runtimeError) return done({ error: report.runtimeError.message });
        const values = {};
        for (const [name, audit] of METRICS) {
          values[name] = name === 'score' ? report.categories.performance.score * 100 : report.audits[audit].numericValue;
        }
        done({ values });
      } catch {
        done({ error: `Lighthouse exited with code ${code}: ${stderr.trim().split('\n').pop() ?? 'no report written'}` });
      }
    });
  });
}

const median = values => {
  const sorted = [...values].sort((a, b) => a - b), middle = Math.floor(sorted.length / 2);
  return sorted.length % 2 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
};

const results = new Map(urls.map(url => [url, []]));
let failed = 0;
console.log(`Lighthouse ${LIGHTHOUSE_VERSION}, ${options.desktop ? 'desktop' : 'mobile'} profile, ${runs} run(s) per address` +
  (options['block-consent'] ? ', consent dialog blocked' : ''));
for (let run = 1; run <= runs; run++) {
  for (const [index, url] of urls.entries()) {
    const report = join(work, `run-${run}-${index}.json`);
    const { values, error } = await lighthouse(url, report);
    if (options.keep && !error) {
        // the whole report of the run: it names the LCP element, what shifted, what blocked
        mkdirSync(options.keep, { recursive: true });
        copyFileSync(report, join(options.keep, `run-${run}-page-${index + 1}.json`));
    }
    if (error) {
      failed++;
      console.log(`run ${run} ${url}: FAILED: ${error.replace(/[\x00-\x1f\x7f-\x9f]/g, ' ').slice(0, 300)}`);
      continue;
    }
    results.get(url).push(values);
    console.log(`run ${run} ${url}: ` + METRICS.map(([name, , format]) => `${name} ${format(values[name])}`).join(', '));
  }
}

const summary = {};
console.log('\nMedians (lowest to highest):');
for (const [url, measured] of results) {
  if (!measured.length) continue;
  summary[url] = {};
  console.log(url);
  for (const [name, , format] of METRICS) {
    const values = measured.map(run => run[name]);
    summary[url][name] = { median: median(values), min: Math.min(...values), max: Math.max(...values), runs: values };
    console.log(`  ${name.padEnd(5)} ${format(median(values)).padStart(8)}   (${format(Math.min(...values))} to ${format(Math.max(...values))})`);
  }
  const ttfb = summary[url].ttfb;
  if (ttfb.max > 2 * Math.max(ttfb.median, 100)) {
    console.log(`  NOTE: the server took ${Math.round(ttfb.max)} ms in the slowest run against a median of ` +
      `${Math.round(ttfb.median)} ms: the box was busy at times. Trust the medians, not single runs, and run more.`);
  }
  if (measured.length < 3) console.log('  NOTE: fewer than 3 good runs: too few for a median to mean much.');
}
if (options.json) writeFileSync(options.json, JSON.stringify({ lighthouse: LIGHTHOUSE_VERSION, desktop: !!options.desktop, summary }, null, 2));
process.exit(failed ? 1 : 0);
