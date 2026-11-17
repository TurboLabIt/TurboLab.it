"""
Commits exactly the paths it is given, as they are on disk, and pushes: the way this project commits. Nothing else
from the working tree gets in, whatever other sessions have changed or staged, and the added lines are scanned for
secrets first. Run with `python3 -I`:

  commit.py --message-file M PATH [PATH…]   commit those files (or what changed under those folders), then push
  commit.py --scan PATH [PATH…]             only show what would be committed and what the scan finds

  --no-push            commit, don't push
  --accept-findings    commit although the scan found something: only after reading every finding

How: the commit is built in a private index from HEAD plus those paths, and the branch moves to it only if nobody
committed in the meantime. The shared index is touched for those paths only, after the commit, so that `git status`
stays true. Ignored files are refused: what .gitignore keeps local stays local.

Exit codes: 0 done, 1 failed, 2 usage, 3 the scan found something (nothing was committed).
"""
import argparse, math, os, re, subprocess, sys, tempfile, time

REMOTE, BRANCH = 'origin', 'main'
LOCK_FILES = ('composer.lock', 'yarn.lock', 'package-lock.json', 'symfony.lock')

# (kind, pattern). The value to hide in the report is the first group, or the whole match
SECRETS = [
    ('private key', re.compile(r'-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----')),
    ('GitHub token', re.compile(r'\b(gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})')),
    ('AWS key', re.compile(r'\b((?:AKIA|ASIA)[0-9A-Z]{16})\b')),
    ('Google API key', re.compile(r'\b(AIza[0-9A-Za-z_-]{35})\b')),
    ('Slack token', re.compile(r'\b(xox[abeprs]-[A-Za-z0-9-]{10,})')),
    ('API key', re.compile(r'\b(sk-[A-Za-z0-9_-]{20,}|sk_live_[A-Za-z0-9]{20,}|rk_live_[A-Za-z0-9]{20,})')),
    ('Telegram bot token', re.compile(r'\b(\d{8,10}:[A-Za-z0-9_-]{35})\b')),
    ('JSON Web Token', re.compile(r'\b(eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,})')),
    ('service account key', re.compile(
        r'"(?:private_key|private_key_id|client_secret|refresh_token)"\s*:\s*"([^"]{8,})"')),
    ('credentials in a URL', re.compile(r'[a-z][a-z0-9+.-]*://[^/\s:@\'"]+:([^/\s:@\'"]{3,})@')),
]
# looked for everywhere but in lock files, which are full of hashes, addresses and authors' e-mails
REVIEW = [
    ('value assigned to a secret-looking name', re.compile(
        r'''(?i)(?:passw(?:or)?d|passwd|secret|token|api[_-]?key|auth[_-]?key|private[_-]?key)["'\]\w]*'''
        r'''\s*(?:=>|[:=])\s*["']([^"'\s]{8,})["']''')),
    ('long random-looking string', re.compile(
        r'(?<![A-Za-z0-9+/_-])([A-Za-z0-9+/_-]{40,}={0,2})(?![A-Za-z0-9+/_=-])')),
    ('IP address', re.compile(r'(?<![\w.])((?:\d{1,3}\.){3}\d{1,3})(?![\w.])')),
    ('e-mail address', re.compile(r'\b([A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+)\b')),
]
PLACEHOLDER = re.compile(r'(?i)^(?:x+|\*+|\.+|…+|changeme|change_me|password|secret|example|placeholder|null|none'
                         r'|true|false|your[-_].*|<.*>|\{.*\}|%.*%|\$.*)$')
PUBLIC_IP = re.compile(r'^(?:127\.|0\.0\.0\.0$|255\.|192\.0\.2\.|198\.51\.100\.|203\.0\.113\.)')
PUBLIC_MAIL = re.compile(r'(?i)@(?:example\.(?:com|org|net)|(?:.*\.)?invalid|anthropic\.com'
                         r'|users\.noreply\.github\.com)$')


def fail(message: str, code: int = 1) -> None:
    print(f"FAILED: {message}", file=sys.stderr)
    sys.exit(code)


# control characters, the Unicode line separators and the marks that reorder text on screen. The last two kinds
# are built from their code points: written as they are, nobody could see them in this file
UNPRINTABLE = re.compile(r'[\x00-\x1f\x7f-\x9f' + ''.join(
    chr(code) for code in (0x2028, 0x2029, *range(0x202a, 0x202f), *range(0x2066, 0x206a))) + ']')


def shown(text) -> str:
    """A name or a line of a file, safe to print: nothing in it drives the terminal."""
    return UNPRINTABLE.sub('?', str(text))


def git(*args, env=None, check=True, binary=False, timeout=120):
    """git's output; with check=False, (exit code, output, error) instead of failing."""
    result = subprocess.run(['git', *args], capture_output=True, env=env, timeout=timeout)
    error = result.stderr.decode('utf-8', 'replace').strip()
    if check and result.returncode != 0:
        fail(f"git {' '.join(args[:3])}…: {shown(error)[:500]}")
    if binary:
        return result.stdout
    out = result.stdout.decode('utf-8', 'replace').strip()
    return out if check else (result.returncode, out, error)


def changed(path: str) -> list:
    """What differs from the last commit at that path or under it, staged or not: changed, new, deleted, renamed."""
    names, entries = [], git('status', '--porcelain=v1', '-z', '--untracked-files=all', '--', path, binary=True)
    entries = entries.decode('utf-8', 'surrogateescape').split('\0')
    while entries:
        entry = entries.pop(0)
        if len(entry) < 4:
            continue
        names.append(entry[3:])
        if entry[0] in 'RC' or entry[1] in 'RC':
            names.append(entries.pop(0))        # a rename or a copy also names where it came from
    return names


def repo_paths(given: list, start: str, root: str) -> list:
    """The files to commit, relative to the repository: the given files, and what changed under the given folders."""
    files = []
    for item in given:
        path = os.path.relpath(os.path.abspath(os.path.join(start, item)), root)
        if path == '.' or path.startswith('..'):
            fail(f"{shown(item)}: name the files or the folders to commit, inside the repository "
                 "(never the whole tree)", 2)
        if re.search(r'[\x00-\x1f\x7f]', path):
            fail(f"{shown(path)}: a control character in a file name", 2)
        found = changed(path)
        if not found:
            code, _, _ = git('check-ignore', '-q', '--', path, check=False)
            fail(f"{shown(path)} " + ("is ignored by .gitignore: it stays local" if code == 0 else
                                      "has nothing to commit: no change there, or no such file"))
        files += found
    files = sorted(set(files))
    for path in files:
        if os.path.islink(path):
            fail(f"{shown(path)} is a symbolic link: commit it by hand, after checking where it points")
    return files


def build_tree(files: list, index: str) -> str:
    """The tree of HEAD with those files as they are on disk, built in an index of its own."""
    env = dict(os.environ, GIT_INDEX_FILE=index)
    git('read-tree', 'HEAD', env=env)
    for path in files:
        if not os.path.exists(path):
            git('update-index', '--force-remove', '--', path, env=env)
            continue
        tracked = git('ls-tree', 'HEAD', '--', path).split()
        mode = tracked[0] if tracked else ('100755' if os.stat(path).st_mode & 0o100 else '100644')
        blob = git('hash-object', '-w', f'--path={path}', '--', path)
        git('update-index', '--add', '--cacheinfo', f'{mode},{blob},{path}', env=env)
    return git('write-tree', env=env)


def sync_index(files: list, commit: str) -> None:
    """The shared index, for these paths only: afterwards it says what the new commit says."""
    for path in files:
        entry = git('ls-tree', commit, '--', path).split()
        command = (['update-index', '--add', '--cacheinfo', f'{entry[0]},{entry[2]},{path}'] if entry
                   else ['update-index', '--force-remove', '--', path])
        for _ in range(6):
            code, _, error = git(*command, check=False)
            if code == 0:
                break
            time.sleep(0.5)     # another session holds the index lock for a moment
        else:
            print(f"WARNING: the commit is made, but the index entry of {shown(path)} wasn't updated "
                  f"({shown(error)[:120]}): run `git reset -q -- {shown(path)}`", file=sys.stderr)


def hide(value: str) -> str:
    return f"{value[:4]}… ({len(value)} characters)"


def looks_random(value: str) -> bool:
    """A hash or a key rather than a long path or a long name: hexadecimal, or as varied as random text is."""
    value = value.rstrip('=')
    if re.fullmatch(r'[A-Fa-f0-9]+', value):
        return True
    if not re.search(r'\d', value):
        return False
    shares = [value.count(char) / len(value) for char in set(value)]
    return -sum(share * math.log2(share) for share in shares) >= 4.7


def meant_to_be_public(kind: str, value: str) -> bool:
    if kind == 'IP address':
        return bool(PUBLIC_IP.match(value)) or any(int(part) > 255 for part in value.split('.'))
    if kind == 'e-mail address':
        return bool(PUBLIC_MAIL.search(value))
    if kind.startswith('value assigned'):
        return bool(PLACEHOLDER.match(value))
    return not looks_random(value)


def scan(base: str, tree: str) -> list:
    """What the added lines hold that may not belong in a public repository: (file, line, kind, what)."""
    findings, path, line_number = [], None, 0
    diff = git('diff', '-U0', '--no-color', '--no-ext-diff', base, tree, binary=True).decode('utf-8', 'replace')
    for line in diff.split('\n'):
        if line.startswith('+++ '):
            path = line[6:] if line.startswith('+++ b/') else None
        elif line.startswith('@@'):
            match = re.match(r'@@ -\S+ \+(\d+)', line)
            line_number = int(match.group(1)) if match else 0
        elif line.startswith('Binary files ') and line.endswith(' differ'):
            findings.append((line.split(' and ')[-1][2:-7], 0, 'binary file', 'not readable: check what it is'))
        elif line.startswith('+') and not line.startswith('+++') and path:
            text = line[1:]
            for kind, pattern in SECRETS:
                findings += [(path, line_number, kind, hide(match.group(match.lastindex or 0)))
                             for match in pattern.finditer(text)]
            if os.path.basename(path) not in LOCK_FILES:
                for kind, pattern in REVIEW:
                    for value in (match.group(1) for match in pattern.finditer(text)):
                        if not meant_to_be_public(kind, value):
                            plain = kind in ('IP address', 'e-mail address')
                            findings.append((path, line_number, kind, value if plain else hide(value)))
            line_number += 1
    return findings


def catch_up(scan_only: bool) -> None:
    """Where this checkout stands against the remote, before committing on top of it."""
    if scan_only:
        return
    git('fetch', '--quiet', REMOTE, BRANCH)
    counts = git('rev-list', '--left-right', '--count', f'HEAD...{REMOTE}/{BRANCH}').split()
    ahead, behind = int(counts[0]), int(counts[1])
    if behind and ahead:
        fail(f"{BRANCH} and {REMOTE}/{BRANCH} have diverged ({ahead} commits here, {behind} there): that takes a "
             "rebase on a clean tree. Stop and tell the user: never stash someone else's work to get one")
    if behind:
        code, _, error = git('merge', '--ff-only', '--quiet', f'{REMOTE}/{BRANCH}', check=False)
        if code != 0:
            fail(f"{REMOTE}/{BRANCH} is {behind} commits ahead and can't be fast-forwarded over the local "
                 f"changes: {shown(error)[:300]}")
        print(f"fast-forwarded to {REMOTE}/{BRANCH} ({behind} new commits) before committing")
    if ahead:
        print(f"NOTE: {ahead} local commit(s) not pushed yet (someone else's, or an earlier --no-push): "
              "the push will send them too")


def push() -> None:
    code, _, error = git('push', REMOTE, BRANCH, check=False, timeout=300)
    if code != 0:
        fail(f"committed, but the push was refused: {shown(error)[:400]}. Don't force it: fetch, look, "
             "and tell the user")
    git('fetch', '--quiet', REMOTE, BRANCH)
    left = git('rev-list', '--left-right', '--count', f'HEAD...{REMOTE}/{BRANCH}').split()
    print(f"pushed to {REMOTE}/{BRANCH}" + ('' if left == ['0', '0'] else f", but they still differ: {left}"))


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('paths', nargs='+', metavar='PATH')
    what = parser.add_mutually_exclusive_group(required=True)
    what.add_argument('--message-file', help='the commit message, in a file')
    what.add_argument('--scan', action='store_true', help='show what would be committed and the scan, commit nothing')
    parser.add_argument('--no-push', action='store_true')
    parser.add_argument('--accept-findings', action='store_true', help='commit although the scan found something')
    args = parser.parse_args()

    start = os.getcwd()
    root = git('rev-parse', '--show-toplevel')
    os.chdir(root)
    if git('symbolic-ref', '--short', 'HEAD') != BRANCH:
        fail(f"this checkout isn't on {BRANCH}: the project commits straight to {BRANCH}")
    files = repo_paths(args.paths, start, root)

    message = None
    if not args.scan:
        with open(os.path.join(start, args.message_file), encoding='utf-8') as f:
            message = f.read().strip()
        if not message:
            fail(f"{args.message_file} is empty", 2)
    catch_up(args.scan)

    work = tempfile.mkdtemp(prefix='tli-commit.')
    try:
        base = git('rev-parse', 'HEAD')
        tree = build_tree(files, os.path.join(work, 'index'))
        if tree == git('rev-parse', 'HEAD^{tree}'):
            fail("nothing to commit: those paths are the same as in the last commit")

        print('\n'.join(shown(line) for line in git('diff', '--stat', '--no-color', base, tree).split('\n')))
        findings = scan(base, tree)
        for path, line_number, kind, value in findings:
            print(f"FINDING {shown(path)}:{line_number}: {kind}: {shown(value)}")
        if not findings:
            print("scan: nothing that looks like a secret, an address or a key in the added lines")
        if args.scan:
            sys.exit(3 if findings else 0)
        if findings and not args.accept_findings:
            fail(f"{len(findings)} finding(s): nothing committed. Read each one; if every one is meant to be "
                 "public, run again with --accept-findings and say why in your report", 3)

        message_file = os.path.join(work, 'message')
        with open(message_file, 'w', encoding='utf-8') as f:
            f.write(message + '\n')
        commit = git('commit-tree', tree, '-p', base, '-F', message_file)
        code, _, error = git('update-ref', '-m', f"commit: {message.splitlines()[0][:70]}", f'refs/heads/{BRANCH}',
                             commit, base, check=False)
        if code != 0:
            fail("someone else committed in the meantime, so nothing was committed: run it again "
                 f"({shown(error)[:200]})")
        sync_index(files, commit)
        print(f"committed {commit[:7]}: {len(files)} file(s)")
    finally:
        for name in os.listdir(work):
            os.remove(os.path.join(work, name))
        os.rmdir(work)

    if args.no_push:
        print("not pushed (--no-push)")
    else:
        push()


if __name__ == '__main__':
    main()
