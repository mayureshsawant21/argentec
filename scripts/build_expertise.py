#!/usr/bin/env python3
"""Build the Expertise section and keep the Expertise menu in sync.

Run from the repository root:

    python3 scripts/build_expertise.py

What it does (safe to run repeatedly):
  1. Writes the Expertise mega menu (desktop) and expandable group (mobile)
     into the header of every root page, between
     <!-- expertise-menu:* --> markers.
  2. Generates expertise/index.html and one page per service in
     scripts/expertise_content.py, reusing the header from contact.html.
  3. Writes docs/expertise-content-review.md, listing statements that need
     Argentec's confirmation before publication.

The output is plain static HTML, so GitHub Pages serves it as-is. Edit the
content file (or this template) and re-run; don't hand-edit generated pages.
"""
import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scripts"))
from expertise_content import SERVICES, SERVICES_BY_SLUG  # noqa: E402

SITE = "https://argentec.com"
ROOT_PAGES = ["index.html", "about.html", "contact.html", "careers.html"]
CHEVRON = '<svg viewBox="0 0 12 8" aria-hidden="true"><path d="M1 1.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>'
ARROW = '<svg viewBox="0 0 32 12" width="32" height="12" aria-hidden="true"><path d="M0 6h30M25 1l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>'


def esc(text):
    return html.escape(text, quote=True)


def accent(text):
    """Escape, then turn **word** into <em>word</em>."""
    return re.sub(r"\*\*(.+?)\*\*", r"<em>\1</em>", esc(text))


def plain(text):
    return text.replace("**", "")


def page_url(slug):
    return "expertise/%s.html" % slug


# ---------------------------------------------------------------------------
# Shared components
# ---------------------------------------------------------------------------
def mega_menu(prefix, current=None, hub=False):
    active = current is not None or hub
    items = []
    for s in SERVICES:
        cur = ' aria-current="page"' if s["slug"] == current else ""
        items.append(
            '            <li><a class="mega__link" href="%s%s"%s><span class="mega__num">%s</span>'
            '<span class="mega__name">%s</span><span class="mega__desc">%s</span></a></li>'
            % (prefix, page_url(s["slug"]), cur, s["num"], esc(s["name"]), esc(s["menu_desc"]))
        )
    hub_cur = ' aria-current="page"' if hub else ""
    return (
        '<!-- expertise-menu:desktop -->\n'
        '      <div class="nav-item" data-mega>\n'
        '        <a class="nav-link%s" href="%sexpertise/index.html"%s>Expertise</a>\n'
        '        <button class="nav-item__toggle" type="button" aria-expanded="false" aria-controls="mega-expertise" aria-label="Show Expertise pages">%s</button>\n'
        '        <div class="mega" id="mega-expertise">\n'
        '          <div class="mega__inner">\n'
        '            <div class="mega__intro">\n'
        '              <p class="mega__eyebrow">Expertise</p>\n'
        '              <p class="mega__lead">Seven connected services, from the first study to handover.</p>\n'
        '              <a class="mega__all" href="%sexpertise/index.html">View all expertise</a>\n'
        '            </div>\n'
        '            <ul class="mega__list">\n%s\n            </ul>\n'
        '          </div>\n'
        '        </div>\n'
        '      </div>\n'
        '      <!-- /expertise-menu:desktop -->'
    ) % (" is-current" if active else "", prefix, hub_cur, CHEVRON, prefix, "\n".join(items))


def mobile_group(prefix, current=None, hub=False):
    items = []
    for s in SERVICES:
        cur = ' aria-current="page"' if s["slug"] == current else ""
        items.append('          <li><a href="%s%s"%s>%s</a></li>' % (prefix, page_url(s["slug"]), cur, esc(s["name"])))
    open_ = current is not None or hub
    return (
        '<!-- expertise-menu:mobile -->\n'
        '      <div class="mm-group">\n'
        '        <div class="mm-group__row">\n'
        '          <a href="%sexpertise/index.html"%s>Expertise</a>\n'
        '          <button class="mm-group__toggle" type="button" aria-expanded="%s" aria-controls="mm-expertise" aria-label="Show Expertise pages">%s</button>\n'
        '        </div>\n'
        '        <ul class="mm-group__list" id="mm-expertise"%s>\n%s\n        </ul>\n'
        '      </div>\n'
        '      <!-- /expertise-menu:mobile -->'
    ) % (prefix, ' aria-current="page"' if hub else "", "true" if open_ else "false", CHEVRON,
         "" if open_ else " hidden", "\n".join(items))


DESKTOP_BLOCK = re.compile(r"<!-- expertise-menu:desktop -->.*?<!-- /expertise-menu:desktop -->", re.S)
MOBILE_BLOCK = re.compile(r"<!-- expertise-menu:mobile -->.*?<!-- /expertise-menu:mobile -->", re.S)
OLD_DESKTOP = re.compile(r'<a class="nav-link" href="(?:index\.html)?#expertise">Expertise</a>')
OLD_MOBILE = re.compile(r'<a href="(?:index\.html)?#expertise">Expertise</a>')


def set_menus(markup, prefix, current=None, hub=False):
    desktop = mega_menu(prefix, current, hub)
    mobile = mobile_group(prefix, current, hub)
    if DESKTOP_BLOCK.search(markup):
        markup = DESKTOP_BLOCK.sub(lambda m: desktop, markup)
    else:
        markup, n = OLD_DESKTOP.subn(lambda m: desktop, markup)
        assert n == 1, "Expertise link not found in desktop nav"
    if MOBILE_BLOCK.search(markup):
        markup = MOBILE_BLOCK.sub(lambda m: mobile, markup)
    else:
        markup, n = OLD_MOBILE.subn(lambda m: mobile, markup)
        assert n == 1, "Expertise link not found in mobile nav"
    return markup


def footer(prefix):
    services = "\n".join(
        '          <li><a href="%s%s">%s</a></li>' % (prefix, page_url(s["slug"]), esc(s["name"])) for s in SERVICES
    )
    return """  <!-- ============ FOOTER ============ -->
  <footer class="site-footer">
    <div class="site-footer__inner">
      <div class="site-footer__brand">
        <a class="site-footer__logo" href="%(p)sindex.html" aria-label="Argentec home"><img src="%(p)sassets/img/argentec-logo.svg" alt="" width="64" height="64"><span>Argentec</span></a>
        <p>Shaping the foundation of growth.</p>
        <a class="btn btn--silver site-footer__cta" href="%(p)scontact.html?type=development">Start a project</a>
      </div>
      <nav class="site-footer__col" aria-label="Expertise">
        <p class="site-footer__title">Expertise</p>
        <ul>
%(services)s
        </ul>
      </nav>
      <nav class="site-footer__col" aria-label="Company">
        <p class="site-footer__title">Company</p>
        <ul>
          <li><a href="%(p)sabout.html">About</a></li>
          <li><a href="%(p)sexpertise/index.html">All expertise</a></li>
          <li><a href="%(p)scontact.html">Contact</a></li>
          <li><a href="%(p)scontact.html?type=supplier">Suppliers &amp; subcontractors</a></li>
          <li><a href="%(p)scareers.html">Careers</a></li>
        </ul>
      </nav>
      <div class="site-footer__col">
        <p class="site-footer__title">Head office</p>
        <address>Office No. 401&ndash;404, 4th Floor<br>Prabhat Chambers, SV Road<br>Khar West, Mumbai 400052</address>
      </div>
    </div>
    <div class="site-footer__base">
      <span>&copy; <span data-year>2026</span> Argentec. All rights reserved.</span>
      <span>Contracting arm of Paradigm Realty</span>
    </div>
  </footer>""" % {"p": prefix, "services": services}


def breadcrumb(prefix, trail):
    """trail: list of (label, href or None)."""
    parts = []
    for i, (label, href) in enumerate(trail):
        if href:
            parts.append('<li><a href="%s">%s</a></li>' % (href, esc(label)))
        else:
            parts.append('<li><span aria-current="page">%s</span></li>' % esc(label))
    ld = {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": i + 1, "name": label,
             **({"item": SITE + "/" + abs_} if abs_ is not None else {})}
            for i, (label, _, abs_) in enumerate(trail_abs(trail))
        ],
    }
    return (
        '<nav class="xp-crumbs" aria-label="Breadcrumb"><ol>%s</ol></nav>' % "".join(parts),
        '<script type="application/ld+json">%s</script>' % json.dumps(ld, ensure_ascii=False),
    )


def trail_abs(trail):
    out = []
    for label, href in trail:
        if href is None:
            out.append((label, href, None))
        else:
            out.append((label, href, href.replace("../", "").replace("index.html", "").replace("expertise/", "expertise/")))
    return out


# ---------------------------------------------------------------------------
# Header reused from contact.html
# ---------------------------------------------------------------------------
def shared_header():
    src = open(os.path.join(ROOT, "contact.html"), encoding="utf-8").read()
    start = src.index('<header class="site-header"')
    end = src.index("  <main>")
    hdr = src[start:end]
    hdr = hdr.replace(' is-current" href="contact.html" aria-current="page"', '" href="contact.html"')
    return hdr


def prefix_links(markup, prefix):
    def fix(m):
        attr, url = m.group(1), m.group(2)
        if re.match(r"^(https?:|mailto:|tel:|#|/|data:)", url) or url.startswith(prefix):
            return m.group(0)
        return '%s="%s%s"' % (attr, prefix, url)
    return re.sub(r'(href|src)="([^"]*)"', fix, markup)


def head(title, description, canonical, image, noindex=False):
    robots = '\n  <meta name="robots" content="noindex">' if noindex else ""
    return """<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>%(title)s</title>
  <meta name="description" content="%(desc)s">%(robots)s
  <meta name="theme-color" content="#0f2a4d">
  <link rel="canonical" href="%(canon)s">
  <link rel="icon" type="image/svg+xml" href="../assets/img/argentec-logo.svg">

  <meta property="og:type" content="website">
  <meta property="og:title" content="%(title)s">
  <meta property="og:description" content="%(desc)s">
  <meta property="og:url" content="%(canon)s">
  <meta property="og:image" content="%(site)s/assets/img/expertise/%(image)s">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/expertise.css">
  <link rel="stylesheet" href="../css/footer.css">
</head>""" % {"title": esc(title), "desc": esc(description), "canon": canonical, "site": SITE,
             "image": image, "robots": robots}


def cta_band(prefix, heading, text):
    return """    <!-- ============ PROJECT ENQUIRY ============ -->
    <section class="xp-cta" aria-labelledby="cta-title">
      <div class="xp-cta__inner">
        <p class="xp-eyebrow xp-eyebrow--light" data-reveal><span class="xp-eyebrow__rule"></span>Project enquiry</p>
        <h2 class="xp-cta__title" id="cta-title" data-reveal>%s</h2>
        <p class="xp-cta__text" data-reveal>%s</p>
        <div class="xp-cta__actions" data-reveal>
          <a class="btn btn--silver" href="%scontact.html?type=development">Discuss your project</a>
          <a class="xp-link xp-link--light" href="%scontact.html"><span>Other enquiries</span>%s</a>
        </div>
      </div>
    </section>""" % (accent(heading), esc(text), prefix, prefix, ARROW)


# ---------------------------------------------------------------------------
# Service page
# ---------------------------------------------------------------------------
def service_page(s, header_markup):
    p = "../"
    crumbs, crumbs_ld = breadcrumb(p, [("Home", p + "index.html"), ("Expertise", p + "expertise/index.html"), (s["name"], None)])
    hdr = set_menus(header_markup, p, current=s["slug"])

    values = "\n".join(
        '            <li class="xp-value" data-reveal style="--i:%d"><h3>%s</h3><p>%s</p></li>' % (i, esc(t), esc(d))
        for i, (t, d) in enumerate(s["values"])
    )
    caps = "\n".join(
        '          <li class="xp-cap" data-reveal style="--i:%d"><span class="xp-cap__ref">%s.%d</span><h3>%s</h3><p>%s</p></li>'
        % (i % 3, s["num"], i + 1, esc(t), esc(d))
        for i, (t, d) in enumerate(s["capabilities"])
    )
    scope = "\n".join('            <li>%s</li>' % esc(x) for x in s["scope"])
    steps = "\n".join(
        '          <li class="xp-step" style="--i:%d"><span class="xp-step__num">%02d</span><h3>%s</h3><p>%s</p></li>'
        % (i, i + 1, esc(t), esc(d))
        for i, (t, d) in enumerate(s["process"])
    )
    apps = "\n".join(
        '          <li class="xp-app" data-reveal style="--i:%d"><h3>%s</h3><p>%s</p></li>' % (i, esc(t), esc(d))
        for i, (t, d) in enumerate(s["applications"])
    )
    related = "\n".join(
        '          <li><a class="xp-related__card" href="%s.html"><span class="xp-related__num">%s</span><span class="xp-related__name">%s</span><span class="xp-related__desc">%s</span><span class="xp-related__go" aria-hidden="true">%s</span></a></li>'
        % (r, SERVICES_BY_SLUG[r]["num"], esc(SERVICES_BY_SLUG[r]["name"]), esc(SERVICES_BY_SLUG[r]["menu_desc"]), ARROW)
        for r in s["related"]
    )
    faqs = "\n".join(
        '          <details class="xp-faq"%s><summary><span>%s</span><span class="xp-faq__icon" aria-hidden="true"></span></summary><div class="xp-faq__answer"><p>%s</p></div></details>'
        % (" open" if i == 0 else "", esc(q), esc(a))
        for i, (q, a) in enumerate(s["faqs"])
    )
    faq_ld = {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in s["faqs"]],
    }
    overview = "\n".join('          <p data-reveal>%s</p>' % esc(x) for x in s["overview"])
    title = "%s | Argentec Expertise" % s["name"]

    body = """
<body class="page-expertise">

  %(header)s
  <main id="main">
    <!-- ============ HERO ============ -->
    <section class="xp-hero" aria-labelledby="xp-title">
      <div class="xp-hero__image" style="background-image:url('../assets/img/expertise/%(image)s');background-position:%(pos)s" role="img" aria-label="%(alt)s"></div>
      <div class="xp-hero__inner">
        %(crumbs)s
        <p class="xp-eyebrow xp-eyebrow--light"><span class="xp-eyebrow__rule"></span>Expertise &middot; %(num)s / 07</p>
        <h1 class="xp-hero__title" id="xp-title"><span class="line"><span class="line__inner">%(title_html)s</span></span></h1>
        <p class="xp-hero__lead">%(lead)s</p>
        <div class="xp-hero__actions">
          <a class="btn btn--silver" href="../contact.html?type=development">Discuss your project</a>
          <a class="xp-link xp-link--light" href="#capabilities"><span>Capabilities &amp; scope</span>%(arrow)s</a>
        </div>
      </div>
    </section>

    <!-- ============ OVERVIEW ============ -->
    <section class="xp-section xp-section--paper" aria-labelledby="overview-title">
      <div class="xp-slab" aria-hidden="true"><span>%(num)s.A &middot; Overview</span><span>%(name)s</span></div>
      <div class="xp-inner xp-overview">
        <h2 class="xp-h2" id="overview-title" data-reveal>%(ov_title)s</h2>
        <div class="xp-overview__copy">
%(overview)s
          <ul class="xp-values">
%(values)s
          </ul>
        </div>
      </div>
    </section>

    <!-- ============ CAPABILITIES & SCOPE ============ -->
    <section class="xp-section xp-section--dark" id="capabilities" aria-labelledby="cap-title">
      <div class="xp-slab" aria-hidden="true"><span>%(num)s.B &middot; Capabilities</span><span>Scope of work</span></div>
      <div class="xp-inner">
        <h2 class="xp-h2" id="cap-title" data-reveal>Capabilities &amp; <em>scope of work</em></h2>
        <ul class="xp-caps">
%(caps)s
        </ul>
        <div class="xp-scope" data-reveal>
          <h3 class="xp-scope__title">Typical scope</h3>
          <ul class="xp-scope__list">
%(scope)s
          </ul>
        </div>
      </div>
    </section>

    <!-- ============ METHODOLOGY ============ -->
    <section class="xp-section xp-section--paper" aria-labelledby="process-title">
      <div class="xp-slab" aria-hidden="true"><span>%(num)s.C &middot; Methodology</span><span>Project lifecycle</span></div>
      <div class="xp-inner">
        <h2 class="xp-h2" id="process-title" data-reveal>How we <em>deliver</em></h2>
        <ol class="xp-steps" data-steps>
%(steps)s
        </ol>
      </div>
    </section>

    <!-- ============ APPLICATIONS ============ -->
    <section class="xp-section xp-section--navy" aria-labelledby="apps-title">
      <div class="xp-slab" aria-hidden="true"><span>%(num)s.D &middot; Applications</span><span>Where it applies</span></div>
      <div class="xp-inner">
        <h2 class="xp-h2" id="apps-title" data-reveal>Project <em>applications</em></h2>
        <ul class="xp-apps">
%(apps)s
        </ul>
      </div>
    </section>

    <!-- ============ QUALITY, SAFETY, COORDINATION ============ -->
    <section class="xp-section xp-section--paper" aria-labelledby="qsc-title">
      <div class="xp-slab" aria-hidden="true"><span>%(num)s.E &middot; Standards</span><span>Quality &middot; Safety &middot; Coordination</span></div>
      <div class="xp-inner">
        <h2 class="xp-h2" id="qsc-title" data-reveal>Quality, safety <em>&amp; coordination</em></h2>
        <ul class="xp-qsc">
          <li data-reveal style="--i:0"><span class="xp-qsc__code">Q</span><h3>Quality</h3><p>%(quality)s</p></li>
          <li data-reveal style="--i:1"><span class="xp-qsc__code">S</span><h3>Safety</h3><p>%(safety)s</p></li>
          <li data-reveal style="--i:2"><span class="xp-qsc__code">C</span><h3>Coordination</h3><p>%(coordination)s</p></li>
        </ul>
      </div>
    </section>

    <!-- ============ RELATED EXPERTISE ============ -->
    <section class="xp-section xp-section--white" aria-labelledby="related-title">
      <div class="xp-inner">
        <div class="xp-related__head">
          <h2 class="xp-h2" id="related-title" data-reveal>Related <em>expertise</em></h2>
          <a class="xp-link" href="index.html"><span>All expertise</span>%(arrow)s</a>
        </div>
        <ul class="xp-related">
%(related)s
        </ul>
      </div>
    </section>

    <!-- ============ FAQS ============ -->
    <section class="xp-section xp-section--paper" aria-labelledby="faq-title">
      <div class="xp-slab" aria-hidden="true"><span>%(num)s.F &middot; FAQs</span><span>%(name)s</span></div>
      <div class="xp-inner xp-faqs">
        <h2 class="xp-h2" id="faq-title" data-reveal>Frequently asked <em>questions</em></h2>
        <div class="xp-faqs__list">
%(faqs)s
        </div>
      </div>
    </section>

%(cta)s
  </main>

%(footer)s

  %(crumbs_ld)s
  <script type="application/ld+json">%(faq_ld)s</script>
  <script src="../js/main.js"></script>
  <script src="../js/expertise.js"></script>
</body>
</html>
""" % {
        "header": hdr.strip(), "image": s["image"], "pos": s.get("image_pos", "center"), "alt": esc(s["image_alt"]),
        "crumbs": crumbs, "num": s["num"], "title_html": accent(s["title"]), "lead": esc(s["lead"]), "arrow": ARROW,
        "name": esc(s["name"]), "ov_title": accent(s["overview_title"]), "overview": overview, "values": values,
        "caps": caps, "scope": scope, "steps": steps, "apps": apps,
        "quality": esc(s["quality"]), "safety": esc(s["safety"]), "coordination": esc(s["coordination"]),
        "related": related, "faqs": faqs,
        "cta": cta_band(p, "Planning a %s **project?**" % ("BIM-led" if s["slug"] == "bim-digital-construction" else "new"),
                        "Share your site, scope and timeline. Our team will review it and come back with next steps."),
        "footer": footer(p), "crumbs_ld": crumbs_ld,
        "faq_ld": json.dumps(faq_ld, ensure_ascii=False),
    }
    return head(title, s["meta"], "%s/expertise/%s.html" % (SITE, s["slug"]), s["image"], s.get("noindex", False)) + body


# ---------------------------------------------------------------------------
# Hub page
# ---------------------------------------------------------------------------
def hub_page(header_markup):
    p = "../"
    crumbs, crumbs_ld = breadcrumb(p, [("Home", p + "index.html"), ("Expertise", None)])
    hdr = set_menus(header_markup, p, hub=True)
    cards = "\n".join(
        '          <li class="xp-hubcard" data-reveal style="--i:%d"><a href="%s.html">'
        '<span class="xp-hubcard__num">%s</span><h2>%s</h2><p>%s</p><span class="xp-hubcard__go">Explore %s</span></a></li>'
        % (i % 3, s["slug"], s["num"], esc(s["name"]), esc(s["lead"].split(". ")[0].rstrip(".") + "."), ARROW)
        for i, s in enumerate(SERVICES)
    )
    body = """
<body class="page-expertise">

  %(header)s
  <main id="main">
    <section class="xp-hero xp-hero--hub" aria-labelledby="xp-title">
      <div class="xp-hero__image" style="background-image:url('../assets/img/expertise/expertise-hub.jpg');background-position:center 70%%" role="img" aria-label="City skyline of high-rise towers"></div>
      <div class="xp-hero__inner">
        %(crumbs)s
        <p class="xp-eyebrow xp-eyebrow--light"><span class="xp-eyebrow__rule"></span>Expertise</p>
        <h1 class="xp-hero__title" id="xp-title"><span class="line"><span class="line__inner">From first study <em>to handover.</em></span></span></h1>
        <p class="xp-hero__lead">Seven connected services for developers, commercial, industrial and institutional clients across India. Engage one, or bring them together under a single team.</p>
      </div>
    </section>

    <section class="xp-section xp-section--paper" aria-label="Services">
      <div class="xp-inner">
        <ul class="xp-hub">
%(cards)s
        </ul>
      </div>
    </section>

%(cta)s
  </main>

%(footer)s

  %(crumbs_ld)s
  <script src="../js/main.js"></script>
  <script src="../js/expertise.js"></script>
</body>
</html>
""" % {"header": hdr.strip(), "crumbs": crumbs, "cards": cards,
       "cta": cta_band(p, "Not sure which service **you need?**",
                       "Tell us about the project. We will recommend the right scope, from a single package to complete delivery."),
       "footer": footer(p), "crumbs_ld": crumbs_ld}
    return head("Expertise | Argentec Construction",
                "Argentec's construction expertise: pre-construction, civil and structural, MEP, project management, EPC, turnkey and fit-outs, and BIM for projects across India.",
                SITE + "/expertise/", "expertise-hub.jpg") + body


# ---------------------------------------------------------------------------
def review_doc():
    lines = [
        "# Expertise pages: content needing confirmation",
        "",
        "Generated by `scripts/build_expertise.py`. Before publishing, Argentec should confirm",
        "each statement below. Anything not confirmed should be removed from",
        "`scripts/expertise_content.py`, then the build re-run.",
        "",
        "Verified from the company profile (brochure) and used on the pages: MSP (Microsoft",
        "Project) scheduling, single Microsoft environment, Six Sigma-based quality checklist,",
        "SOP audits by non-executive PMCs, MIVAN aluminium formwork, MEP scope (electrical,",
        "plumbing, fire-fighting, HV/LV), turnkey delivery from engineering to completion,",
        "pre-construction risk assessment, safety and environmental commitments. EPC is taken",
        "from the client's section 2 content document.",
        "",
        "No client names, completed projects, certifications or statistics are used on these pages.",
        "",
    ]
    for s in SERVICES:
        lines.append("## %s %s (`expertise/%s.html`)" % (s["num"], s["name"], s["slug"]))
        lines.append("")
        for r in s["review"]:
            lines.append("- [ ] %s" % r)
        lines.append("")
    lines += [
        "## All pages",
        "",
        "- [ ] Hero images are from the company profile and the homepage video. Replace with",
        "      Argentec project photography when available.",
        "- [ ] \"Related projects\" are not shown because no project is mapped to a service yet.",
        "      Supply project-to-service mapping to add them.",
        "- [ ] FAQ answers describe typical practice; confirm they match Argentec's contracts.",
        "",
    ]
    return "\n".join(lines)


def main():
    # 1. Menus in root pages
    for name in ROOT_PAGES:
        path = os.path.join(ROOT, name)
        src = open(path, encoding="utf-8").read()
        out = set_menus(src, "")
        if out != src:
            open(path, "w", encoding="utf-8").write(out)
            print("menu updated:", name)

    # 2. Expertise pages
    header = prefix_links(shared_header(), "../")
    os.makedirs(os.path.join(ROOT, "expertise"), exist_ok=True)
    open(os.path.join(ROOT, "expertise", "index.html"), "w", encoding="utf-8").write(hub_page(header))
    print("wrote expertise/index.html")
    for s in SERVICES:
        open(os.path.join(ROOT, "expertise", s["slug"] + ".html"), "w", encoding="utf-8").write(service_page(s, header))
        print("wrote expertise/%s.html" % s["slug"])

    # 3. Review list
    os.makedirs(os.path.join(ROOT, "docs"), exist_ok=True)
    open(os.path.join(ROOT, "docs", "expertise-content-review.md"), "w", encoding="utf-8").write(review_doc())
    print("wrote docs/expertise-content-review.md")


if __name__ == "__main__":
    main()
