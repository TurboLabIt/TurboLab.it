---
name: tli-lighthouse
description: >-
  Measure a TurboLab.it page's loading performance with Lighthouse: the lab Core Web Vitals (LCP, CLS, TBT, FCP)
  and the performance score, on a dev copy or on production. Use this skill whenever speed or layout stability is
  the question: "is it slower now", "check the Core Web Vitals", "did this change hurt performance", "run
  Lighthouse", "what's the LCP", or before and after touching CSS, fonts, images, scripts, ads or anything in the
  page's head. It carries a runner that takes several runs, alternates the pages being compared and reports
  medians, because a single run on a dev box is noise.
---

# Measuring a page with Lighthouse

Lighthouse loads a page in Chrome with a throttled network and CPU, and reports how the load went: when content
first shows (FCP), when the main content does (LCP), how much the layout jumps (CLS), how long the page keeps the
main thread busy (TBT), and a 0-100 score that summarizes them. These are lab figures: good for comparing two
versions of a page, not a promise of what visitors get.

`scripts/lighthouse.mjs` runs it the way that gives figures worth comparing. It needs Node and network access to
npm (Lighthouse is fetched by `npx`, at the version pinned in the script), and it uses the Chrome and the launch
flags of the `tli-browser-test` skill.

```bash
L=.claude/skills/tli-lighthouse/scripts/lighthouse.mjs
node $L https://dev0.turbolab.it/                                    # 5 runs, mobile profile, medians
node $L https://dev0.turbolab.it/ https://dev0.turbolab.it/1939      # two pages, runs alternated
node $L <url> --runs 7 --desktop --block-consent --json <file> --keep <folder>
```

- `--runs N`: 5 by default. Fewer than 5 is a smoke test, not a measurement. A run takes about 20 seconds.
- `--desktop`: Lighthouse's desktop profile. The default is the mobile one, which is the harder and the one Google
  ranks by.
- `--block-consent`: keeps the consent dialog off, to measure the site's own code. Without it you measure what a
  first-time visitor gets, dialog included.
- `--json FILE`: the medians and every run's figures, to keep for a later comparison.
- `--keep DIR`: Lighthouse's full report of every run.

For each address it prints every run, then the median with the lowest and the highest value of each metric, and
`ttfb`: how long the server took to answer in that run.

## What makes a comparison valid

On a dev box the figures move by themselves, and it looks exactly like a regression. dev0 runs the site's cron
jobs every minute, and when one fires the server answers in seconds instead of a fraction of one: FCP and LCP of
that run go up by as much, and the score drops by tens of points.

- **Compare medians of 5 runs or more**, never two single runs.
- **Compare under the same conditions.** Two addresses given together are measured in turn, so a busy minute hits
  both. For a before and after of the same address, measure both states close in time, and look at `ttfb` in the
  two outputs: if it differs, the box did, and the comparison isn't valid yet.
- **Know what your change can move.** A change in the browser (CSS, a script, an image) can't change `ttfb`. If
  `ttfb` moved, it's the environment. If only `ttfb`-driven figures moved (FCP, LCP, by the same amount), likewise.
- **The script says when the box was busy**: a NOTE under the address when one run's `ttfb` is more than double
  the median. Run it again, with more runs.
- **Absolute figures on a dev copy are worse than production's**: the Symfony toolbar is on the page, nothing is
  cached, and the box is small. Judge a change by the difference, and take the absolute figures from production.

## Production

`node $L https://turbolab.it/<page>` measures the live site: it is one page load per run, like a visitor's. Use it
for the figures that count, and for a change that is already deployed. External testing services can't reach a
dev copy (it isn't on the public DNS), so a dev copy is measured from the box itself, with this script.

## Reading the result

- **LCP** under 2.5 s and **CLS** under 0.1 are Google's "good" thresholds; **TBT** stands in for responsiveness
  in the lab (under 200 ms is good).
- **CLS is the one to protect here**: ads and web fonts are what moved the layout in the past. A CLS that rises
  after a change in the head, in the fonts or around an ad slot is that change until proven otherwise.
- **The LCP element may be text.** On an article page it was the title: then what delays it is the CSS and font
  chain, not an image.
- The score weighs the metrics and rounds: read the metrics first, the score last.
- To see what Lighthouse saw, pass `--keep <folder>`: every run's full report is saved there. In it,
  `audits['lcp-breakdown-insight']` names the LCP element and where its time went, and
  `audits['cls-culprits-insight']` and `audits['layout-shifts']` name what moved.

The script refuses addresses outside `turbolab.it` unless `--any-site` is given, like the browser skill, and
leaves no browser or temporary folder behind.
