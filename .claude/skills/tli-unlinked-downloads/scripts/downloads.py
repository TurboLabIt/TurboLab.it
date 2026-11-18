"""
The articles under "File da controllare:" on /scarica/da-controllare, for the tli-unlinked-downloads skill: each has
Downloads attached that its text never links. Logged in with the ADMINISTRATOR account (`forum.py login --account
admin`, in the tli-issue skill) or, on a dev copy, with a dev-session.php session (tli-logged-in-check skill). Plain
HTTPS, saving the way the editor saves. Run with `python3 -I`:

  downloads.py list <state> [--out FILE]          the articles under "File da controllare:" and their Downloads
  downloads.py show <state> --article N [--all] [--full] [--skip-hidden-check] [--list-from STATE]
                                                  the article as the editor gets it, block by block, with the
                                                  places that mention its Downloads and the hidden-text check
  downloads.py apply <state> --plan FILE [--article N] [--dry-run] [--skip-hidden-check] [--list-from STATE]
                                                  applies a plan (format in SKILL.md): checks it, saves, verifies

Only the articles in that list can be in a plan, and only with their listed Downloads. The hidden-text check reads
this checkout's database through stored-body.php (dev copies only): the editor gets the body as the page shows it,
and a save stores exactly that, so any stored text the page doesn't show would be deleted for good.
Exit codes: 0 OK, 1 failed, 2 usage.
"""
import argparse, difflib, html, json, os, re, subprocess, sys, time, urllib.parse
from html.parser import HTMLParser

HERE = os.path.dirname(os.path.realpath(__file__))
# the session and its safeguards are forum.py's, shared with tli-issue, tli-issue-close and tli-browser-test
sys.path.insert(0, os.path.join(HERE, '..', '..', 'tli-issue', 'scripts'))
import forum  # noqa: E402
from forum import fail, one_line, quoted  # noqa: E402

LIST_PATH = '/scarica/da-controllare'
STORED_BODY = os.path.join(HERE, 'stored-body.php')
# exactly what the editor's "Usa come riga » Download:" inserts (assets/js/ckeditor-plugins/link-file.js)
DOWNLOAD_LINE = '<p><strong>» Download:</strong> <a href="{url}">{title}</a></p>'
VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}
# tags that sit inside a word's run of text: removing them must not split or join words
INLINE = {'a', 'abbr', 'b', 'code', 'em', 'i', 'ins', 'mark', 's', 'small', 'span', 'strong', 'sub', 'sup', 'u'}
MENTION = re.compile(r'allegat|scaric|download', re.I)


# ---------------------------------------------------------------------------------------------------- text

def text_of(markup: str) -> str:
    """The text a reader sees in some markup, on one line, with nothing in it that drives a terminal."""
    def tag(match):
        name = re.match(r'</?\s*([a-zA-Z0-9]+)', match.group(0))
        return '' if name and name.group(1).lower() in INLINE else ' '
    text = html.unescape(re.sub(r'<[^>]*>', tag, markup))
    return re.sub(f'[{forum.UNSAFE}]', '', re.sub(r'\s+', ' ', text)).strip()


def readable(markup: str) -> str:
    """The same with its links as [text](target), and its images and videos named: what `show` prints."""
    markup = re.sub(r'<img\b[^>]*>', ' [immagine] ', markup)
    markup = re.sub(r'<iframe\b[^>]*>.*?</iframe>', ' [video] ', markup, flags=re.S)
    markup = re.sub(r'<a\b[^>]*?\bhref="([^"]*)"[^>]*>(.*?)</a>', r'[\2](\1)', markup, flags=re.S)
    return text_of(markup)


def reader_words(markup: str) -> list:
    """A body's words as a reader gets them. Link targets and image sources don't count: the database stores them as
    placeholders, and the page as addresses. A stored YouTube placeholder is a video on the page, not text."""
    return text_of(re.sub(r'==###youtube::code::[^#\s]*###==', ' ', markup)).split()


def word_changes(old: list, new: list) -> tuple:
    """(runs only the first has, runs only the second has, pairs that one replaced with the other), as text."""
    lost, gained, replaced = [], [], []
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, old, new, autojunk=False).get_opcodes():
        if tag == 'delete':
            lost.append(' '.join(old[i1:i2]))
        elif tag == 'insert':
            gained.append(' '.join(new[j1:j2]))
        elif tag == 'replace':
            replaced.append((' '.join(old[i1:i2]), ' '.join(new[j1:j2])))
    return lost, gained, replaced


def clip(text: str, length: int) -> str:
    return text if len(text) <= length else text[:length] + ' […]'


def only_adds(old_markup: str, new_markup: str) -> bool:
    """Is every character the old text has still in the new one, in the same order? The operations only insert text,
    wrap it in a link or change a link's target: anything else would be a bug."""
    remaining = iter(re.sub(r'\s+', '', text_of(new_markup)))
    return all(char in remaining for char in re.sub(r'\s+', '', text_of(old_markup)))


# ---------------------------------------------------------------------------------------------------- the list

def download_url_ok(site, url: str, file_id: str) -> bool:
    parts = urllib.parse.urlsplit(url)
    return (parts.scheme == 'https' and parts.hostname == site.host and parts.path == f'/scarica/{file_id}'
            and not parts.query and not parts.fragment)


def fetch_list(site) -> dict:
    """The "File da controllare:" section of /scarica/da-controllare, and only that one: the page's other lists
    (orphans, files missing on disk or in the database) are not this skill's."""
    status, _, page, _ = site.get(LIST_PATH)
    if status != 200:
        fail(f"{LIST_PATH} answered HTTP {status}")
    heading = re.search(r'<h2 class="title">File da controllare: ([\d.]+)</h2>', page)
    if not heading and re.search(r'<li>\s*Zero "File da controllare"', page):
        # an empty list has no section: the page names it among the checks that found nothing, without a date
        return {'site': site.base, 'generated': None, 'articles': {}}
    if not heading:
        fail(f"no «File da controllare:» section on {LIST_PATH}: the list is for logged-in users, check `whoami`")
    end = page.find('<h2 class="title">', heading.end())
    section = page[heading.end():end if end > 0 else len(page)]
    generated = re.search(r'è stata generata (.*?)\. Gli articoli', text_of(section))

    articles = {}
    for article_id, cells in re.findall(r'<tr>\s*<th scope="row">(\d+)</th>(.*?)</tr>', section, re.S):
        tds = re.findall(r'<td>(.*?)</td>', cells, re.S)
        link = re.search(r'<a href="([^"]+)">(.*?)</a>', tds[0], re.S) if len(tds) == 3 else None
        if not link:
            fail(f"row {article_id} of the list isn't article, Downloads, authors: has the page changed?")
        downloads = []
        for href, label, file_id in re.findall(r'<a href="([^"]+)">(.*?) \(#(\d+)\)</a>', tds[1], re.S):
            url = html.unescape(href)
            if not download_url_ok(site, url, file_id):
                fail(f"Download #{file_id} of article {article_id} isn't at {site.base}/scarica/{file_id}: "
                     f"{quoted(url)}")
            downloads.append({'id': int(file_id), 'title': html.unescape(label).strip(), 'url': url})
        if not downloads:
            fail(f"row {article_id} of the list has no Download: has the page changed?")
        articles[int(article_id)] = {'id': int(article_id), 'title': html.unescape(link.group(2)).strip(),
                                     'url': html.unescape(link.group(1)), 'authors': text_of(tds[2]),
                                     'downloads': downloads}

    declared = int(heading.group(1).replace('.', ''))
    if declared != len(articles):
        fail(f"the heading counts {declared} articles and the table has {len(articles)}: has the page changed?")
    return {'site': site.base, 'generated': generated.group(1) if generated else None, 'articles': articles}


def listing_for(site, args) -> dict:
    """The list the work is checked against: the site's own, or, with --list-from, the one another session gets (a dev
    copy, when production's list lags behind: a FilesToArticles fix not deployed yet, a run not come yet). Its Downloads
    then point at this site, where each one must still be attached to its article (checked on the article's page)."""
    if not getattr(args, 'list_from', None):
        return fetch_list(site)
    other = forum.Site.load(args.list_from)
    listing = fetch_list(other)
    for article in listing['articles'].values():
        article['url'] = f"{site.base}/{article['id']}"
        for download in article['downloads']:
            download['url'] = f"{site.base}/scarica/{download['id']}"
    listing['from'] = other.host
    return listing


def listed_article(listing: dict, article_id: int, site) -> dict:
    if article_id not in listing['articles']:
        fail(f"article {article_id} isn't under «File da controllare:» on {listing.get('from') or site.host}: this "
             "skill touches only the articles listed there")
    return listing['articles'][article_id]


def target_downloads(article: Article, item: dict) -> dict:
    """The article's listed Downloads, each as this site has it: its title here, attached or not, linked or not."""
    found = {}
    for download in item['downloads']:
        found[download['id']] = dict(download, title=article.attached.get(download['url'], download['title']),
                                     attached=download['url'] in article.attached, linked=article.links(download['url']))
    return found


# ---------------------------------------------------------------------------------------------------- the editor's copy

class Article:
    """An article as the editor gets it, on the page it is edited on: its title, its body, where to save them."""

    def __init__(self, site, article_id: int):
        status, _, page, path = site.get(f'/{article_id}')
        # a draft, or anything not published, answers 503 and still shows the editor to whoever may edit it
        if status not in (200, 503):
            fail(f"article {article_id} answered HTTP {status}")
        title = re.search(r'<textarea\b[^>]*\bdata-tli-editable-id="title"[^>]*>(.*?)</textarea>', page, re.S)
        save = re.search(r'<article\b[^>]*\bdata-save-url="([^"]+)"', page)
        opening = re.search(r'<div id="tli-article-body"[^>]*>', page)
        if not title or not save or not opening:
            fail(f"article {article_id}: this session can't edit it, the page has no editor. The administrator "
                 "account must be an editor or an administrator: check who it is with forum.py whoami")
        self.id, self.path, self.published = article_id, path, status == 200
        self.title = html.unescape(title.group(1))
        self.save_path = html.unescape(save.group(1))
        if not re.fullmatch(rf'/ajax/editor/article/{article_id}/update/', self.save_path):
            fail(f"article {article_id}: unexpected save address {quoted(self.save_path)}")
        close = page.find('</div>', opening.end())
        body = page[opening.end():close]
        # the allowlist has no <div>: one inside would mean that the copy can't be told apart from the page
        if close < 0 or '<div' in body:
            fail(f"article {article_id}: the editor's copy of the body can't be found on the page")
        self.body = body.strip()
        modified = re.search(r'<meta property="article:modified_time" content="([^"]+)"', page)
        self.modified = modified.group(1) if modified else None
        # the Downloads attached to it on this site, as its list under the text shows them: address → title
        self.attached = {html.unescape(href): html.unescape(title).strip() for href, title in re.findall(
            r'<a href="([^"]+)" target="_blank"\s+class="tli-download-text-link"><span>(.*?)</span></a>', page, re.S)}

    def links(self, url: str) -> bool:
        return f'href="{html.escape(url)}"' in self.body


class Block:
    def __init__(self, markup: str, tag: str, start: int, end: int):
        self.tag, self.start, self.end = tag, start, end
        self.html = markup[start:end]
        self.text = text_of(self.html)


class Blocks(HTMLParser):
    """The top-level nodes of a body (paragraphs, headings, lists…), each with its place in the markup: what a plan
    points at, by quoting its words."""

    def __init__(self, markup: str):
        super().__init__(convert_charrefs=False)
        self.markup, self.depth, self.found, self.start, self.tag = markup, 0, [], None, None
        self.line_starts = [0] + [match.end() for match in re.finditer('\n', markup)]
        self.feed(markup)
        self.close()

    def here(self) -> int:
        line, column = self.getpos()
        return self.line_starts[line - 1] + column

    def handle_starttag(self, tag, attrs):
        if self.depth == 0 and tag in VOID:
            self.found.append(Block(self.markup, tag, self.here(), self.here() + len(self.get_starttag_text())))
            return
        if self.depth == 0:
            self.start, self.tag = self.here(), tag
        if tag not in VOID:
            self.depth += 1

    def handle_startendtag(self, tag, attrs):
        if self.depth == 0:
            self.found.append(Block(self.markup, tag, self.here(), self.here() + len(self.get_starttag_text())))

    def handle_endtag(self, tag):
        if tag in VOID or self.depth == 0:
            return
        self.depth -= 1
        if self.depth == 0:
            self.found.append(Block(self.markup, self.tag, self.start, self.markup.index('>', self.here()) + 1))

    def handle_data(self, data):
        if self.depth == 0 and data.strip():
            start = self.here()
            self.found.append(Block(self.markup, '#text', start, start + len(data)))


def blocks_of(markup: str) -> list:
    return Blocks(markup).found


def find_block(blocks: list, quote, what: str) -> Block:
    wanted = ' '.join(str(quote or '').split())
    hits = [block for block in blocks if wanted and wanted in block.text]
    # a heading whose words are also a "» Leggi:" line's link text: the block that is the whole quote wins
    exact = [block for block in hits if block.text == wanted]
    if len(hits) > 1 and len(exact) == 1:
        hits = exact
    if len(hits) != 1:
        fail(f"{what}: {quoted(wanted)} is in {len(hits)} blocks of the text, and it must point at exactly one: "
             "quote more of its words, as `show` prints them (not the link targets)")
    return hits[0]


def find_words(block: Block, words, what: str) -> tuple:
    """Where some words are in a block, as one run of text outside any link: (start, end) in the body."""
    target = html.escape(str(words or ''), quote=False)
    found, in_link = [], 0
    for piece in re.finditer(r'<[^>]*>|[^<]+', block.html):
        if piece.group(0).startswith('<'):
            name = re.match(r'<(/?)\s*([a-zA-Z0-9]+)', piece.group(0))
            if name and name.group(2).lower() == 'a':
                in_link = max(0, in_link + (-1 if name.group(1) else 1))
            continue
        index = piece.group(0).find(target) if target and not in_link else -1
        while index >= 0:
            found.append(block.start + piece.start() + index)
            index = piece.group(0).find(target, index + 1)
    if len(found) != 1:
        fail(f"{what}: the words {quoted(words)} are in that block {len(found)} times outside links, and they must be "
             "there once, in one run of text: no bold, italics or link inside them")
    return found[0], found[0] + len(target)


# ---------------------------------------------------------------------------------------------------- hidden text

def stored_copy(ids: list):
    """What this checkout's database stores for those articles, through stored-body.php: (data, None) or (None, why)."""
    try:
        done = subprocess.run(['php', STORED_BODY, *[str(i) for i in ids]], capture_output=True, text=True,
                              timeout=300)
    except (OSError, subprocess.TimeoutExpired) as ex:
        return None, f"stored-body.php didn't run: {ex}"
    if done.returncode != 0:
        return None, one_line(done.stderr.strip()[-400:] or f"stored-body.php exited with {done.returncode}")
    try:
        return json.loads(done.stdout), None
    except ValueError:
        return None, "stored-body.php printed no JSON"


def check_hidden(article: Article, copy) -> dict:
    """Does the database store words that the editor's copy lacks? A save would delete them for good.
    state: ok, hidden (with the runs), stale (the copy is older than the article), unknown."""
    row = ((copy or {}).get('articles') or {}).get(str(article.id))
    if not row:
        return {'state': 'unknown', 'why': "the article isn't in this checkout's database"}
    copy_modified = max(date for date in (row.get('updated_at'), row.get('published_at')) if date)
    # both dates are the site's local time: the page's, without its offset, compares with the database's
    page_modified = article.modified[:19].replace('T', ' ') if article.modified else None
    if page_modified and page_modified > copy_modified:
        return {'state': 'stale', 'why': f"the article changed on {page_modified[:16]}, after the last change "
                                         f"this checkout's copy knows of ({copy_modified[:16]}): refresh the copy"}
    if page_modified and page_modified < copy_modified:
        return {'state': 'stale', 'why': f"this checkout's copy of the article changed on {copy_modified[:16]}, after "
                                         f"the site's last change ({page_modified[:16]}): a rehearsal saved it? "
                                         "Refresh the copy"}
    stored, page = reader_words(row['body']), reader_words(article.body)
    lost, gained, replaced = word_changes(stored, page)
    if gained or replaced:
        sample = (gained or [b for _, b in replaced])[0]
        return {'state': 'stale', 'why': f"the page has words this checkout's copy hasn't ({quoted(clip(sample, 80))}"
                                         "): the copy is older than the article, refresh it"}
    return {'state': 'hidden' if lost else 'ok', 'runs': lost, 'stored': stored, 'page': page}


def restore_hints(blocks: list, hidden: dict) -> list:
    """For each hidden run, the restore operation that puts it back where the stored body has it."""
    hints, cumulative = [], []
    total = 0
    for block in blocks:
        total += len(reader_words(block.html))
        cumulative.append(total)
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, hidden['stored'], hidden['page'],
                                                       autojunk=False).get_opcodes():
        if tag != 'delete':
            continue
        text = ' '.join(hidden['stored'][i1:i2])
        following = next((index for index, count in enumerate(cumulative) if count > j1), None)
        if following is None:
            hints.append({'op': 'restore', 'after': ' '.join(blocks[-1].text.split()[:10]) if blocks else '',
                          'text': text})
        else:
            hints.append({'op': 'restore', 'before': ' '.join(blocks[following].text.split()[:10]), 'text': text})
    return hints


# ---------------------------------------------------------------------------------------------------- plans

def load_plan(path: str) -> dict:
    try:
        with open(path, encoding='utf-8') as f:
            plan = json.load(f)
    except (OSError, ValueError) as ex:
        fail(f"can't read the plan {path}: {ex}", 2)
    if not isinstance(plan, dict) or not plan or not all(
            re.fullmatch(r'[1-9][0-9]*', key) and isinstance(ops, list) and ops and all(isinstance(op, dict) for op in ops)
            for key, ops in plan.items()):
        fail("a plan is a JSON object: article ID → a list of operations, each an object (SKILL.md)", 2)
    return plan


def locate(blocks: list, op: dict, what: str) -> tuple:
    """(block, position): the place an operation inserts at, after or before the block it quotes."""
    if ('after' in op) == ('before' in op):
        fail(f"{what}: say where with exactly one of after and before")
    block = find_block(blocks, op.get('after', op.get('before')), what)
    return block, block.end if 'after' in op else block.start


def splices_of(article: Article, blocks: list, downloads: dict, ops: list, hidden_runs: list) -> tuple:
    """The plan's operations as (start, end, new markup, order) on the editor's copy, and the Downloads they link."""
    splices, linked = [], {}
    for number, op in enumerate(ops, 1):
        kind = op.get('op')
        what = f"article {article.id}, operation {number} ({kind})"
        download = None
        if kind in ('line', 'link', 'repoint'):
            download = downloads.get(op.get('file'))
            if not download:
                fail(f"{what}: Download #{op.get('file')} isn't listed for this article under «File da controllare:»")
            linked[download['id']] = download
            url = html.escape(download['url'])

        if kind == 'line':
            block, position = locate(blocks, op, what)
            if op.get('lead_in') and 'before' in op:
                # a paragraph of its own, right before the line: for a place with no paragraph to end, such as the
                # end of a section closed by a credit or an image (splice() keeps the order of the same operation)
                splices.append((position, position, f"<p>{html.escape(op['lead_in'].strip(), quote=False)}</p>",
                                number))
            elif op.get('lead_in'):
                if block.tag != 'p' or not block.html.endswith('</p>'):
                    fail(f"{what}: a lead_in with after ends the paragraph the line follows: it must be a paragraph")
                splices.append((block.end - 4, block.end - 4, html.escape(op['lead_in'], quote=False), number))
            splices.append((position, position, DOWNLOAD_LINE.format(
                url=url, title=html.escape(download['title'], quote=False)), number))
        elif kind == 'link':
            block = find_block(blocks, op.get('in'), what)
            start, end = find_words(block, op.get('text'), what)
            splices.append((start, end, f'<a href="{url}">{article.body[start:end]}</a>', number))
        elif kind == 'repoint':
            hrefs = [match for match in re.finditer(r'<a\b[^>]*?\bhref="([^"]*)"', article.body)
                     if html.unescape(match.group(1)) == op.get('href')]
            if len(hrefs) != 1:
                fail(f"{what}: {len(hrefs)} links of the text point at {quoted(op.get('href'))}, and it must be one")
            splices.append((hrefs[0].start(1), hrefs[0].end(1), url, number))
        elif kind == 'restore':
            text = ' '.join(str(op.get('text') or '').split())
            if not text or text not in hidden_runs:
                fail(f"{what}: restore puts back only text that the database stores and the page hides, word for "
                     "word as `show` reports it, and this isn't")
            _, position = locate(blocks, op, what)
            splices.append((position, position, f'<p>{html.escape(text, quote=False)}</p>', number))
        else:
            fail(f"{what}: the operations are line, link, repoint and restore")
    return splices, linked


def splice(body: str, splices: list) -> str:
    """The body with every operation applied, all of them measured on the editor's copy as it came. In one pass from
    the start: at the same place, insertions come before a replacement, and in the plan's order among themselves."""
    parts, cursor = [], 0
    for start, end, markup, number in sorted(splices, key=lambda item: (item[0], item[1] > item[0], item[3])):
        if start < cursor:
            fail(f"operation {number} changes words that another operation of the same article changes too")
        parts += [body[cursor:start], markup]
        cursor = end
    return ''.join(parts) + body[cursor:]


def print_changes(old_body: str, new_body: str, prefix: str = '    ') -> None:
    old = [readable(block.html) for block in blocks_of(old_body)]
    new = [readable(block.html) for block in blocks_of(new_body)]
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, old, new, autojunk=False).get_opcodes():
        if tag == 'equal':
            continue
        if tag == 'insert':
            # what an inserted block follows, or precedes when it's the first one
            print(f"{prefix}  {clip(old[i1 - 1], 240) if i1 else '(start of the text)'}")
        for line in old[i1:i2]:
            print(f"{prefix}- {clip(line, 700)}")
        for line in new[j1:j2]:
            print(f"{prefix}+ {clip(line, 700)}")


def save(site, article: Article, body: str) -> str:
    # what the editor posts (article-edit-contenteditable.js): the title as it is, the body, an empty token
    status, _, text = site.request('POST', article.save_path, {'title': article.title, 'body': body, 'token': ''},
                                   {'Referer': site.base + article.path}, ajax=True)
    try:
        message = str(json.loads(text).get('message', ''))
    except (ValueError, AttributeError):
        message = ''
    if status != 200 or not message.startswith('✅'):
        fail(f"article {article.id}: the save was refused (HTTP {status}): {clip(text_of(text), 400)}")
    return one_line(message)


# ---------------------------------------------------------------------------------------------------- commands

def cmd_list(site, args) -> None:
    listing = fetch_list(site)
    print(f"«File da controllare:» on {site.host}: {len(listing['articles'])} articles"
          + (f" (list generated {one_line(listing['generated'])})" if listing['generated'] else ''))
    for article in listing['articles'].values():
        print(f"{article['id']}\t{quoted(article['title'])}\tby {one_line(article['authors'])}")
        for download in article['downloads']:
            print(f"\t#{download['id']} {quoted(download['title'])}\t{one_line(download['url'])}")
    if args.out:
        with open(args.out, 'w', encoding='utf-8') as f:
            json.dump(listing, f, ensure_ascii=False, indent=1)
        print(f"saved to {args.out}")


def hidden_report(article: Article, copy, why) -> dict:
    if copy is None:
        return {'state': 'unknown', 'why': why}
    return check_hidden(article, copy)


def cmd_show(site, args) -> None:
    listing = listing_for(site, args)
    item = listed_article(listing, args.article, site)
    article = Article(site, args.article)
    blocks = blocks_of(article.body)
    print(f"article {article.id} {quoted(article.title)} by {one_line(item['authors'])}: "
          + ('published' if article.published else 'NOT PUBLISHED (HTTP 503: a draft, or under review)')
          + (f", last changed {article.modified[:16]}" if article.modified else '')
          + (f" (listed on {listing['from']})" if listing.get('from') else ''))
    for download in target_downloads(article, item).values():
        state = ('ALREADY LINKED in the text' if download['linked'] else 'attached, not linked') \
            if download['attached'] else f"NOT ATTACHED to it on {site.host}"
        print(f"  Download #{download['id']} {quoted(download['title'])} {one_line(download['url'])}: {state}")

    if args.skip_hidden_check:
        print("hidden-text check: skipped")
    else:
        copy, why = stored_copy([article.id])
        hidden = hidden_report(article, copy, why)
        if hidden['state'] == 'ok':
            print("hidden-text check: OK, the editor gets every word this checkout's copy stores")
        elif hidden['state'] == 'hidden':
            print(f"hidden-text check: {len(hidden['runs'])} run(s) of words that the database stores and the page "
                  "never shows. A save deletes them for good, unless the plan restores them:")
            for hint in restore_hints(blocks, hidden):
                print(f"  {json.dumps(hint, ensure_ascii=False)}")
        else:
            print(f"hidden-text check: {hidden['state'].upper()}: {hidden['why']}")

    names = {download['id']: download['title'].lower() for download in item['downloads']}
    marked = set()
    notes = {}
    for index, block in enumerate(blocks):
        text, why = block.text.lower(), []
        if MENTION.search(text):
            why.append('allegato/scarica/download')
        for file_id, name in names.items():
            if name in text or name.replace('_', ' ') in text:
                why.append(f"title of #{file_id}")
        for href in re.findall(r'<a\b[^>]*?\bhref="(https?://[^"]+)"', block.html):
            host = urllib.parse.urlsplit(html.unescape(href)).hostname or ''
            if host and host != site.host and why:
                why.append(f"links {host}")
        if why:
            marked.add(index)
            notes[index] = why
    shown = range(len(blocks)) if args.all else sorted({n for i in marked for n in (i - 1, i, i + 1)
                                                        if 0 <= n < len(blocks)})
    print(f"text: {len(blocks)} blocks" + ('' if args.all else f", the {len(marked)} that may mention a Download "
                                           "with the ones around them (--all prints them all)") + ':')
    previous = None
    for index in shown:
        if previous is not None and index != previous + 1:
            print('  …')
        line = readable(blocks[index].html)
        mark = f"   ◀ {', '.join(dict.fromkeys(notes[index]))}" if index in notes else ''
        print(f"  [{index}] {blocks[index].tag}: {line if args.full else clip(line, 600)}{mark}")
        previous = index


def cmd_apply(site, args) -> None:
    plan = load_plan(args.plan)
    if args.article:
        if str(args.article) not in plan:
            fail(f"article {args.article} isn't in the plan", 2)
        plan = {str(args.article): plan[str(args.article)]}
    listing = listing_for(site, args)
    ids = [int(key) for key in plan]
    for article_id in ids:
        listed_article(listing, article_id, site)

    copy, why = (None, 'skipped') if args.skip_hidden_check else stored_copy(ids)
    if copy is None and not args.skip_hidden_check:
        fail(f"the hidden-text check needs this checkout's database ({why}). Without it, a save can delete stored "
             "text that the page doesn't show: run it where a dev copy is, or pass --skip-hidden-check knowingly")

    done, skipped = [], []
    for article_id in ids:
        item = listing['articles'][article_id]
        article = Article(site, article_id)
        blocks = blocks_of(article.body)
        hidden = None if args.skip_hidden_check else check_hidden(article, copy)
        print(f"== {article_id} {quoted(article.title)}" + ('' if article.published else ' (not published)'))
        if hidden and hidden['state'] in ('stale', 'unknown'):
            skipped.append((article_id, f"hidden-text check: {hidden['why']}"))
            print(f"   SKIPPED, nothing saved: hidden-text check {hidden['state']}: {hidden['why']}")
            continue

        downloads = target_downloads(article, item)
        named = [downloads[op['file']] for op in plan[str(article_id)] if op.get('file') in downloads]
        detached = [f"#{download['id']}" for download in named if not download['attached']]
        if detached:
            skipped.append((article_id, f"Download(s) {', '.join(detached)} no longer attached to it on {site.host}"))
            print(f"   SKIPPED, nothing saved: Download(s) {', '.join(detached)} no longer attached to the article on "
                  f"{site.host}: detached after the list was made?")
            continue
        already = [f"#{download['id']}" for download in named if download['linked']]
        if already:
            skipped.append((article_id, f"Download(s) {', '.join(already)} already linked in the text"))
            print(f"   SKIPPED, nothing saved: the text already links Download(s) {', '.join(already)}: the list is "
                  "older than the article, or the plan is: `show` it again")
            continue
        splices, linked = splices_of(article, blocks, downloads, plan[str(article_id)],
                                     hidden['runs'] if hidden else [])
        body = splice(article.body, splices)
        for download in linked.values():
            if f'href="{html.escape(download["url"])}"' not in body:
                fail(f"article {article_id}: after the plan, Download #{download['id']} still isn't linked")
        if not only_adds(article.body, body):
            fail(f"article {article_id}: the plan would remove some of the text, and its operations only add: stop "
                 "and look, it's a bug of this script")
        restored = {' '.join(str(op.get('text') or '').split()) for op in plan[str(article_id)]
                    if op.get('op') == 'restore'}
        kept_hidden = [run for run in (hidden['runs'] if hidden else []) if run not in restored]
        if kept_hidden:
            skipped.append((article_id, "a save would delete words that the database stores and the page hides"))
            print("   SKIPPED, nothing saved: a save would delete words that the database stores and the page hides "
                  f"({quoted(clip(kept_hidden[0], 120))}). `show` prints the restore operations that put them back")
            continue
        print_changes(article.body, body)
        unlinked = [download for download in item['downloads'] if download['id'] not in linked]
        for download in unlinked:
            print(f"   NOTE: Download #{download['id']} {quoted(download['title'])} stays unlinked: the article "
                  "stays in the list")
        if args.dry_run:
            print("   dry run: nothing saved")
            done.append(article_id)
            continue

        message = save(site, article, body)
        time.sleep(1)
        after = Article(site, article_id)
        missing = [download['id'] for download in linked.values()
                   if f'href="{html.escape(download["url"])}"' not in after.body]
        if missing:
            fail(f"article {article_id}: saved ({message}), but the page doesn't link Download(s) "
                 f"{', '.join(f'#{m}' for m in missing)}: check it")
        lost, gained, replaced = word_changes(reader_words(body), reader_words(after.body))
        print(f"   OK saved: {message}")
        for old, new in replaced:
            print(f"   the save itself changed {quoted(old)} into {quoted(new)}")
        for words in lost:
            print(f"   WARNING: the save dropped {quoted(clip(words, 200))}: check the article")
        for words in gained:
            print(f"   the save itself added {quoted(clip(words, 200))}")
        done.append(article_id)

    left = [article_id for article_id in listing['articles'] if str(article_id) not in plan]
    print()
    print(("ready to save (dry run, nothing saved): " if args.dry_run else "saved: ")
          + (', '.join(str(i) for i in done) or 'none'))
    for article_id, reason in skipped:
        print(f"skipped {article_id}: {reason}")
    if left and not args.article and not listing.get('from'):
        print(f"not in the plan, left as they are: {', '.join(str(i) for i in left)}")
    if done and not args.dry_run:
        print("The list is rebuilt by FilesToArticles (uploaded-assets-maintenance.sh, every 3 hours): run `list` "
              "after that, and these articles are gone from it.")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest='command', required=True)

    listing = sub.add_parser('list')
    listing.add_argument('state')
    listing.add_argument('--out', help='also save the list there, as JSON')
    listing.set_defaults(run=cmd_list)

    show = sub.add_parser('show')
    show.add_argument('state')
    show.add_argument('--article', type=int, required=True)
    show.add_argument('--all', action='store_true', help='every block, not just the ones that may mention a Download')
    show.add_argument('--full', action='store_true', help="don't cut long blocks")
    show.add_argument('--skip-hidden-check', action='store_true')
    show.add_argument('--list-from', metavar='STATE', help="take the list from another session's site (a dev copy)")
    show.set_defaults(run=cmd_show)

    apply = sub.add_parser('apply')
    apply.add_argument('state')
    apply.add_argument('--plan', required=True)
    apply.add_argument('--article', type=int, help="only this article of the plan")
    apply.add_argument('--dry-run', action='store_true', help='check and print the changes, save nothing')
    apply.add_argument('--list-from', metavar='STATE', help="take the list from another session's site (a dev copy)")
    apply.add_argument('--skip-hidden-check', action='store_true',
                       help="save even where this checkout's database can't tell whether the page hides text")
    apply.set_defaults(run=cmd_apply)

    args = parser.parse_args()
    args.run(forum.Site.load(args.state), args)


if __name__ == '__main__':
    main()
