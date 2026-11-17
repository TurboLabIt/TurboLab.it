"""
The state of the project's dependencies (Composer and Yarn). Run with `python3 -I`, from anywhere:

  deps.py status            the cooldown in force, whether vendor/ is there, and what the locks changed since the
                            last commit: each package, old → new
  deps.py lock-diff [REV]   the same list, against another commit (deps.py lock-diff HEAD~3)
  deps.py resolve-check     would a full Composer resolve work right now? Tried on a copy of composer.json in a
                            temporary folder: nothing of the project is touched

On a dev box `scripts/cache-clear.sh` removes vendor/ and composer.lock BEFORE resolving again. When a security
release is younger than the cooldown, that resolve fails (the fix is held back, the older versions are blocked), and
the box is left without vendor/: resolve-check tells in advance.
"""
import json, os, re, shutil, subprocess, sys, tempfile

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))


def fail(message: str, code: int = 1) -> None:
    print(f"FAILED: {message}", file=sys.stderr)
    sys.exit(code)


def shown(text: str) -> str:
    return re.sub(r'[\x00-\x08\x0b-\x1f\x7f-\x9f]', ' ', text)


def git_file(revision: str, path: str):
    """The file as it is in that commit; None when it isn't there."""
    result = subprocess.run(['git', '-C', PROJECT_DIR, 'show', f'{revision}:{path}'], capture_output=True)
    return result.stdout.decode('utf-8', 'replace') if result.returncode == 0 else None


def disk_file(path: str):
    try:
        with open(os.path.join(PROJECT_DIR, path), encoding='utf-8') as f:
            return f.read()
    except OSError:
        return None


def composer_packages(lock) -> dict:
    """name → "version (commit)" from a composer.lock's text."""
    if not lock:
        return {}
    try:
        data = json.loads(lock)
    except ValueError:
        fail("composer.lock isn't valid JSON: a merge conflict, or a hand edit?")
    packages = {}
    for package in data.get('packages', []) + data.get('packages-dev', []):
        reference = (package.get('source') or {}).get('reference') or (package.get('dist') or {}).get('reference') or ''
        version = package.get('version', '?')
        packages[package['name']] = version + (f" ({reference[:7]})" if version.startswith('dev-') else '')
    return packages


def yarn_packages(lock) -> dict:
    """name → "1.2.3" (or "1.2.3, 2.0.0" when several versions are installed) from a Yarn lock's text."""
    versions = {}
    for match in re.finditer(r'^  resolution: "?((?:@[^@/"]+/)?[^@"]+)@npm:([^"\n]+)"?$', lock or '', re.M):
        versions.setdefault(match.group(1), set()).add(match.group(2))
    return {name: ', '.join(sorted(found)) for name, found in versions.items()}


def changes(old: dict, new: dict) -> list:
    return [(name, old.get(name), new.get(name)) for name in sorted(set(old) | set(new))
            if old.get(name) != new.get(name)]


def lock_diff(revision: str) -> int:
    """Prints what composer.lock and yarn.lock changed since that commit; returns how many packages moved."""
    total = 0
    for path, parse in (('composer.lock', composer_packages), ('yarn.lock', yarn_packages)):
        old, new = git_file(revision, path), disk_file(path)
        if new is None:
            print(f"{path}: MISSING from the working tree" + (" (it is in the last commit)" if old else ''))
            continue
        moved = changes(parse(old), parse(new))
        total += len(moved)
        if not moved:
            print(f"{path}: " + ("new" if not old else "no package changed" if old != new else "as in the commit"))
            continue
        print(f"{path}: {len(moved)} package(s) changed")
        for name, before, after in moved:
            print(f"  {shown(name)}: {shown(before or 'new')} → {shown(after or 'removed')}")
    return total


def cmd_status() -> None:
    composer = json.loads(disk_file('composer.json') or '{}')
    period = ((composer.get('config') or {}).get('policy') or {}).get('cooldown', {}).get('period')
    gate = re.search(r'^npmMinimalAgeGate:\s*(\S+)', disk_file('.yarnrc.yml') or '', re.M)
    print(f"cooldown: Composer {period or 'NOT SET'}, Yarn {gate.group(1) if gate else 'NOT SET'}")
    vendor = os.path.isfile(os.path.join(PROJECT_DIR, 'vendor', 'autoload.php'))
    print("vendor/: " + ("installed" if vendor else "MISSING: a resolve failed, or it never ran. "
                         "`composer install` when composer.lock is there; otherwise see the skill"))
    installed = os.path.isfile(os.path.join(PROJECT_DIR, '.pnp.cjs'))
    print("Yarn: " + ("installed" if installed else "not installed (no .pnp.cjs)"))

    moved = lock_diff('HEAD')
    files = ['composer.json', 'composer.lock', 'package.json', 'yarn.lock', 'symfony.lock', '.yarnrc.yml']
    status = subprocess.run(['git', '-C', PROJECT_DIR, 'status', '--porcelain', '--', *files],
                            capture_output=True, text=True).stdout
    touched = [line[3:] for line in status.splitlines()]
    if not touched:
        print("nothing to commit in the manifests or the locks")
    elif moved and not {'composer.json', 'package.json'} & set(touched):
        print(f"→ a lock bump ({', '.join(touched)}): commit it now, alone, as the tli-commit skill says")
    else:
        print(f"→ changed: {', '.join(touched)}. A manifest and its lock go in the same commit; if only version "
              "ranges moved it is a bump, otherwise it belongs with the code that needs the dependency")


def cmd_resolve_check() -> None:
    box = tempfile.mkdtemp(prefix='tli-resolve-check.')
    try:
        shutil.copy2(os.path.join(PROJECT_DIR, 'composer.json'), box)
        # no lock, no plugins, no scripts: what `composer install` does on dev after cache-clear.sh removed the lock
        resolve = ['composer', 'update', '--no-install', '--no-scripts', '--no-plugins', '--no-interaction',
                   '--no-progress']
        result = subprocess.run(resolve, cwd=box, capture_output=True, text=True, timeout=900)
        output = shown(result.stdout + result.stderr)
        if result.returncode == 0:
            locked = composer_packages(open(os.path.join(box, 'composer.lock'), encoding='utf-8').read())
            moved = changes(composer_packages(disk_file('composer.lock')), locked)
            print(f"OK: a full resolve works right now ({len(locked)} packages). Against today's composer.lock it "
                  f"would change {len(moved)} package(s)" + (':' if moved else '.'))
            for name, before, after in moved[:60]:
                print(f"  {shown(name)}: {shown(before or 'new')} → {shown(after or 'removed')}")
            return
        print("A FULL RESOLVE FAILS RIGHT NOW. Don't run scripts/cache-clear.sh on a dev box until it passes: it "
              "removes vendor/ and composer.lock first, and would leave the box without them.")
        problem = output[output.find('Problem 1'):] if 'Problem 1' in output else output[-1500:]
        print(problem.strip()[:2500])
        sys.exit(1)
    finally:
        shutil.rmtree(box, ignore_errors=True)


def main() -> None:
    command = sys.argv[1] if len(sys.argv) > 1 else ''
    if command == 'status' and len(sys.argv) == 2:
        cmd_status()
    elif command == 'lock-diff' and len(sys.argv) <= 3:
        revision = sys.argv[2] if len(sys.argv) == 3 else 'HEAD'
        if subprocess.run(['git', '-C', PROJECT_DIR, 'rev-parse', '--verify', '--quiet', f'{revision}^{{commit}}'],
                          capture_output=True).returncode != 0:
            fail(f"«{shown(revision)}» isn't a commit", 2)
        lock_diff(revision)
    elif command == 'resolve-check' and len(sys.argv) == 2:
        cmd_resolve_check()
    else:
        fail("usage: deps.py status | lock-diff [REV] | resolve-check", 2)


if __name__ == '__main__':
    main()
