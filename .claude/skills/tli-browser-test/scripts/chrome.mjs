/**
 * Headless Chrome for Testing, driven over the DevTools Protocol. Node 22 or later, no packages to install.
 *
 *   import { launch } from './chrome.mjs';
 *   const browser = await launch();
 *   const page = await browser.newPage();
 *   await page.goto('https://dev0.turbolab.it/');
 *   await page.hover('.some-selector');
 *   await page.screenshot('/path/to/shot.png');
 *   await browser.close();
 *
 * The protocol runs over a pipe to the browser, not over a TCP port: no other user of the PC can attach to it.
 * Every run gets a profile folder of its own, removed on close, and only the browser it started is ever killed.
 */
import { spawn } from 'node:child_process';
import { X509Certificate, createHash } from 'node:crypto';
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const PROJECT_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..', '..');
// the only sites this is for: TurboLab.it and its subdomains (the dev copies)
const SITE_HOST = /^(?:[a-z0-9-]+\.)*turbolab\.it$/;
// the consent dialog puts itself back when removed, and its backdrop takes every pointer event: kept off the network
export const CONSENT_DIALOG = ['*clickiocmp.com*', '*clickiocdn.com*'];
const sleep = ms => new Promise(done => setTimeout(done, ms));
// a signal ends Node without its exit handlers: turned into an exit, the browser and its profile are still removed
for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) process.once(signal, () => process.exit(1));

/** The Chrome for Testing binary: CHROME_PATH, or the newest one under ~/.cache/chrome-for-testing. */
export function chromePath() {
  if (process.env.CHROME_PATH) return process.env.CHROME_PATH;
  const root = join(homedir(), '.cache', 'chrome-for-testing', 'chrome');
  const versions = existsSync(root) ? readdirSync(root).filter(name => name.startsWith('linux-')) : [];
  versions.sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
  const found = versions.map(name => join(root, name, 'chrome-linux64', 'chrome')).filter(existsSync).pop();
  if (!found) {
    throw new Error('Chrome for Testing not found: install it with ' +
      '`npx --yes @puppeteer/browsers install chrome@stable --path ~/.cache/chrome-for-testing`, or set CHROME_PATH');
  }
  return found;
}

/** The https://NAME.turbolab.it address of the site or of a dev copy, as a URL; anything else is refused. */
export function siteUrl(address, { anySite = false } = {}) {
  const url = new URL(address);
  if (!anySite && (url.protocol !== 'https:' || !SITE_HOST.test(url.hostname) || url.username || url.password)) {
    throw new Error(`${address} isn't TurboLab.it or one of its subdomains: this browser is for the project's own ` +
      'sites (it may run without its sandbox, and carries a test session)');
  }
  return url;
}

/** dev0's self-signed certificate, named in the project's dev nginx configuration; null when there is none. */
export function devCertificate() {
  try {
    const conf = readFileSync(join(PROJECT_DIR, 'config', 'custom', 'dev', 'nginx-dev-shared.conf'), 'utf8');
    const file = /^\s*ssl_certificate\s+([^;]+);/m.exec(conf)?.[1].trim();
    return file && existsSync(file) ? file : null;
  } catch {
    return null;
  }
}

function certificateFingerprint(pemFile) {
  const key = new X509Certificate(readFileSync(pemFile)).publicKey.export({ type: 'spki', format: 'der' });
  return createHash('sha256').update(key).digest('base64');
}

class Connection {
  constructor(child) {
    this.child = child;
    this.lastId = 0;
    this.waiting = new Map();
    this.listeners = new Set();
    let pending = Buffer.alloc(0);
    child.stdio[4].on('data', chunk => {
      pending = Buffer.concat([pending, chunk]);
      for (let end = pending.indexOf(0); end !== -1; end = pending.indexOf(0)) {
        const message = JSON.parse(pending.subarray(0, end).toString('utf8'));
        pending = pending.subarray(end + 1);
        if (message.id && this.waiting.has(message.id)) {
          const { done, failed, method } = this.waiting.get(message.id);
          this.waiting.delete(message.id);
          message.error ? failed(new Error(`${method}: ${message.error.message}`)) : done(message.result);
        } else if (message.method) {
          for (const listener of this.listeners) listener(message);
        }
      }
    });
    child.on('exit', () => {
      for (const { failed, method } of this.waiting.values()) failed(new Error(`${method}: the browser has gone`));
      this.waiting.clear();
    });
  }

  send(method, params = {}, sessionId) {
    const id = ++this.lastId;
    return new Promise((done, failed) => {
      this.waiting.set(id, { done, failed, method });
      this.child.stdio[3].write(JSON.stringify({ id, method, params, sessionId }) + '\0');
    });
  }
}

class Page {
  constructor(connection, targetId, sessionId) {
    // errors: what the page's JavaScript threw or logged as an error. response: the status and the address of the
    // last document loaded in the tab (after redirects). failedRequests: what didn't load, the blocked dialog aside
    Object.assign(this, { connection, targetId, sessionId, errors: [], failedRequests: [], response: null });
    connection.listeners.add(message => {
      if (message.sessionId !== sessionId) return;
      if (message.method === 'Network.responseReceived' && message.params.type === 'Document'
          && message.params.frameId === this.targetId) {
        this.response = { status: message.params.response.status, url: message.params.response.url };
      } else if (message.method === 'Runtime.exceptionThrown') {
        const details = message.params.exceptionDetails;
        this.errors.push(details.exception?.description ?? details.text);
      } else if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') {
        this.errors.push(message.params.args.map(arg => arg.value ?? arg.description ?? '').join(' '));
      } else if (message.method === 'Network.loadingFailed' && !message.params.canceled
          && message.params.blockedReason !== 'inspector') {
        this.failedRequests.push(message.params.errorText);
      }
    });
  }

  send(method, params) {
    return this.connection.send(method, params, this.sessionId);
  }

  /** Resolves with the next event of that name, or fails after the timeout. */
  once(method, timeout = 30000) {
    return new Promise((done, failed) => {
      const listener = message => {
        if (message.sessionId !== this.sessionId || message.method !== method) return;
        clearTimeout(timer);
        this.connection.listeners.delete(listener);
        done(message.params);
      };
      const timer = setTimeout(() => {
        this.connection.listeners.delete(listener);
        failed(new Error(`no ${method} within ${timeout} ms`));
      }, timeout);
      this.connection.listeners.add(listener);
    });
  }

  /** Opens the address and waits for the load event, then for `settle` ms: scripts here are deferred. */
  async goto(address, { timeout = 45000, settle = 500, anySite = false } = {}) {
    const url = siteUrl(address, { anySite });
    const loaded = this.once('Page.loadEventFired', timeout);
    const { errorText } = await this.send('Page.navigate', { url: url.href });
    if (errorText) {
      loaded.catch(() => {});
      throw new Error(`${url.href}: ${errorText}`);
    }
    await loaded;
    await sleep(settle);
  }

  /** The value of a JavaScript expression in the page (promises are awaited). Throws what the page throws. */
  async evaluate(expression) {
    const { result, exceptionDetails } = await this.send('Runtime.evaluate',
      { expression, awaitPromise: true, returnByValue: true, userGesture: false });
    if (exceptionDetails) {
      throw new Error(exceptionDetails.exception?.description ?? exceptionDetails.text);
    }
    return result.value;
  }

  /** Waits until the expression is truthy and returns its value. */
  async waitFor(expression, { timeout = 15000, every = 100 } = {}) {
    for (const end = Date.now() + timeout; ;) {
      const value = await this.evaluate(expression);
      if (value) return value;
      if (Date.now() > end) throw new Error(`still false after ${timeout} ms: ${expression}`);
      await sleep(every);
    }
  }

  /**
   * Where an element is, measured and scrolled into view in one step: the page reflows between separate calls.
   * The site scrolls smoothly, so the scroll is forced to be instant.
   */
  async rect(selector) {
    const rect = await this.evaluate(`(() => {
      const element = document.querySelector(${JSON.stringify(selector)});
      if (!element) return null;
      element.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' });
      const box = element.getBoundingClientRect();
      return { x: box.x, y: box.y, width: box.width, height: box.height, scrollX, scrollY };
    })()`);
    if (!rect) throw new Error(`no element matches ${selector}`);
    return rect;
  }

  mouse(type, x, y, more = {}) {
    return this.send('Input.dispatchMouseEvent', { type, x, y, ...more });
  }

  /** Moves the pointer onto the element, re-aiming until the element really is under it. */
  async hover(selector, { attempts = 5 } = {}) {
    for (let attempt = 1; ; attempt++) {
      const box = await this.rect(selector);
      const x = box.x + box.width / 2, y = box.y + box.height / 2;
      await this.mouse('mouseMoved', x, y);
      const onIt = await this.evaluate(`(() => {
        const element = document.querySelector(${JSON.stringify(selector)});
        const under = document.elementFromPoint(${x}, ${y});
        return !!element && !!under && (element === under || element.contains(under));
      })()`);
      if (onIt) return { x, y };
      if (attempt >= attempts) throw new Error(`${selector} is covered by something else at its centre`);
      await sleep(400);
    }
  }

  /** A real click: the page sees a user gesture, which element.click() doesn't give. */
  async click(selector) {
    const { x, y } = await this.hover(selector);
    await this.mouse('mousePressed', x, y, { button: 'left', clickCount: 1 });
    await this.mouse('mouseReleased', x, y, { button: 'left', clickCount: 1 });
  }

  /** Types into whatever has the focus, replacing nothing: select first to replace. */
  type(text) {
    return this.send('Input.insertText', { text });
  }

  /** Enter, with the text a real key press carries: without it, forms don't submit. */
  async pressEnter() {
    const key = { key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r', unmodifiedText: '\r' };
    await this.send('Input.dispatchKeyEvent', { type: 'keyDown', ...key });
    await this.send('Input.dispatchKeyEvent', { type: 'keyUp', ...key });
  }

  /** Puts files in an <input type="file">: the page gets a real change event. */
  async setFiles(selector, files) {
    const { root } = await this.send('DOM.getDocument', {});
    const { nodeId } = await this.send('DOM.querySelector', { nodeId: root.nodeId, selector });
    if (!nodeId) throw new Error(`no element matches ${selector}`);
    await this.send('DOM.setFileInputFiles', { nodeId, files: files.map(file => resolve(file)) });
  }

  /** A PNG of the viewport, of the whole page (fullPage), or of one element (selector). */
  async screenshot(file, { fullPage = false, selector = null } = {}) {
    const params = { format: 'png', captureBeyondViewport: fullPage || !!selector };
    if (selector) {
      const box = await this.rect(selector);
      // the clip is in document coordinates
      params.clip = { x: box.x + box.scrollX, y: box.y + box.scrollY, width: box.width, height: box.height, scale: 1 };
    } else if (fullPage) {
      const { cssContentSize } = await this.send('Page.getLayoutMetrics', {});
      params.clip = { x: 0, y: 0, width: cssContentSize.width, height: cssContentSize.height, scale: 1 };
    }
    const { data } = await this.send('Page.captureScreenshot', params);
    writeFileSync(file, Buffer.from(data, 'base64'));
    return file;
  }

  close() {
    return this.connection.send('Target.closeTarget', { targetId: this.targetId });
  }
}

class Browser {
  constructor(child, connection, profile, userAgent) {
    Object.assign(this, { child, connection, profile, userAgent, closed: false });
  }

  /**
   * A new tab. `state` is a session file written by `forum.py login` (the tli-issue skill): the tab is then
   * logged in on that site, with the User-Agent the session was opened with (phpBB checks it on every request).
   */
  async newPage({ width = 1280, height = 900, mobile = false, state = null, keepConsentDialog = false } = {}) {
    const { targetId } = await this.connection.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await this.connection.send('Target.attachToTarget', { targetId, flatten: true });
    const page = new Page(this.connection, targetId, sessionId);
    await Promise.all(['Page.enable', 'Runtime.enable', 'Network.enable'].map(method => page.send(method)));
    await page.send('Emulation.setDeviceMetricsOverride',
      mobile ? { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }
        : { width, height, deviceScaleFactor: 1, mobile: false });
    if (mobile) await page.send('Emulation.setTouchEmulationEnabled', { enabled: true });
    if (!keepConsentDialog) await page.send('Network.setBlockedURLs', { urls: CONSENT_DIALOG });

    let userAgent = this.userAgent;
    if (state) {
      const session = JSON.parse(readFileSync(state, 'utf8'));
      const site = siteUrl(session.base_url);
      userAgent = session.ua || userAgent;
      const cookies = Object.entries(session.cookies ?? {}).map(([name, value]) =>
        ({ name, value: String(value), domain: site.hostname, path: '/', secure: true, httpOnly: true }));
      if (!cookies.length) throw new Error(`${state} holds no session: run forum.py login again`);
      await page.send('Network.setCookies', { cookies });
    }
    await page.send('Emulation.setUserAgentOverride', { userAgent });
    return page;
  }

  async close() {
    if (this.closed) return;
    this.closed = true;
    const gone = new Promise(done => this.child.once('exit', done));
    this.connection.send('Browser.close').catch(() => {});
    if (await Promise.race([gone.then(() => false), sleep(3000).then(() => true)])) this.child.kill('SIGKILL');
    await gone;
    rmSync(this.profile, { recursive: true, force: true });
  }
}

/**
 * The flags Chrome needs on this kind of box, whoever starts it (this library, or Lighthouse): headless, the
 * certificates to trust (dev0's by itself, plus the PEM files given), and no sandbox only where the kernel refuses
 * unprivileged user namespaces (AppArmor, as on dev0), because there Chrome can't build one.
 */
export function chromeFlags({ certificates = [] } = {}) {
  const flags = ['--headless=new', '--disable-gpu', '--mute-audio', '--no-first-run', '--no-default-browser-check'];
  const trusted = [devCertificate(), ...certificates].filter(Boolean).map(certificateFingerprint);
  if (trusted.length) flags.push(`--ignore-certificate-errors-spki-list=${trusted.join(',')}`);
  const restricted = '/proc/sys/kernel/apparmor_restrict_unprivileged_userns';
  if (existsSync(restricted) && readFileSync(restricted, 'utf8').trim() === '1') flags.push('--no-sandbox');
  return flags;
}

/**
 * Starts a browser of its own. `touch` gives a coarse pointer without hover (a phone); otherwise a mouse that
 * hovers, which headless Chrome doesn't report by itself. `certificates` are PEM files to trust: dev0's is
 * trusted by itself, and only that one, so TLS errors elsewhere still show.
 */
export async function launch({ touch = false, certificates = [], args = [] } = {}) {
  const profile = mkdtempSync(join(tmpdir(), 'tli-browser.'));
  const pointer = touch ? 'primaryPointerType=2,availablePointerTypes=2,primaryHoverType=1,availableHoverTypes=1'
    : 'primaryPointerType=4,availablePointerTypes=4,primaryHoverType=2,availableHoverTypes=2';
  const flags = [...chromeFlags({ certificates }), '--remote-debugging-pipe', `--user-data-dir=${profile}`,
    '--hide-scrollbars', '--window-size=1280,900', `--blink-settings=${pointer}`];

  const binary = chromePath();
  const child = spawn(binary, [...flags, ...args], { stdio: ['ignore', 'ignore', 'pipe', 'pipe', 'pipe'] });
  let stderr = '';
  const onExit = () => { child.kill('SIGKILL'); rmSync(profile, { recursive: true, force: true }); };
  process.once('exit', onExit);
  child.once('exit', () => process.removeListener('exit', onExit));

  try {
    await new Promise((spawned, failed) => {
      child.once('spawn', spawned);
      child.once('error', error => failed(new Error(`can't start ${binary}: ${error.message}`)));
    });
    child.stderr.on('data', chunk => { stderr = (stderr + chunk).slice(-2000); });
    const connection = new Connection(child);
    const started = new Promise((_, failed) => child.once('exit', code =>
      failed(new Error(`Chrome exited at once (code ${code}): ${stderr.trim().split('\n').pop()}`))));
    started.catch(() => {});
    const version = await Promise.race([connection.send('Browser.getVersion'), started]);
    // what a normal Chrome sends, plus a name the server logs can tell apart
    const userAgent = version.userAgent.replace('HeadlessChrome', 'Chrome') + ' tli-browser-test/1.0';
    return new Browser(child, connection, profile, userAgent);
  } catch (error) {
    onExit();
    throw error;
  }
}
