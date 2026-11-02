#!/usr/bin/env python3
"""Build the Projects section from scripts/projects_data.py.

    python3 scripts/build_projects.py

Writes projects/index.html and projects/<slug>.html. Reuses the header,
menus, breadcrumb and footer components from build_expertise.py, so run
this after build_expertise.py if the header changes. Output is static HTML
for GitHub Pages.
"""
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scripts"))
from build_expertise import (SITE, ARROW, esc, accent, set_menus, footer, breadcrumb,  # noqa: E402
                             shared_header, prefix_links)
from projects_data import PROJECTS, REGIONS, HEADLINE, AS_OF  # noqa: E402

STAGE_LABEL = {"completed": "Completed", "construction": "Under construction", "future": "Future development"}


# ---------------------------------------------------------------------------
# Derived figures
# ---------------------------------------------------------------------------
def indian(n):
    """1075447 -> '10,75,447' (Indian digit grouping)."""
    s = str(int(n))
    if len(s) <= 3:
        return s
    head, tail = s[:-3], s[-3:]
    head = re.sub(r"(\d)(?=(\d{2})+$)", r"\1,", head)
    return head + "," + tail


def floors(config):
    nums = [int(x) for x in re.findall(r"\d+", config)]
    return max(nums) if nums else 0


def project_area(p):
    return p.get("area") or sum(b[2] for b in p["buildings"])


def project_stage(p):
    stages = {b[3] for b in p["buildings"]}
    if stages == {"completed"}:
        return "completed"
    if stages == {"future"}:
        return "future"
    return "construction"


def counts(p):
    bs = p["buildings"]
    return {k: sum(1 for b in bs if b[3] == k) for k in STAGE_LABEL}


def tallest(p):
    return max(p["buildings"], key=lambda b: floors(b[1]))


def placeholder(p):
    n = len(p["buildings"])
    top = floors(tallest(p)[1])
    if n == 1:
        return 1
    if top <= 16:
        return 4
    if n == 2:
        return 2
    return 3


def stage_summary(p):
    c = counts(p)
    n = len(p["buildings"])
    st = project_stage(p)
    if st == "completed":
        return "All %d complete" % n if n > 1 else "Complete"
    if st == "future":
        return "%d tower%s planned" % (n, "s" if n > 1 else "")
    return "%d of %d complete" % (c["completed"], n)


def portfolio_totals():
    bs = [b for p in PROJECTS for b in p["buildings"]]
    future_area = sum(project_area(p) for p in PROJECTS if project_stage(p) == "future")
    return {
        "projects": len(PROJECTS),
        "area": sum(project_area(p) for p in PROJECTS),
        "buildings": len(bs),
        "completed": sum(1 for b in bs if b[3] == "completed"),
        "construction": sum(1 for b in bs if b[3] == "construction"),
        "future_area": future_area,
    }


# ---------------------------------------------------------------------------
# Shared bits
# ---------------------------------------------------------------------------
def page_header(header_markup):
    hdr = set_menus(header_markup, "../")
    hdr = hdr.replace('<a class="nav-link" href="../projects/index.html">Projects</a>',
                      '<a class="nav-link is-current" href="../projects/index.html" aria-current="page">Projects</a>')
    return hdr


def head(title, description, canonical, robots=""):
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

  <meta property="og:type" content="website">
  <meta property="og:title" content="%(title)s">
  <meta property="og:description" content="%(desc)s">
  <meta property="og:url" content="%(canon)s">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/projects.css">
  <link rel="stylesheet" href="../css/footer.css">
</head>""" % {"title": esc(title), "desc": esc(description), "canon": canonical, "robots": robots}


def badge(stage):
    return '<span class="pj-badge pj-badge--%s">%s</span>' % (stage, STAGE_LABEL[stage])


def figure(src, alt):
    return ('<figure class="pj-figure"><img src="%s" alt="%s" width="800" height="600" loading="lazy">'
            '<figcaption>Representative image</figcaption></figure>') % (src, esc(alt))


def cta():
    return """    <section class="pj-cta" aria-labelledby="cta-title">
      <div class="pj-inner pj-cta__inner">
        <div>
          <p class="pj-eyebrow" data-reveal>Project enquiry</p>
          <h2 class="pj-cta__title" id="cta-title" data-reveal>Planning your next <em>project?</em></h2>
        </div>
        <div class="pj-cta__side" data-reveal>
          <p>Share your site, scope and timeline. Our team will review it and come back with next steps.</p>
          <div class="pj-cta__actions">
            <a class="pj-btn" href="../contact.html?type=development">Discuss your project</a>
            <a class="pj-link" href="../expertise/index.html"><span>Our expertise</span>%s</a>
          </div>
        </div>
      </div>
    </section>""" % ARROW


def scripts():
    return """  <script src="../js/main.js"></script>
  <script src="../js/expertise.js"></script>
  <script src="../js/projects.js"></script>"""


# ---------------------------------------------------------------------------
# Hub
# ---------------------------------------------------------------------------
def hub(header_markup):
    t = portfolio_totals()
    crumbs, crumbs_ld = breadcrumb("../", [("Home", "../index.html"), ("Projects", None)])
    stage_counts = {k: sum(1 for p in PROJECTS if project_stage(p) == k) for k in STAGE_LABEL}
    region_counts = {k: sum(1 for p in PROJECTS if p["region"] == k) for k in REGIONS}

    cards = []
    for i, p in enumerate(PROJECTS):
        st = project_stage(p)
        c = counts(p)
        n = len(p["buildings"])
        done = c["completed"] / n
        cards.append("""          <li class="pj-card" data-stage="%(st)s" data-region="%(reg)s" data-reveal style="--i:%(k)d">
            <a href="%(slug)s.html">
              <div class="pj-card__media">
                <img src="../assets/img/projects/placeholder-%(img)d.svg" alt="" width="800" height="600" loading="lazy">
                %(badge)s
                <span class="pj-card__note">Representative image</span>
              </div>
              <div class="pj-card__body">
                <span class="pj-card__num">%(num)02d</span>
                <h2 class="pj-card__name">%(name)s</h2>
                <p class="pj-card__place">%(place)s &middot; %(region)s</p>
                <dl class="pj-card__facts">
                  <div><dt>Area</dt><dd>%(area)s <small>sq ft</small></dd></div>
                  <div><dt>Buildings</dt><dd>%(n)d</dd></div>
                  <div><dt>Tallest</dt><dd>%(tall)s</dd></div>
                </dl>
                <div class="pj-progress" aria-label="%(summary)s"><span style="--done:%(done).3f"></span></div>
                <p class="pj-card__summary">%(summary)s</p>
              </div>
            </a>
          </li>""" % {"st": st, "reg": p["region"], "k": i % 3, "slug": p["slug"], "img": placeholder(p),
                     "badge": badge(st), "num": i + 1, "name": esc(p["name"]), "place": esc(p["area_name"]),
                     "region": REGIONS[p["region"]], "area": indian(project_area(p)), "n": n,
                     "tall": esc(tallest(p)[1]), "done": done, "summary": stage_summary(p)})

    stage_chips = ['<button class="pj-chip is-active" type="button" data-filter-stage="all" aria-pressed="true">All <span>%d</span></button>' % len(PROJECTS)]
    for k, label in STAGE_LABEL.items():
        stage_chips.append('<button class="pj-chip" type="button" data-filter-stage="%s" aria-pressed="false">%s <span>%d</span></button>' % (k, label, stage_counts[k]))
    region_chips = ['<button class="pj-chip pj-chip--quiet is-active" type="button" data-filter-region="all" aria-pressed="true">All locations</button>']
    for k, label in REGIONS.items():
        region_chips.append('<button class="pj-chip pj-chip--quiet" type="button" data-filter-region="%s" aria-pressed="false">%s <span>%d</span></button>' % (k, label, region_counts[k]))

    item_ld = {"@context": "https://schema.org", "@type": "ItemList", "name": "Argentec project portfolio",
               "itemListElement": [{"@type": "ListItem", "position": i + 1, "name": p["name"],
                                    "url": "%s/projects/%s.html" % (SITE, p["slug"])} for i, p in enumerate(PROJECTS)]}

    body = """
<body class="page-projects header-solid">

  %(header)s
  <main id="main">
    <section class="pj-hero" aria-labelledby="pj-title">
      <div class="pj-inner">
        %(crumbs)s
        <p class="pj-eyebrow">Project portfolio &middot; Status as of %(asof)s</p>
        <h1 class="pj-hero__title" id="pj-title">Built across Mumbai <em>&amp; Thane.</em></h1>
        <p class="pj-hero__lead">Residential towers, rehab and sale buildings, and redevelopment, delivered and under way for leading developers across the city.</p>
      </div>
      <div class="pj-inner">
        <dl class="pj-stats">
          <div><dt>Projects</dt><dd>%(projects)s</dd></div>
          <div><dt>Portfolio area</dt><dd>%(area)s <small>Mn sq ft</small></dd></div>
          <div><dt>Buildings &amp; wings</dt><dd>%(buildings)d</dd></div>
          <div><dt>Completed</dt><dd>%(completed)d</dd></div>
          <div><dt>Under construction</dt><dd>%(construction)d</dd></div>
          <div><dt>Future development</dt><dd>%(future).2f <small>Mn sq ft</small></dd></div>
        </dl>
      </div>
    </section>

    <section class="pj-list" aria-labelledby="list-title">
      <div class="pj-inner">
        <div class="pj-list__head">
          <h2 class="pj-h2" id="list-title">Featured <em>portfolio</em></h2>
          <p class="pj-list__count" aria-live="polite"><span id="pj-count">%(n)d</span> of %(n)d projects</p>
        </div>
        <div class="pj-filters" role="group" aria-label="Filter projects">
          <div class="pj-filters__row" role="group" aria-label="By stage">%(stage_chips)s</div>
          <div class="pj-filters__row" role="group" aria-label="By location">%(region_chips)s</div>
        </div>
        <ul class="pj-grid" id="pj-grid">
%(cards)s
        </ul>
        <p class="pj-empty" id="pj-empty" hidden>No projects match these filters.</p>
        <p class="pj-footnote">Areas are in sq ft as reported in the October 2026 portfolio. Where one figure covers several buildings, it is shown once for the project. Images are representative.</p>
      </div>
    </section>

%(cta)s
  </main>

%(footer)s

  %(crumbs_ld)s
  <script type="application/ld+json">%(item_ld)s</script>
%(scripts)s
</body>
</html>
""" % {"header": page_header(header_markup).strip(), "crumbs": crumbs, "asof": AS_OF,
       "projects": HEADLINE["projects"], "area": HEADLINE["area"], "buildings": t["buildings"],
       "completed": t["completed"], "construction": t["construction"], "future": t["future_area"] / 1e6,
       "n": len(PROJECTS), "stage_chips": "".join(stage_chips), "region_chips": "".join(region_chips),
       "cards": "\n".join(cards), "cta": cta(), "footer": footer("../"), "crumbs_ld": crumbs_ld,
       "item_ld": json.dumps(item_ld, ensure_ascii=False), "scripts": scripts()}
    return head("Projects | Argentec Construction, Mumbai",
                "Argentec's project portfolio: %d projects and %s Mn sq ft across Mumbai and Thane, including completed, under-construction and future developments." % (len(PROJECTS), HEADLINE["area"]),
                SITE + "/projects/") + body


# ---------------------------------------------------------------------------
# Detail
# ---------------------------------------------------------------------------
def detail(i, p, header_markup):
    st = project_stage(p)
    c = counts(p)
    n = len(p["buildings"])
    crumbs, crumbs_ld = breadcrumb("../", [("Home", "../index.html"), ("Projects", "index.html"), (p["name"], None)])
    shared_area = p.get("area")

    rows = []
    for b in p["buildings"]:
        area = indian(b[2]) if b[2] else "&mdash;"
        rows.append("""            <tr data-stage="%s">
              <th scope="row">%s</th>
              <td data-label="Configuration">%s</td>
              <td data-label="Area (sq ft)" class="num">%s</td>
              <td data-label="Stage">%s</td>
              <td data-label="Current status">%s</td>
            </tr>""" % (b[3], esc(b[0]), esc(b[1]), area, badge(b[3]), esc(b[4])))

    prev_p = PROJECTS[i - 1]
    next_p = PROJECTS[(i + 1) % len(PROJECTS)]
    rehab = any("rehab" in b[0].lower() for b in p["buildings"])
    sale = any("sale" in b[0].lower() for b in p["buildings"])
    mix = "Rehab and sale buildings" if rehab and sale else ("Rehab buildings" if rehab else ("Sale buildings" if sale else ("Single building" if n == 1 else "%d buildings" % n)))

    lead = {
        "completed": "%s in %s is complete, with every building handed over or occupancy certificate received." % (p["name"], p["area_name"]),
        "construction": "%s in %s is under construction: %d of %d building%s complete, with work progressing on the rest." % (p["name"], p["area_name"], c["completed"], n, "s" if n > 1 else ""),
        "future": "%s in %s is a planned future development of %d tower%s." % (p["name"], p["area_name"], n, "s" if n > 1 else ""),
    }[st]
    if st == "construction" and c["completed"] == 0:
        lead = "%s in %s is under construction across %d building%s." % (p["name"], p["area_name"], n, "s" if n > 1 else "")

    body = """
<body class="page-projects header-solid">

  %(header)s
  <main id="main">
    <section class="pj-detail-hero" aria-labelledby="pj-title">
      <div class="pj-inner pj-detail-hero__grid">
        <div class="pj-detail-hero__copy">
          %(crumbs)s
          <p class="pj-eyebrow">Project &middot; %(num)02d / %(total)02d</p>
          <h1 class="pj-hero__title" id="pj-title">%(name)s</h1>
          <p class="pj-detail-hero__place">%(address)s</p>
          <div class="pj-detail-hero__status">%(badge)s<span>%(summary)s</span></div>
          <p class="pj-hero__lead">%(lead)s</p>
        </div>
        %(figure)s
      </div>
    </section>

    <section class="pj-facts-wrap" aria-label="Key facts">
      <div class="pj-inner">
        <dl class="pj-facts">
          <div><dt>Total area</dt><dd>%(area)s <small>sq ft</small></dd></div>
          <div><dt>Buildings / wings</dt><dd>%(n)d</dd></div>
          <div><dt>Tallest</dt><dd>%(tall)s</dd></div>
          <div><dt>Mix</dt><dd class="pj-facts__text">%(mix)s</dd></div>
          <div><dt>Location</dt><dd class="pj-facts__text">%(place)s<small>%(region)s</small></dd></div>
        </dl>
        <div class="pj-progress pj-progress--lg" aria-label="%(summary)s"><span style="--done:%(done).3f"></span></div>
        <p class="pj-progress__legend"><span><b>%(c_done)d</b> completed</span><span><b>%(c_uc)d</b> under construction</span><span><b>%(c_fut)d</b> future</span></p>
      </div>
    </section>

    <section class="pj-buildings" aria-labelledby="b-title">
      <div class="pj-inner">
        <h2 class="pj-h2" id="b-title">Buildings <em>&amp; status</em></h2>
        <p class="pj-buildings__note">Status as of %(asof)s.%(shared)s</p>
        <div class="pj-table-wrap">
          <table class="pj-table">
            <thead><tr><th scope="col">Building / wing</th><th scope="col">Configuration</th><th scope="col" class="num">Area (sq ft)</th><th scope="col">Stage</th><th scope="col">Current status</th></tr></thead>
            <tbody>
%(rows)s
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <nav class="pj-pager" aria-label="More projects">
      <div class="pj-inner pj-pager__inner">
        <a class="pj-pager__link" href="%(prev_slug)s.html"><span class="pj-pager__dir">Previous project</span><span class="pj-pager__name">%(prev_name)s</span></a>
        <a class="pj-pager__all" href="index.html">All projects</a>
        <a class="pj-pager__link pj-pager__link--next" href="%(next_slug)s.html"><span class="pj-pager__dir">Next project</span><span class="pj-pager__name">%(next_name)s</span></a>
      </div>
    </nav>

%(cta)s
  </main>

%(footer)s

  %(crumbs_ld)s
%(scripts)s
</body>
</html>
""" % {"header": page_header(header_markup).strip(), "crumbs": crumbs, "num": i + 1, "total": len(PROJECTS),
       "name": esc(p["name"]), "address": esc(p["address"]), "badge": badge(st), "summary": stage_summary(p),
       "lead": esc(lead), "figure": figure("../assets/img/projects/placeholder-%d.svg" % placeholder(p), "Illustration of a residential building"),
       "area": indian(project_area(p)), "n": n, "tall": esc(tallest(p)[1]), "mix": mix,
       "place": esc(p["area_name"]), "region": REGIONS[p["region"]], "done": c["completed"] / n,
       "c_done": c["completed"], "c_uc": c["construction"], "c_fut": c["future"], "asof": AS_OF,
       "shared": " The project area of %s sq ft covers all its buildings." % indian(shared_area) if shared_area else "",
       "rows": "\n".join(rows), "prev_slug": prev_p["slug"], "prev_name": esc(prev_p["name"]),
       "next_slug": next_p["slug"], "next_name": esc(next_p["name"]), "cta": cta(), "footer": footer("../"),
       "crumbs_ld": crumbs_ld, "scripts": scripts()}
    desc = "%s, %s: %s, %d building%s, %s sq ft. %s. Argentec construction project." % (
        p["name"], p["area_name"], STAGE_LABEL[st].lower(), n, "s" if n > 1 else "", indian(project_area(p)), stage_summary(p))
    return head("%s, %s | Argentec Projects" % (p["name"], p["area_name"]), desc,
                "%s/projects/%s.html" % (SITE, p["slug"])) + body


def main():
    header = prefix_links(shared_header(), "../")
    os.makedirs(os.path.join(ROOT, "projects"), exist_ok=True)
    open(os.path.join(ROOT, "projects", "index.html"), "w", encoding="utf-8").write(hub(header))
    for i, p in enumerate(PROJECTS):
        open(os.path.join(ROOT, "projects", p["slug"] + ".html"), "w", encoding="utf-8").write(detail(i, p, header))
    t = portfolio_totals()
    print("wrote projects/index.html + %d project pages" % len(PROJECTS))
    print("totals:", t)


if __name__ == "__main__":
    main()
