"""
The few frontend rules of the project that a script can check. Run with `python3 -I`, from anywhere:

  check-frontend.py

It reads files of the working tree only. Exit code 1 when a rule is broken. The other rules (weights, colours,
what a comment is for) need eyes: the skill has them.
"""
import glob, json, os, re, sys

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', '..'))
broken = 0


def read(path: str) -> str:
    try:
        with open(os.path.join(PROJECT_DIR, path), encoding='utf-8') as f:
            return f.read()
    except OSError:
        return ''


def rule(ok: bool, text: str, detail: str = '') -> None:
    global broken
    broken += not ok
    print(('ok      ' if ok else 'BROKEN  ') + text + ('' if ok or not detail else f": {detail}"))


# 1. plain CSS only: no preprocessor sources (the purchased theme's archive in assets/themes/ is kept as it came)
sources = [os.path.relpath(path, PROJECT_DIR) for extension in ('scss', 'sass', 'less', 'styl')
           for path in glob.glob(os.path.join(PROJECT_DIR, 'assets', '**', f'*.{extension}'), recursive=True)]
sources = [path for path in sources if not path.startswith('assets/themes/')]
rule(not sources, "plain CSS only, no preprocessor", ', '.join(sources[:5]))

# 2. jQuery is the global loaded by the base template, never a package of ours
package = json.loads(read('package.json') or '{}')
packaged = [name for section in ('dependencies', 'devDependencies') for name in package.get(section, {}) if name == 'jquery']
rule(not packaged and 'jquery' in read('templates/base.html.twig') and "jquery: 'jQuery'" in read('webpack.config.js'),
     "jQuery is the page's global (base template + webpack externals), not a package",
     "package.json lists jquery" if packaged else "the base template or the externals no longer name it")

# 3. our bundles are deferred: the theme's own main.js runs before them and can't depend on them
rule(bool(re.search(r'^\s*defer:\s*true', read('config/packages/webpack_encore.yaml'), re.M)),
     "Encore bundles are deferred", "config/packages/webpack_encore.yaml no longer says defer: true")

# 4. e-mail clients don't show AVIF or WebP: an image in an e-mail comes from a ...MetaUrl getter (PNG)
offenders = []
templates = glob.glob(os.path.join(PROJECT_DIR, 'templates', 'email', '**', '*.twig'), recursive=True)
templates.append(os.path.join(PROJECT_DIR, 'templates', 'newsletter', 'email.html.twig'))
for path in templates:
    for number, line in enumerate(read(os.path.relpath(path, PROJECT_DIR)).split('\n'), 1):
        for call in re.findall(r'[\w.]*(?:[Ss]potlight|[Ii]mage)\w*Url\b', line):
            if 'Meta' not in call:
                offenders.append(f"{os.path.relpath(path, PROJECT_DIR)}:{number} {call}")
        if re.search(r'<img[^>]+src="[^"{]*\.(?:avif|webp)"', line):
            offenders.append(f"{os.path.relpath(path, PROJECT_DIR)}:{number} an AVIF or WebP file")
rule(not offenders, f"images in the {len(templates)} e-mail templates are PNG (the ...MetaUrl getters)", '; '.join(offenders[:5]))

print(f"\n{broken} rule(s) broken" if broken else "\nthe checkable rules hold")
sys.exit(1 if broken else 0)
