#!/usr/bin/env python3
"""Build the blog: blog/index.html plus one page per article.

Run from the repository root:

    python3 scripts/build_blog.py

Articles are Markdown files in content/blog/<slug>.md with a front-matter
block at the top:

    ---
    title: Article headline (becomes the H1)
    date: 2026-11-17
    author: Argentec Editorial
    category: Pre-construction
    excerpt: One or two sentences shown on the blog card.
    cover: assets/img/blog/<file>
    cover_alt: Description of the cover image
    noindex: true          (optional; keeps sample articles out of search)
    ---

Supported Markdown: ## and ### headings, paragraphs, - and 1. lists,
> quotes, ![alt](src) images, **bold**, *italic* and [links](url).
The output is plain static HTML; don't hand-edit generated pages.
"""
import datetime
import json
import math
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scripts"))
from build_expertise import SITE, ARROW, esc, set_menus, footer, breadcrumb, shared_header, prefix_links  # noqa: E402

CONTENT = os.path.join(ROOT, "content", "blog")
OUT = os.path.join(ROOT, "blog")


# ---------------------------------------------------------------------------
# Content
# ---------------------------------------------------------------------------
def parse_front_matter(text):
    m = re.match(r"^---\s*\n(.*?)\n---\s*\n(.*)$", text, re.S)
    if not m:
        raise ValueError("missing front matter")
    meta = {}
    for line in m.group(1).splitlines():
        if ":" in line:
            k, v = line.split(":", 1)
            meta[k.strip()] = v.strip()
    return meta, m.group(2)


def inline(text):
    t = esc(text)
    t = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", t)
    t = re.sub(r"(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)", r"<em>\1</em>", t)
    t = re.sub(r"\[([^\]]+)\]\(([^)\s]+)\)", r'<a href="\2">\1</a>', t)
    return t


def markdown(md):
    out, headings = [], []
    for block in re.split(r"\n\s*\n", md.strip()):
        lines = block.strip().splitlines()
        first = lines[0]
        img = re.match(r"^!\[([^\]]*)\]\(([^)\s]+)\)$", block.strip())
        if img:
            out.append('<figure class="bl-figure"><img src="%s" alt="%s" loading="lazy"></figure>'
                       % (esc(img.group(2)), esc(img.group(1))))
        elif first.startswith("### "):
            out.append("<h3>%s</h3>" % inline(first[4:]))
        elif first.startswith("## "):
            text = first[3:]
            hid = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
            headings.append((hid, text))
            out.append('<h2 id="%s">%s</h2>' % (hid, inline(text)))
        elif all(l.lstrip().startswith(("- ", "* ")) for l in lines):
            out.append("<ul>%s</ul>" % "".join("<li>%s</li>" % inline(l.lstrip()[2:]) for l in lines))
        elif all(re.match(r"^\s*\d+\.\s", l) for l in lines):
            out.append("<ol>%s</ol>" % "".join("<li>%s</li>" % inline(re.sub(r"^\s*\d+\.\s", "", l)) for l in lines))
        elif all(l.startswith(">") for l in lines):
            out.append("<blockquote><p>%s</p></blockquote>" % inline(" ".join(l.lstrip("> ").strip() for l in lines)))
        else:
            out.append("<p>%s</p>" % inline(" ".join(l.strip() for l in lines)))
    return "\n          ".join(out), headings


def load_posts():
    posts = []
    for name in sorted(os.listdir(CONTENT)):
        if not name.endswith(".md"):
            continue
        meta, body = parse_front_matter(open(os.path.join(CONTENT, name), encoding="utf-8").read())
        html_body, headings = markdown(body)
        words = len(re.findall(r"\w+", body))
        date = datetime.date.fromisoformat(meta["date"])
        posts.append({
            "slug": name[:-3],
            "title": meta["title"],
            "date": date,
            "date_label": date.strftime("%-d %B %Y"),
            "author": meta.get("author", "Argentec"),
            "category": meta.get("category", "Insights"),
            "excerpt": meta.get("excerpt", ""),
            "cover": meta.get("cover", "assets/img/projects/placeholder-1.svg"),
            "cover_alt": meta.get("cover_alt", ""),
            "noindex": meta.get("noindex", "").lower() == "true",
            "minutes": max(1, math.ceil(words / 200)),
            "html": html_body,
            "headings": headings,
        })
    posts.sort(key=lambda p: p["date"], reverse=True)
    return posts


# ---------------------------------------------------------------------------
# Shared bits
# ---------------------------------------------------------------------------
def page_header(header_markup):
    hdr = set_menus(header_markup, "../")
    hdr = hdr.replace('<a class="nav-link" href="../blog/index.html">Blog</a>',
                      '<a class="nav-link is-current" href="../blog/index.html" aria-current="page">Blog</a>')
    return hdr


def head(title, description, canonical, image=None, noindex=False, og_type="website"):
    return """<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>%(title)s</title>
  <meta name="description" content="%(desc)s">%(robots)s
  <meta name="theme-color" content="#ffffff">
  <link rel="canonical" href="%(canon)s">
  <link rel="icon" type="image/svg+xml" href="../assets/img/argentec-logo.svg">

  <meta property="og:type" content="%(og)s">
  <meta property="og:title" content="%(title)s">
  <meta property="og:description" content="%(desc)s">
  <meta property="og:url" content="%(canon)s">%(image)s

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/blog.css">
  <link rel="stylesheet" href="../css/footer.css">
</head>""" % {"title": esc(title), "desc": esc(description), "canon": canonical, "og": og_type,
             "robots": '\n  <meta name="robots" content="noindex">' if noindex else "",
             "image": '\n  <meta property="og:image" content="%s/%s">' % (SITE, image) if image else ""}


def scripts():
    return """  <script src="../js/main.js"></script>
  <script src="../js/expertise.js"></script>
  <script src="../js/blog.js"></script>"""


def card(p, i, featured=False):
    return """          <li class="bl-card%(feat)s" data-reveal style="--i:%(k)d">
            <a href="%(slug)s.html">
              <div class="bl-card__media"><img src="../%(cover)s" alt="%(alt)s" width="1200" height="750" loading="lazy"></div>
              <div class="bl-card__body">
                <p class="bl-meta"><span>%(cat)s</span><time datetime="%(iso)s">%(date)s</time><span>%(min)d min read</span></p>
                <h2 class="bl-card__title">%(title)s</h2>
                <p class="bl-card__excerpt">%(excerpt)s</p>
                <span class="bl-card__more">Read article %(arrow)s</span>
              </div>
            </a>
          </li>""" % {"feat": " bl-card--featured" if featured else "", "k": i % 3, "slug": p["slug"],
                     "cover": p["cover"], "alt": esc(p["cover_alt"]), "cat": esc(p["category"]),
                     "iso": p["date"].isoformat(), "date": p["date_label"], "min": p["minutes"],
                     "title": esc(p["title"]), "excerpt": esc(p["excerpt"]), "arrow": ARROW}


# ---------------------------------------------------------------------------
# Pages
# ---------------------------------------------------------------------------
def hub(posts, header_markup):
    crumbs, crumbs_ld = breadcrumb("../", [("Home", "../index.html"), ("Blog", None)])
    cards = [card(p, i, featured=(i == 0)) for i, p in enumerate(posts)]
    ld = {"@context": "https://schema.org", "@type": "Blog", "name": "Argentec Blog", "url": SITE + "/blog/",
          "blogPost": [{"@type": "BlogPosting", "headline": p["title"], "datePublished": p["date"].isoformat(),
                        "url": "%s/blog/%s.html" % (SITE, p["slug"])} for p in posts if not p["noindex"]]}
    body = """
<body class="page-blog header-solid">

  %(header)s
  <main id="main">
    <section class="bl-hero" aria-labelledby="bl-title">
      <div class="bl-inner">
        %(crumbs)s
        <p class="bl-eyebrow">Blog</p>
        <h1 class="bl-hero__title" id="bl-title">Notes from <em>the site.</em></h1>
        <p class="bl-hero__lead">Practical thinking on planning, building and delivering projects in Mumbai, from the people who run them.</p>
      </div>
    </section>

    <section class="bl-list" aria-label="Articles">
      <div class="bl-inner">
        <ul class="bl-grid">
%(cards)s
        </ul>
      </div>
    </section>
  </main>

%(footer)s

%(scripts)s
  %(crumbs_ld)s
  <script type="application/ld+json">%(ld)s</script>
</body>
</html>
""" % {"header": page_header(header_markup), "crumbs": crumbs, "cards": "\n".join(cards) or "          <li class=\"bl-empty\">Articles are on their way.</li>",
       "footer": footer("../"), "scripts": scripts(), "crumbs_ld": crumbs_ld, "ld": json.dumps(ld, ensure_ascii=False)}
    return head("Blog | Argentec", "Articles from Argentec on planning, building and delivering construction projects in Mumbai.",
                SITE + "/blog/") + body


def article(i, posts, header_markup):
    p = posts[i]
    crumbs, crumbs_ld = breadcrumb("../", [("Home", "../index.html"), ("Blog", "index.html"), (p["title"], None)])
    others = [q for q in posts if q is not p][:3]
    more = ""
    if others:
        more = """    <section class="bl-more" aria-labelledby="more-title">
      <div class="bl-inner">
        <h2 class="bl-more__title" id="more-title">More articles</h2>
        <ul class="bl-grid">
%s
        </ul>
      </div>
    </section>
""" % "\n".join(card(q, k) for k, q in enumerate(others))
    toc = ""
    if len(p["headings"]) >= 3:
        toc = '<nav class="bl-toc" aria-label="In this article"><p>In this article</p><ol>%s</ol></nav>' % "".join(
            '<li><a href="#%s">%s</a></li>' % (h, esc(t)) for h, t in p["headings"])
    url = "%s/blog/%s.html" % (SITE, p["slug"])
    ld = {"@context": "https://schema.org", "@type": "BlogPosting", "headline": p["title"],
          "description": p["excerpt"], "datePublished": p["date"].isoformat(), "image": SITE + "/" + p["cover"],
          "author": {"@type": "Organization", "name": p["author"]},
          "publisher": {"@type": "Organization", "name": "Argentec", "logo": {"@type": "ImageObject", "url": SITE + "/assets/img/argentec-logo.svg"}},
          "mainEntityOfPage": url}
    body = """
<body class="page-blog header-solid">

  %(header)s
  <main id="main">
    <article class="bl-article">
      <header class="bl-article__head">
        <div class="bl-inner bl-inner--narrow">
          %(crumbs)s
          <p class="bl-meta"><span>%(cat)s</span><time datetime="%(iso)s">%(date)s</time><span>%(min)d min read</span></p>
          <h1 class="bl-article__title">%(title)s</h1>
          <p class="bl-article__lead">%(excerpt)s</p>
          <p class="bl-article__byline">By %(author)s</p>
        </div>
        <div class="bl-inner">
          <figure class="bl-article__cover"><img src="../%(cover)s" alt="%(alt)s" width="1200" height="750"></figure>
        </div>
      </header>

      <div class="bl-inner bl-article__layout">
        <aside class="bl-article__aside">
          %(toc)s
          <button class="bl-share" type="button" data-copy-link>
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <span data-copy-label>Copy link</span>
          </button>
        </aside>
        <div class="bl-prose">
          %(html)s
        </div>
      </div>

      <div class="bl-inner bl-inner--narrow bl-article__foot">
        <a class="bl-back" href="index.html"><svg viewBox="0 0 32 12" width="32" height="12" aria-hidden="true"><path d="M32 6H2M7 1L2 6l5 5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg><span>All articles</span></a>
      </div>
    </article>
%(more)s  </main>

%(footer)s

%(scripts)s
  %(crumbs_ld)s
  <script type="application/ld+json">%(ld)s</script>
</body>
</html>
""" % {"header": page_header(header_markup), "crumbs": crumbs, "cat": esc(p["category"]),
       "iso": p["date"].isoformat(), "date": p["date_label"], "min": p["minutes"], "title": esc(p["title"]),
       "excerpt": esc(p["excerpt"]), "author": esc(p["author"]), "cover": p["cover"], "alt": esc(p["cover_alt"]),
       "toc": toc, "html": p["html"], "more": more, "footer": footer("../"), "scripts": scripts(),
       "crumbs_ld": crumbs_ld, "ld": json.dumps(ld, ensure_ascii=False)}
    return head("%s | Argentec Blog" % p["title"], p["excerpt"], url, image=p["cover"],
                noindex=p["noindex"], og_type="article") + body


def main():
    posts = load_posts()
    header = prefix_links(shared_header(), "../")
    os.makedirs(OUT, exist_ok=True)
    keep = {"index.html"} | {p["slug"] + ".html" for p in posts}
    for name in os.listdir(OUT):
        if name.endswith(".html") and name not in keep:
            os.remove(os.path.join(OUT, name))
            print("removed blog/%s" % name)
    open(os.path.join(OUT, "index.html"), "w", encoding="utf-8").write(hub(posts, header))
    for i, p in enumerate(posts):
        open(os.path.join(OUT, p["slug"] + ".html"), "w", encoding="utf-8").write(article(i, posts, header))
    print("wrote blog/index.html + %d article(s)" % len(posts))


if __name__ == "__main__":
    main()
    import cache_bust
    cache_bust.main()
