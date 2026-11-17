"""
What state a dev box is in, when something is slow, flaky or down. Read-only: it looks, measures and says what it
sees, and changes nothing. Run with `python3 -I`, from anywhere:

  doctor.py            every check
  doctor.py --quick    without the page timings (a few seconds less)

Each line starts with ok, NOTE (worth knowing before you measure or test) or PROBLEM (explains a failure by itself).
Exit code 1 when there is a PROBLEM.
"""
import http.client, json, os, re, shutil, ssl, statistics, subprocess, sys, time, urllib.parse

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))
problems = 0


def say(level: str, text: str) -> None:
    global problems
    problems += level == 'PROBLEM'
    print(f"{level:8}{text}")


def run(*command, timeout=20) -> str:
    try:
        return subprocess.run(command, capture_output=True, text=True, timeout=timeout).stdout.strip()
    except (OSError, subprocess.TimeoutExpired):
        return ''


def env_value(name: str) -> str:
    """A variable of the project, as Symfony would load it on a dev box: the most specific file first."""
    for file in ('.env.dev.local', '.env.local', '.env.dev', '.env'):
        try:
            with open(os.path.join(PROJECT_DIR, file), encoding='utf-8') as f:
                match = re.search(rf'^{name}=["\']?([^"\'\n]*)["\']?\s*$', f.read(), re.M)
        except OSError:
            continue
        if match and match.group(1):
            return match.group(1)
    return ''


def memory() -> None:
    info = {}
    with open('/proc/meminfo', encoding='ascii') as f:
        for line in f:
            key, _, value = line.partition(':')
            info[key] = int(value.split()[0]) // 1024
    swap_used = info['SwapTotal'] - info['SwapFree']
    text = (f"memory: {info['MemAvailable']} MB available of {info['MemTotal']}, swap {swap_used} of "
            f"{info['SwapTotal']} MB used")
    short = info['MemAvailable'] < 800 or (info['SwapTotal'] and swap_used > info['SwapTotal'] * 0.6)
    say('PROBLEM' if short else 'ok', text + (": short. Tests fail at random and searches time out in this state; "
                                              "don't start a full test run or a browser now" if short else ''))
    holders = {}
    for line in run('ps', '-eo', 'rss=,comm=').splitlines():
        rss, _, name = line.strip().partition(' ')
        if rss.isdigit():
            holders[name.strip()] = holders.get(name.strip(), 0) + int(rss) // 1024
    top = sorted(holders.items(), key=lambda item: -item[1])[:6]
    say('ok', "biggest: " + ', '.join(f"{name} {megabytes} MB" for name, megabytes in top))

    killed = run('journalctl', '-k', '-q', '--since', '-24h', '-g', 'Out of memory|oom-kill')
    if killed:
        lines = killed.splitlines()
        say('PROBLEM', f"the kernel killed a process for lack of memory in the last 24 hours ({len(lines)} line(s)): "
                       f"{lines[-1][:160]}. Failures around that time aren't code bugs")
    load, cores = os.getloadavg()[0], os.cpu_count() or 1
    say('NOTE' if load > cores else 'ok', f"load {load:.1f} on {cores} cores"
        + (": the box is busy, timings taken now are inflated" if load > cores else ''))


def services() -> None:
    for unit in ('nginx', 'mysql', 'meilisearch'):
        shown = run('systemctl', 'show', unit, '-p', 'ActiveState,NRestarts,ActiveEnterTimestamp')
        state = dict(line.split('=', 1) for line in shown.splitlines() if '=' in line)
        if state.get('ActiveState') != 'active':
            say('PROBLEM', f"{unit} is {state.get('ActiveState', 'unknown')}")
        elif state.get('NRestarts', '0') not in ('0', ''):
            say('PROBLEM', f"{unit} is running but has restarted {state['NRestarts']} time(s) by itself: it is "
                           f"crashing, or it was killed. `journalctl -u {unit} -n 30` says why")
        else:
            say('ok', f"{unit} running since {state.get('ActiveEnterTimestamp', '?')}")

    units = run('systemctl', 'list-units', '--type=service', '--no-legend', 'php*-fpm*')
    fpm = re.search(r'(php[\d.]+-fpm)\.service', units)
    if not fpm:
        say('PROBLEM', "no php-fpm service is running")
        return
    binary = 'php-fpm' + re.search(r'[\d.]+', fpm.group(1)).group(0)
    settings = run(binary, '-i')
    value = lambda key: (re.search(rf'^{re.escape(key)} => (\S+)', settings, re.M) or [None, ''])[1]
    workers = len(run('pgrep', '-f', 'php-fpm: pool').split())
    say('ok', f"{fpm.group(1)} running, {workers} worker(s) right now")
    mode, start = value('xdebug.mode'), value('xdebug.start_with_request')
    if 'debug' in mode and start == 'yes':
        port = value('xdebug.client_port') or '9003'
        listening = f":{port} " in run('ss', '-ltn')
        say('PROBLEM' if listening else 'NOTE',
            f"Xdebug connects to a debugger at every request (mode {mode}, start_with_request=yes)"
            + (f", and something listens on port {port}: if it doesn't answer, every PHP page waits about 2 seconds"
               if listening else ": harmless while nothing listens on its port"))
    elif mode and mode != 'off':
        say('ok', f"Xdebug mode {mode}, start_with_request={start or 'default'}: a debugger only on request "
                  "(develop mode still costs some tens of milliseconds per page)")


def search_engine() -> None:
    address = urllib.parse.urlsplit(env_value('MEILISEARCH_URL') or 'http://127.0.0.1:7700')
    try:
        connection = http.client.HTTPConnection(address.hostname, address.port or 7700, timeout=5)
        connection.request('GET', '/health')
        answer = json.loads(connection.getresponse().read() or b'{}')
        ok = answer.get('status') == 'available'
        say('ok' if ok else 'PROBLEM', f"Meilisearch answers: {answer.get('status', 'no status')}")
    except (OSError, ValueError) as error:
        say('PROBLEM', f"Meilisearch doesn't answer on {address.netloc} ({error}): the site's search fails. After a "
                       "package upgrade, look for a database-version message in `journalctl -u meilisearch`")


def site() -> None:
    base = env_value('APP_SITE_URL').rstrip('/')
    if not base.startswith('https://'):
        say('NOTE', "no APP_SITE_URL for this copy: page timings skipped")
        return
    host = urllib.parse.urlsplit(base).hostname
    cafile = None
    try:
        with open(os.path.join(PROJECT_DIR, 'config', 'custom', 'dev', 'nginx-dev-shared.conf'), encoding='utf-8') as f:
            match = re.search(r'^\s*ssl_certificate\s+([^;]+);', f.read(), re.M)
        cafile = match.group(1).strip() if match and os.path.isfile(match.group(1).strip()) else None
    except OSError:
        pass
    context = ssl.create_default_context(cafile=cafile)

    def first_byte(path: str):
        """(status, seconds until the answer starts) for one request."""
        try:
            connection = http.client.HTTPSConnection(host, 443, context=context, timeout=30)
            connection.connect()
            started = time.monotonic()
            connection.request('GET', path, headers={'User-Agent': 'tli-dev0-doctor/1.0'})
            response = connection.getresponse()
            elapsed = time.monotonic() - started
            response.read()
            return response.status, elapsed
        except OSError as error:
            return str(error), None

    static = [first_byte('/robots.txt') for _ in range(3)]
    pages = [first_byte('/') for _ in range(5)]
    if pages[0][1] is None:
        say('PROBLEM', f"{base}/ doesn't answer: {pages[0][0]}")
        return
    times = [elapsed for _, elapsed in pages if elapsed is not None]
    status = pages[0][0]
    file_times = [elapsed for _, elapsed in static if elapsed is not None]
    file_time = statistics.median(file_times) if file_times else 0
    median, slowest = statistics.median(times), max(times)
    text = (f"{base}/ answers HTTP {status}: a page takes {median * 1000:.0f} ms to start (slowest of 5: "
            f"{slowest * 1000:.0f} ms), a plain file {file_time * 1000:.1f} ms")
    if status >= 500:
        say('PROBLEM', text + ". The page fails: var/log/dev.log has the exception")
    elif median > 1.5:
        say('PROBLEM', text + ". Every page is slow while files aren't: it's PHP. Xdebug waiting for a debugger, "
                              "memory, or a cache being rebuilt")
    elif slowest > 3 * max(median, 0.15):
        say('NOTE', text + ". One request was much slower than the others: a scheduled job ran (they fire every "
                           "minute). Single timings can't be trusted: take several, compare medians")
    else:
        say('ok', text)


def surroundings() -> None:
    jobs = [line for line in run('pgrep', '-fa', 'bin/console|scripts/[a-z-]+\\.sh|paratest|phpunit').splitlines()
            if 'pgrep' not in line and 'doctor.py' not in line]
    if jobs:
        names = sorted({re.sub(r'^\d+ ', '', job)[:70] for job in jobs})
        say('NOTE', f"{len(jobs)} job(s) of the project running right now ({'; '.join(names[:3])}): they share the "
                    "database and the CPU with whatever you run")
    browsers = len([line for line in run('pgrep', '-fa', 'chrome').splitlines()
                    if '--type=' not in line and 'pgrep' not in line])
    sessions = len(run('pgrep', '-x', 'claude').split())
    if browsers or sessions > 1:
        say('NOTE', f"{sessions} Claude session(s) and {browsers} browser(s) on this box: others may be testing, "
                    "committing or building in the same checkout")
    for label, path in (('the project', PROJECT_DIR), ('/tmp', '/tmp')):
        usage = shutil.disk_usage(path)
        free = usage.free // 2 ** 30
        say('PROBLEM' if free < 2 else 'ok', f"disk: {free} GB free for {label}")
    log = os.path.join(PROJECT_DIR, 'var', 'log', 'dev.log')
    if os.path.exists(log):
        say('ok', f"var/log/dev.log is {os.path.getsize(log) // 2 ** 20} MB: it is never rotated, so filter it by date")
    if not os.path.isfile(os.path.join(PROJECT_DIR, 'vendor', 'autoload.php')):
        say('PROBLEM', "vendor/ is missing: a Composer resolve failed after the lock was removed (the tli-deps skill)")
    if not os.path.isfile(os.path.join(PROJECT_DIR, 'public', 'build', 'manifest.json')):
        say('PROBLEM', "public/build/manifest.json is missing: the assets were never built, every page fails")
    mail = run('php', os.path.join(PROJECT_DIR, 'bin', 'console'), 'dbal:run-sql',
               "SELECT config_value FROM turbolab_it_forum.phpbb_config WHERE config_name = 'email_enable'")
    enabled = re.search(r'^\s*([01])\s*$', mail, re.M)
    if enabled:
        say('PROBLEM' if enabled.group(1) == '1' else 'ok',
            "forum e-mail is ON: this copy has real users' addresses, a post or a reply would mail them"
            if enabled.group(1) == '1' else "forum e-mail is off: posting on this copy mails nobody")


def main() -> None:
    if env_value('APP_ENV') not in ('', 'dev') and os.environ.get('APP_ENV', 'dev') != 'dev':
        print("this box doesn't look like a dev copy: the checks assume one", file=sys.stderr)
    memory()
    services()
    search_engine()
    if '--quick' not in sys.argv[1:]:
        site()
    surroundings()
    print(f"\n{problems} problem(s)" if problems else "\nnothing wrong that these checks can see")
    sys.exit(1 if problems else 0)


if __name__ == '__main__':
    main()
