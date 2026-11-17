"""
The project's own Composer packages (turbolabit/*): where each one stands, and how a change in one reaches
production. Run with `python3 -I`, from anywhere:

  packages.py status                 lock, working copy and Packagist, side by side, with what to do next
  packages.py bump NAME [NAME…]      move the lock, and only the lock, to what Packagist serves for those packages
  packages.py prod-test PATH [PATH…] what production would get: the last commit plus those paths, installed from the
                                     lock (no dev symlinks), with every class of the project loaded once

Production installs from composer.lock at every deploy, so a pushed package changes nothing there until the lock
names its new commit. On a dev box vendor/turbolabit/* are links to the working copies in ../php-packages, which is
why a missing lock bump, or a package that doesn't install cleanly, never shows here.
"""
import argparse, json, os, re, shutil, subprocess, sys, tempfile, urllib.request

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))
VENDOR = 'turbolabit/'
PACKAGIST = 'https://repo.packagist.org/p2/{name}~dev.json'
LOCK_ONLY = ['--no-install', '--no-scripts', '--no-plugins', '--no-audit', '--no-interaction']


def fail(message: str, code: int = 1) -> None:
    print(f"FAILED: {message}", file=sys.stderr)
    sys.exit(code)


def run(*command, cwd=None, check=True, timeout=600) -> str:
    result = subprocess.run(command, cwd=cwd, capture_output=True, text=True, timeout=timeout)
    if check and result.returncode != 0:
        fail(f"{' '.join(command[:4])}…: {(result.stderr or result.stdout).strip()[-600:]}")
    return result.stdout.strip() if check else (result.returncode, result.stdout.strip(), result.stderr.strip())


def locked(project: str) -> dict:
    """name → the lock's entry, for each of the project's own packages."""
    try:
        with open(os.path.join(project, 'composer.lock'), encoding='utf-8') as f:
            lock = json.load(f)
    except (OSError, ValueError) as error:
        fail(f"can't read composer.lock in {project}: {error}")
    return {package['name']: package for package in lock.get('packages', []) + lock.get('packages-dev', [])
            if package['name'].startswith(VENDOR)}


def packagist_reference(name: str, branch: str):
    """The commit Packagist serves for that branch of the package; None when it can't be told."""
    if not re.fullmatch(r'turbolabit/[a-z0-9][a-z0-9._-]*', name):
        fail(f"«{name}» isn't one of the project's packages")
    try:
        with urllib.request.urlopen(PACKAGIST.format(name=name), timeout=20) as answer:
            versions = json.load(answer)['packages'][name]
    except (OSError, ValueError, KeyError):
        return None
    for version in versions:
        if version.get('version') == branch and isinstance(version.get('source'), dict):
            return version['source'].get('reference')
    return None


def working_copy(project: str, name: str):
    """(folder, HEAD, uncommitted files, commits not pushed) of the package's working copy; None off a dev box."""
    link = os.path.join(project, 'vendor', name)
    if not os.path.islink(link):
        return None
    folder = os.path.realpath(link)
    if not os.path.isdir(os.path.join(folder, '.git')):
        return None
    head = run('git', '-C', folder, 'rev-parse', 'HEAD')
    dirty = len([line for line in run('git', '-C', folder, 'status', '--porcelain').splitlines() if line.strip()])
    code, ahead, _ = run('git', '-C', folder, 'rev-list', '--count', '@{upstream}..HEAD', check=False)
    return folder, head, dirty, int(ahead) if code == 0 and ahead.isdigit() else None


def full_name(name: str) -> str:
    return name if name.startswith(VENDOR) else VENDOR + name


def cmd_status(args) -> None:
    pending = 0
    for name, entry in sorted(locked(args.project).items()):
        lock_ref = entry['source']['reference']
        served = packagist_reference(name, entry['version'])
        copy = working_copy(args.project, name)
        print(f"{name}\n  lock       {lock_ref[:7]}  ({entry.get('time', '')[:10]})")
        print(f"  Packagist  {served[:7] if served else 'unknown: no answer'}")
        todo = None
        if copy:
            folder, head, dirty, ahead = copy
            print(f"  here       {head[:7]}  {folder}" + (f", {dirty} uncommitted file(s)" if dirty else '')
                  + (f", {ahead} commit(s) not pushed" if ahead else ''))
            if dirty:
                todo = "uncommitted work in the package: commit it there, with its own tests, before anything else"
            elif ahead:
                todo = "push the package: nothing reaches Packagist before that"
            elif served and head != served:
                todo = "pushed, but Packagist doesn't serve it yet: wait a few seconds, then status again"
        if not todo and served and served != lock_ref:
            todo = f"bump the lock: packages.py bump {name[len(VENDOR):]}"
        print(f"  → {todo or 'in step: nothing to do'}")
        pending += bool(todo)
    print(f"\n{pending} package(s) need attention" if pending
          else "\nall the project's packages are in step with the lock")


def cmd_bump(args) -> None:
    before = locked(args.project)
    names = [full_name(name) for name in args.names]
    for name in names:
        if name not in before:
            fail(f"{name} isn't in composer.lock. The project's packages: {', '.join(sorted(before))}", 2)
        served = packagist_reference(name, before[name]['version'])
        copy = working_copy(args.project, name)
        if copy and (copy[2] or copy[3]):
            fail(f"{name}: its working copy has work that isn't committed or pushed. Ship the package first")
        if copy and served and copy[1] != served:
            fail(f"{name}: Packagist serves {served[:7]}, the working copy is at {copy[1][:7]}. Packagist is a few "
                 "seconds behind a push: wait, then try again")
        if served == before[name]['source']['reference']:
            fail(f"{name}: the lock already names {served[:7]}, the commit Packagist serves. Nothing to bump")
    run('composer', 'update', *names, *LOCK_ONLY, cwd=args.project)

    after, unexpected = locked(args.project), []
    for name in sorted(after):
        old, new = before.get(name, {}).get('source', {}).get('reference', ''), after[name]['source']['reference']
        if old != new:
            print(f"{name}: {old[:7] or 'new'} → {new[:7]}")
            if name not in names:
                unexpected.append(name)
    if unexpected:
        print(f"NOTE: also moved, because the packages asked for require them: {', '.join(unexpected)}")
    print("composer.lock is updated and nothing else: vendor/ still holds what it held. Now run the tests that touch "
          "the change, then commit composer.lock in the same commit as the project code that needs the new package.")


def cmd_prod_test(args) -> None:
    box = tempfile.mkdtemp(prefix='tli-prod-test.')
    try:
        # the last commit, then the paths about to be committed on top of it: never the working tree as a whole,
        # which may hold other people's unfinished work
        archive = subprocess.run(['git', '-C', args.project, 'archive', 'HEAD'], capture_output=True, check=True).stdout
        subprocess.run(['tar', '-x', '-C', box], input=archive, check=True)
        for path in args.paths:
            source = os.path.join(args.project, path)
            relative = os.path.relpath(os.path.abspath(source), args.project)
            if relative.startswith('..') or not os.path.isfile(source):
                fail(f"{path}: name files of the project (the ones the commit will hold)", 2)
            os.makedirs(os.path.dirname(os.path.join(box, relative)), exist_ok=True)
            shutil.copy2(source, os.path.join(box, relative))
        print(f"installing in {box} what production would install…", flush=True)
        run('composer', 'install', '--no-dev', '--no-scripts', '--no-interaction', '--no-progress', cwd=box,
            timeout=1200)
        run('composer', 'dump-autoload', '--classmap-authoritative', '--no-dev', '--no-scripts', cwd=box)
        loader = ("require 'vendor/autoload.php'; $n = 0;"
                  "foreach (require 'vendor/composer/autoload_classmap.php' as $class => $file) {"
                  "  if (str_starts_with($class, 'App\\\\') || str_starts_with($class, 'TurboLabIt\\\\')) {"
                  "    class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);"
                  "    $n++; } }"
                  "echo $n;")
        code, loaded, error = run('php', '-d', 'display_errors=stderr', '-r', loader, cwd=box, check=False)
        if code != 0 or not loaded.isdigit():
            # PHP's own sentence, not the stack trace a debugger adds after it
            fatal = re.search(r'(?:Fatal error|Parse error|Uncaught [\w\\]+)[^\n]*', error + '\n' + loaded)
            fail("a class doesn't load with the packages production would get: "
                 + (fatal.group(0)[:500] if fatal else (error or loaded)[-500:]))
        links = [name for name in os.listdir(os.path.join(box, 'vendor', 'turbolabit'))
                 if os.path.islink(os.path.join(box, 'vendor', 'turbolabit', name))]
        print(f"OK: installed from the lock without the dev packages, {loaded} classes of the project and of its own "
              f"packages loaded, {'no' if not links else len(links)} link(s) in vendor/turbolabit")
    finally:
        shutil.rmtree(box, ignore_errors=True)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--project', default=PROJECT_DIR, help=argparse.SUPPRESS)
    sub = parser.add_subparsers(dest='command', required=True)
    sub.add_parser('status').set_defaults(run=cmd_status)
    bump = sub.add_parser('bump')
    bump.add_argument('names', nargs='+', metavar='NAME', help='php-encryptor, or turbolabit/php-encryptor')
    bump.set_defaults(run=cmd_bump)
    test = sub.add_parser('prod-test')
    test.add_argument('paths', nargs='*', metavar='PATH', help='files to lay over the last commit (composer.lock…)')
    test.set_defaults(run=cmd_prod_test)
    args = parser.parse_args()
    args.project = os.path.abspath(args.project)
    args.run(args)


if __name__ == '__main__':
    main()
