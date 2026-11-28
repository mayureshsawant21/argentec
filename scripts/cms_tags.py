#!/usr/bin/env python3
"""Mark the editable content of every page for the admin panel.

Adds data-cms="<key>" to the text and media inside <main> that the admin
panel (admin/pages.php) can edit: headings, paragraphs, list items, figure
captions, definition terms, table cells, figures with counters, images,
video posters and video sources. Keys are per page (h1-1, p-4, img-2 ...).

- Elements that already carry a key keep it, so edits saved in the admin
  stay attached after a page is regenerated.
- Forms, navigation, SVG, scripts, the careers openings block (between
  <!-- cms:... --> markers) and blog pages (managed by the blog editor)
  are left alone.
- Text-holding elements are only tagged when their content is plain text
  with inline markup (em, strong, br, links, spans), so an edit can never
  break the layout around it.

Run from the repository root (the build scripts call it automatically):

    python3 scripts/cms_tags.py
"""
import os
import re
from html.parser import HTMLParser

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP_DIRS = {"admin", "api", "blog", "scripts", "docs", "content", "node_modules", ".git", "assets", "css", "js", "data"}
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}
INLINE = {"em", "strong", "b", "i", "br", "small", "span", "a", "sup", "sub", "time", "abbr", "mark", "u"}
TEXT_TAGS = {"h1", "h2", "h3", "h4", "h5", "p", "li", "dt", "dd", "figcaption", "blockquote", "td", "th", "label"}
SKIP_INSIDE = {"form", "svg", "script", "style", "nav", "select", "button", "noscript", "template", "header"}


class Node:
    __slots__ = ("tag", "attrs", "start", "start_end", "end", "parent", "children", "text")

    def __init__(self, tag, attrs, start, start_end, parent):
        self.tag, self.attrs, self.start, self.start_end = tag, dict(attrs), start, start_end
        self.end = start_end  # updated when the end tag is seen
        self.parent, self.children, self.text = parent, [], ""


class Tree(HTMLParser):
    def __init__(self, src):
        super().__init__(convert_charrefs=False)
        self.src = src
        self.lines = [0]
        for m in re.finditer("\n", src):
            self.lines.append(m.end())
        self.root = Node("#root", [], 0, 0, None)
        self.cur = self.root
        self.feed(src)
        self.close()

    def _off(self):
        line, col = self.getpos()
        return self.lines[line - 1] + col

    def handle_starttag(self, tag, attrs):
        start = self._off()
        text = self.get_starttag_text()
        node = Node(tag, attrs, start, start + len(text), self.cur)
        self.cur.children.append(node)
        if tag not in VOID:
            self.cur = node

    def handle_startendtag(self, tag, attrs):
        start = self._off()
        text = self.get_starttag_text()
        self.cur.children.append(Node(tag, attrs, start, start + len(text), self.cur))

    def handle_endtag(self, tag):
        n = self.cur
        while n is not self.root and n.tag != tag:
            n = n.parent
        if n is self.root:
            return
        start = self._off()
        end = self.src.index(">", start) + 1
        # close everything up to and including n
        c = self.cur
        while c is not n:
            c.end = start
            c = c.parent
        n.end = end
        self.cur = n.parent

    def handle_data(self, data):
        self.cur.text += data


def walk(node):
    for c in node.children:
        yield c
        yield from walk(c)


def plain(node):
    """True when every descendant is an inline tag (no blocks, media, svg)."""
    for d in walk(node):
        if d.tag not in INLINE:
            return False
    return True


def has_text(node, src):
    inner = src[node.start_end:node.end - len("</%s>" % node.tag)] if node.tag not in VOID else ""
    return bool(re.sub(r"<[^>]+>|&nbsp;|\s", "", inner))


def direct_text(node, src):
    """Text sitting directly in node (outside child elements)."""
    if node.tag in VOID:
        return ""
    pos, out = node.start_end, []
    for c in node.children:
        out.append(src[pos:c.start])
        pos = c.end
    out.append(src[pos:node.end - len("</%s>" % node.tag)])
    return re.sub(r"\s|&nbsp;", "", "".join(out))


def classed_text_spans(node, src):
    """For titles built from wrapper spans (line masks), the spans to tag."""
    out = []
    for c in node.children:
        if c.tag == "span" and has_text(c, src):
            inner_spans = [g for g in c.children if g.tag == "span" and "class" in g.attrs and has_text(g, src)]
            if inner_spans and not direct_text(c, src):
                out.extend(inner_spans)
            else:
                out.append(c)
    return out


def tag_page(path):
    src = open(path, encoding="utf-8").read()
    if "<main" not in src:
        return 0
    tree = Tree(src)
    main = next((n for n in walk(tree.root) if n.tag == "main"), None)
    if not main:
        return 0

    # regions owned by other editors (careers openings) are skipped
    blocked = [(m.start(), m.end()) for m in re.finditer(r"<!-- cms:([a-z-]+) -->.*?<!-- /cms:\1 -->", src, re.S)]

    def in_blocked(n):
        return any(a <= n.start < b for a, b in blocked)

    existing = set(re.findall(r'data-cms="([^"]+)"', src))
    counters = {}

    def next_key(tag):
        while True:
            counters[tag] = counters.get(tag, 0) + 1
            k = "%s-%d" % (tag, counters[tag])
            if k not in existing:
                existing.add(k)
                return k

    targets = []

    def visit(n, inside_tagged=False):
        for c in n.children:
            if c.tag in SKIP_INSIDE or in_blocked(c) or "data-cms-skip" in c.attrs:
                continue
            tagged_here = "data-cms" in c.attrs
            if c.tag == "img" or (c.tag == "video" and "poster" in c.attrs) or (c.tag == "source" and n.tag == "video"):
                if not tagged_here:
                    targets.append(c)
            elif not inside_tagged and not tagged_here:
                if c.tag in TEXT_TAGS and plain(c) and has_text(c, src):
                    spans = classed_text_spans(c, src) if not direct_text(c, src) else []
                    if spans and all(plain(s) for s in spans):
                        targets.extend(s for s in spans if "data-cms" not in s.attrs)
                        visit(c, True)
                        continue
                    targets.append(c)
                    visit(c, True)
                    continue
                if c.tag == "span" and "data-count" in c.attrs:
                    targets.append(c)
                    continue
                if c.tag == "a" and not plain(c):
                    # links with an icon: tag the span that holds the label
                    for s in c.children:
                        if s.tag == "span" and plain(s) and has_text(s, src) and "data-cms" not in s.attrs:
                            targets.append(s)
                    continue
            visit(c, inside_tagged or tagged_here)

    visit(main)
    if not targets:
        return 0
    # keys in document order, insert from the end so offsets stay valid
    targets.sort(key=lambda n: n.start)
    keyed = [(n, next_key(n.tag)) for n in targets]
    out = src
    for n, key in sorted(keyed, key=lambda t: t[0].start, reverse=True):
        st = out[n.start:n.start_end]
        close = "/>" if st.endswith("/>") else ">"
        new = st[: len(st) - len(close)].rstrip() + ' data-cms="%s"' % key + close
        out = out[: n.start] + new + out[n.start_end:]
    with open(path, "w", encoding="utf-8") as f:
        f.write(out)
    return len(keyed)


def pages():
    for dirpath, dirs, files in os.walk(ROOT):
        rel = os.path.relpath(dirpath, ROOT)
        top = rel.split(os.sep)[0]
        if rel != "." and (top in SKIP_DIRS or top.startswith(".")):
            dirs[:] = []
            continue
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS and not d.startswith(".")]
        for f in files:
            if f.endswith(".html") and f != "coming-soon.html":
                yield os.path.join(dirpath, f)


def main():
    total = 0
    for p in sorted(pages()):
        total += tag_page(p)
    print("cms: tagged %d new editable element(s)" % total)


if __name__ == "__main__":
    main()
