"""
The deprecations that are really live in the project. Neither the test suite nor the logs tell: the suite ignores
the ones raised outside src/, Doctrine reports only the first hit of each kind, and the dev log mixes months.
Run with `python3 -I`, from anywhere:

  deprecations.py suite [TEST…]      run tests with every deprecation shown, listed once each (no TEST: the whole suite)
  deprecations.py doctrine [TEST…]   the same run, with a probe recording the call site of every Doctrine deprecation
  deprecations.py twig               deprecated syntax in the templates
  deprecations.py log [--days 14]    what var/log/dev.log holds: each message with its first and last day

Nothing in the project is changed: the test configuration is copied to a temporary folder and adjusted there.
"""
import argparse, collections, datetime, os, re, shutil, subprocess, sys, tempfile

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))
PROBE = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'doctrine-probe.php')
CONTROL = 'tli-deprecations control: this line must be listed'
PROBE_CONTROL = 'tli-deprecations control: the probe is loaded inside the tests'
CONTROL_TEST = f"""<?php
use PHPUnit\\Framework\\TestCase;

final class TliDeprecationControlTest extends TestCase
{{
    public function testControl() : void
    {{
        trigger_error('{CONTROL}', E_USER_DEPRECATED);
        if (getenv('TLI_PROBE_LOG')) {{
            Doctrine\\Deprecations\\Deprecation::trigger(
                'tli/probe', 'https://github.com/doctrine/deprecations', '{PROBE_CONTROL}');
        }}
        $this->assertTrue(true);
    }}
}}
"""


def fail(message: str, code: int = 1) -> None:
    print(f"FAILED: {message}", file=sys.stderr)
    sys.exit(code)


def shown(text: str) -> str:
    return re.sub(r'[\x00-\x08\x0b-\x1f\x7f-\x9f]', ' ', text)


def short(path: str) -> str:
    return path[len(PROJECT_DIR) + 1:] if path.startswith(PROJECT_DIR + '/') else path


def config_copy(work: str) -> str:
    """phpunit.dist.xml with indirect deprecations on, nothing that stops the run early, and absolute paths."""
    with open(os.path.join(PROJECT_DIR, 'phpunit.dist.xml'), encoding='utf-8') as f:
        xml = f.read()
    for old, new in (
            ('ignoreIndirectDeprecations="true"', 'ignoreIndirectDeprecations="false"'),
            ('failOnDeprecation="true"', 'failOnDeprecation="false"'),
            ('stopOnError="true"', 'stopOnError="false"'), ('stopOnFailure="true"', 'stopOnFailure="false"'),
            ('bootstrap="tests/bootstrap.php"', f'bootstrap="{PROJECT_DIR}/tests/bootstrap.php"'),
            ('cacheDirectory=".phpunit.cache"', f'cacheDirectory="{work}/cache"'),
            ('<directory>tests</directory>', f'<directory>{PROJECT_DIR}/tests</directory>'),
            ('<directory>src</directory>', f'<directory>{PROJECT_DIR}/src</directory>'),
            ('"vendor/phpunit/phpunit/phpunit.xsd"', f'"{PROJECT_DIR}/vendor/phpunit/phpunit/phpunit.xsd"')):
        if xml.count(old) != 1:
            fail(f"phpunit.dist.xml no longer has {old}: this script needs updating to the new configuration")
        xml = xml.replace(old, new)
    path = os.path.join(work, 'phpunit.xml')
    with open(path, 'w', encoding='utf-8') as f:
        f.write(xml)
    return path


def run_tests(config: str, tests: list, env: dict, control: str) -> str:
    """PHPUnit's output for those tests plus the control test; the whole suite, in parallel, when none is named."""
    tests = [os.path.abspath(test) for test in tests]
    for test in tests:
        if not os.path.exists(test):
            fail(f"no such test: {test}", 2)
    if tests:
        command = ['php', 'bin/phpunit', '-c', config, '--colors=never', '--display-deprecations', control, *tests]
    else:
        print("the whole suite: a few minutes, and heavy on a small box. Name test files or folders to go faster.",
              flush=True)
        # paratest takes one path: the control runs first, on its own
        alone = ['php', 'bin/phpunit', '-c', config, '--colors=never', '--display-deprecations', control]
        first = subprocess.run(alone, cwd=PROJECT_DIR, capture_output=True, text=True, env=env, timeout=300)
        if CONTROL not in first.stdout:
            fail(f"the control deprecation wasn't listed: {shown(first.stdout + first.stderr)[-400:]}")
        command = ['php', 'vendor/bin/paratest', '-c', config, '--colors=never', '--display-deprecations']
    result = subprocess.run(command, cwd=PROJECT_DIR, capture_output=True, text=True, env=env, timeout=3600)
    output = result.stdout + result.stderr
    if tests and CONTROL not in output:
        fail(f"the control deprecation wasn't listed, so a clean result would prove nothing: {shown(output)[-600:]}")
    outcome = re.findall(r'^(?:OK|Tests:|ERRORS!|FAILURES!|WARNINGS!).*$', output, re.M)
    print(f"PHPUnit: {shown(' '.join(outcome[-2:])) or 'no summary line: ' + shown(output)[-300:]}")
    return output


def listed(output: str) -> collections.Counter:
    """(where, message) → how many times, from PHPUnit's "N tests triggered M deprecations:" list."""
    found = collections.Counter()
    section = re.search(r'^\d+ tests? triggered \d+ deprecations?:\n(.*?)'
                        r'(?:\n(?:OK|ERRORS!|FAILURES!|WARNINGS!|Tests:)|\Z)', output, re.S | re.M)
    for entry in re.split(r'^\d+\) ', section.group(1) if section else '', flags=re.M)[1:]:
        where, _, rest = entry.partition('\n')
        message = ' '.join(line.strip() for line in rest.split('\n\n')[0].split('\n') if line.strip())
        found[(short(where.strip()), message)] += 1
    return found


def cmd_suite(args, work: str) -> None:
    control = os.path.join(work, 'TliDeprecationControlTest.php')
    with open(control, 'w', encoding='utf-8') as f:
        f.write(CONTROL_TEST)
    found = listed(run_tests(config_copy(work), args.tests, dict(os.environ), control))
    found = {key: count for key, count in found.items() if key[1] != CONTROL}
    if not found:
        print("no deprecation in this run (the control one was listed, so the listing works)")
        return
    print(f"{len(found)} deprecation(s), each listed once:")
    for (where, message), count in sorted(found.items()):
        print(f"\n{shown(where)}" + (f"  ({count}×)" if count > 1 else '') + f"\n  {shown(message)[:600]}")


def cmd_doctrine(args, work: str) -> None:
    log = os.path.join(work, 'probe.log')
    with open(os.path.join(work, 'probe.ini'), 'w', encoding='utf-8') as f:
        f.write(f'auto_prepend_file="{PROBE}"\n')
    # the leading colon keeps PHP's own configuration folders and adds this one: every worker inherits it
    env = dict(os.environ, PHP_INI_SCAN_DIR=f":{work}", TLI_PROBE_LOG=log,
               TLI_PROBE_VENDOR=os.path.join(PROJECT_DIR, 'vendor'))
    # a script file, not `php -r`: PHP doesn't prepend anything to code given on the command line
    control_script = os.path.join(work, 'control.php')
    with open(control_script, 'w', encoding='utf-8') as f:
        f.write('<?php Doctrine\\Deprecations\\Deprecation::trigger("tli/probe", '
                '"https://github.com/doctrine/deprecations", "probe control");\n')
    check = subprocess.run(['php', control_script], cwd=PROJECT_DIR, capture_output=True, text=True, env=env)
    if not os.path.exists(log) or 'probe control' not in open(log, encoding='utf-8').read():
        fail(f"the probe isn't loaded, so an empty result would prove nothing: {shown(check.stderr)[-400:]}")
    os.remove(log)

    control = os.path.join(work, 'TliDeprecationControlTest.php')
    with open(control, 'w', encoding='utf-8') as f:
        f.write(CONTROL_TEST)
    run_tests(config_copy(work), args.tests, env, control)
    sites = collections.Counter()
    if os.path.exists(log):
        with open(log, encoding='utf-8', errors='replace') as f:
            for line in f:
                where, _, message = line.rstrip('\n').partition('\t')
                sites[(message, short(where))] += 1
    in_tests = [key for key in sites if PROBE_CONTROL in key[0]]
    if not in_tests:
        fail("the probe answered on the command line but not inside the tests: an empty result would prove nothing")
    for key in in_tests:
        del sites[key]
    if not sites:
        print("no Doctrine deprecation in the code these tests run (the probe answered its control). Code that no "
              "test runs isn't seen: a pattern that matters needs a static check, as in tests/Convention")
        return
    messages = collections.defaultdict(list)
    for (message, where), count in sites.items():
        messages[message].append((count, where))
    print(f"{len(messages)} Doctrine deprecation(s), {len(sites)} call site(s):")
    for message, places in sorted(messages.items(), key=lambda item: -sum(count for count, _ in item[1])):
        print(f"\n{shown(message)[:500]}")
        for count, where in sorted(places, reverse=True)[:40]:
            print(f"  {count:6d}×  {shown(where)}")


def cmd_twig(args, work: str) -> None:
    result = subprocess.run(['php', 'bin/console', 'lint:twig', 'templates', '--show-deprecations'], cwd=PROJECT_DIR,
                            capture_output=True, text=True, timeout=600)
    print(shown(result.stdout + result.stderr).strip()[-3000:])
    sys.exit(result.returncode)


def cmd_log(args, work: str) -> None:
    path = os.path.join(PROJECT_DIR, 'var', 'log', 'dev.log')
    if not os.path.exists(path):
        fail(f"no {short(path)}: this reads the dev log, which only a dev box has")
    grep = subprocess.Popen(['grep', '-a', '-F', ' deprecation.', path], stdout=subprocess.PIPE, text=True,
                            errors='replace')
    seen = {}
    for line in grep.stdout:
        match = re.match(r'\[(\d{4}-\d{2}-\d{2})[^\]]*\] deprecation\.\w+: (.*?)(?: \{"exception"| \[\]|$)', line)
        if not match:
            continue
        day, message = match.group(1), match.group(2).strip()
        where = re.search(r' at (/[^ :)]+:\d+)\)', line)
        entry = seen.setdefault(message, [0, day, day, ''])
        entry[0] += 1
        entry[1], entry[2] = min(entry[1], day), max(entry[2], day)
        if where and day == entry[2]:
            entry[3] = short(where.group(1))
    grep.wait()
    if not seen:
        print("no deprecation line in the dev log")
        return
    limit = (datetime.date.today() - datetime.timedelta(days=args.days)).isoformat()
    live = {message: entry for message, entry in seen.items() if entry[2] >= limit}
    print(f"{len(seen)} different deprecation(s) in {short(path)}; {len(live)} seen in the last {args.days} days, "
          f"{len(seen) - len(live)} only before (a line that old is most likely fixed: check its file and line "
          "against today's source before calling it present).")
    latest_first = sorted(live.items(), key=lambda item: item[1][2], reverse=True)
    for message, (count, first, last, where) in latest_first[:40]:
        print(f"\n{shown(message)[:400]}\n  {count}× from {first} to {last}"
              + (f", last at {shown(where)}" if where else ''))


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest='command', required=True)
    for name, run in (('suite', cmd_suite), ('doctrine', cmd_doctrine)):
        command = sub.add_parser(name)
        command.add_argument('tests', nargs='*', metavar='TEST', help='test files or folders (default: everything)')
        command.set_defaults(run=run)
    sub.add_parser('twig').set_defaults(run=cmd_twig)
    log = sub.add_parser('log')
    log.add_argument('--days', type=int, default=14)
    log.set_defaults(run=cmd_log)
    args = parser.parse_args()

    work = tempfile.mkdtemp(prefix='tli-deprecations.')
    try:
        args.run(args, work)
    finally:
        shutil.rmtree(work, ignore_errors=True)


if __name__ == '__main__':
    main()
