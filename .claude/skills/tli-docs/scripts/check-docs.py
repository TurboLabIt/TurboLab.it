"""
Checks the project's docs/*.md against what they point to. Run with `python3 -I`, from anywhere:

  check-docs.py             every doc
  check-docs.py FILE…       only those

It looks for the two things that rot without anyone noticing: a link to a file of the repository that no longer
exists at that path, and a title that doesn't link to the document itself the way the others do. It reads files of
the working tree only: no network. Exit code 1 when something is found.
"""
import glob, os, re, sys

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))
REPOSITORY = 'https://github.com/TurboLabIt/TurboLab.it/'
LINK = re.compile(re.escape(REPOSITORY) + r'(?:blob|tree)/main/([^\s)\]>"\'#?]+)')


def check(path: str) -> list:
    name = os.path.relpath(path, PROJECT_DIR)
    with open(path, encoding='utf-8') as f:
        lines = f.read().split('\n')
    problems = []

    title = re.fullmatch(r'# \[(.+)\]\((\S+)\)', lines[0].strip())
    if not title:
        problems.append((1, "the first line isn't `# [Title](link to this document on GitHub)`"))
    elif title.group(2) != f"{REPOSITORY}blob/main/{name}":
        problems.append((1, f"the title links to {title.group(2)}, not to this document"))

    for number, line in enumerate(lines, 1):
        for target in LINK.findall(line):
            target = target.rstrip('.,;:')
            # a link may name a file that only exists where it is used (local or generated): say which, don't guess
            if not os.path.exists(os.path.join(PROJECT_DIR, target)):
                problems.append((number, f"links to {target}, which isn't in the repository"))
    return problems


def main() -> None:
    files = [os.path.abspath(arg) for arg in sys.argv[1:]] or sorted(glob.glob(os.path.join(PROJECT_DIR, 'docs', '*.md')))
    total = 0
    for path in files:
        if not os.path.isfile(path):
            print(f"FAILED: no such file: {path}", file=sys.stderr)
            sys.exit(2)
        for number, problem in check(path):
            total += 1
            print(f"{os.path.relpath(path, PROJECT_DIR)}:{number}: {problem}")
    print(f"{len(files)} document(s) checked: " + (f"{total} problem(s)" if total else "titles and links are in order"))
    sys.exit(1 if total else 0)


if __name__ == '__main__':
    main()
