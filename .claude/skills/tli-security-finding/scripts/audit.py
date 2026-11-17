"""
Where the security audit stands, read from the local audit file. Run with `python3 -I`, from anywhere:

  audit.py status     how many findings are open, postponed, accepted, resolved; the open ones; the next free
                      number; and the resolved ones that no test in tests/Security guards
  audit.py next       only the number the next finding takes

docs/security-audit.md is local to the box on purpose: it is never committed, and a checkout that doesn't have it
gets a message saying so. This script reads it and prints numbers, severities and statuses; it changes nothing.
"""
import glob, os, re, sys

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))
AUDIT = os.path.join(PROJECT_DIR, 'docs', 'security-audit.md')
TESTS = os.path.join(PROJECT_DIR, 'tests', 'Security')


def fail(message: str, code: int = 1) -> None:
    print(f"FAILED: {message}", file=sys.stderr)
    sys.exit(code)


def findings() -> dict:
    """number → (status, severity, the card's text), from the cards `### N. severity — title status`."""
    try:
        with open(AUDIT, encoding='utf-8') as f:
            text = f.read()
    except FileNotFoundError:
        fail("no docs/security-audit.md in this checkout. The audit file is kept local by whoever maintains the "
             "site: ask them before recording or re-raising anything")
    found = {}
    cards = re.split(r'^### (?=\d+\. )', text, flags=re.M)[1:]
    for card in cards:
        heading, _, body = card.partition('\n')
        number = int(heading.split('.', 1)[0])
        if 'risolto' in heading or '✅' in heading:
            status = 'resolved'
        elif 'wontfix' in heading or 'accettato' in heading:
            status = 'accepted'
        elif 'postponed' in heading or 'posticipato' in heading:
            status = 'postponed'
        else:
            status = 'open'
        severity = heading.split('.', 1)[1].split('—')[0].strip()
        found[number] = (status, severity, body)
    if not found:
        fail("docs/security-audit.md has no finding card (### N. …): has its layout changed?")
    return found


def guarded(number: int, body: str, tests: dict) -> bool:
    """A test names the finding, or the finding's card names a test that exists."""
    named = re.findall(r'tests/Security/(\w+Test\.php)', body)
    if any(name in tests for name in named):
        return True
    reference = re.compile(r'(?:finding|security-audit(?:\.md)?)\s*(?:finding\s*)?#?%d\b' % number, re.I)
    return any(reference.search(source) for source in tests.values())


def main() -> None:
    command = sys.argv[1] if len(sys.argv) > 1 else ''
    if command not in ('status', 'next'):
        fail("usage: audit.py status | next", 2)
    found = findings()
    following = max(found) + 1
    if command == 'next':
        print(following)
        return

    counts = {status: sorted(n for n, entry in found.items() if entry[0] == status)
              for status in ('open', 'postponed', 'accepted', 'resolved')}
    print(f"{len(found)} findings recorded: {len(counts['open'])} open, {len(counts['postponed'])} postponed, "
          f"{len(counts['accepted'])} accepted, {len(counts['resolved'])} resolved. The next one is #{following}.")
    for number in counts['open']:
        print(f"  open: #{number} ({found[number][1]})")
    for status, word in (('postponed', 'postponed, with its reason in the file'), ('accepted', 'accepted, by decision')):
        if counts[status]:
            print(f"  {word}: " + ', '.join(f"#{number}" for number in counts[status])
                  + ". Not to be raised again without something new.")

    tests = {}
    for path in glob.glob(os.path.join(TESTS, '*.php')):
        with open(path, encoding='utf-8', errors='replace') as f:
            tests[os.path.basename(path)] = f.read()
    bare = [number for number in counts['resolved'] if not guarded(number, found[number][2], tests)]
    print(f"{len(tests)} test files in tests/Security.")
    if bare:
        print("  resolved, but no test names them and their card names none: "
              + ', '.join(f"#{number}" for number in bare) + ". Read the card: some fixes are configuration, "
              "or are guarded by a test elsewhere")
    else:
        print("  every resolved finding has a test that names it, or names one in its card")


if __name__ == '__main__':
    main()
