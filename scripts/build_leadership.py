#!/usr/bin/env python3
"""Build leadership.html: the founder's story and the leadership team.

Run from the repository root:

    python3 scripts/build_leadership.py

Styled after Argentec's leadership campaign creatives: deep charcoal-navy,
thin gold frames, the outlined "A" mark. Content comes from those creatives
and the names and designations Argentec supplied. Text and images can be
edited later in the admin panel (Pages → Leadership).
"""
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scripts"))
from build_expertise import SITE, ARROW, esc, set_menus, footer, breadcrumb, shared_header  # noqa: E402

FOUNDER = {
    "name": "Swadesh S Anand",
    "role": "Founder & CEO",
    "bio": [
        "Mr. Anand is the Founder of Argentec and Director of its Construction Vertical. He holds a Bachelor of Engineering degree from Mumbai University and a PGDM in Construction Management from NICMAR.",
        "With extensive experience in construction and logistics, he has overseen 40+ projects across Mumbai, managing construction budgets of &#8377;1,600+ crore and over 10 million sq. ft. of construction area. He also manages Paradigm Realty as a client.",
    ],
}

TRAITS = [
    ("Engineer", "Bachelor of Engineering, Mumbai University.",
     '<path d="M10 30a14 14 0 0 1 28 0"/><path d="M6 30h36v4H6z"/><path d="M24 16v-4M18 18l-2-3M30 18l2-3"/><path d="M10 42h28M14 38l-3 4M34 38l3 4"/>'),
    ("Strategist", "PGDM in Construction Management, NICMAR.",
     '<rect x="6" y="8" width="36" height="24" rx="1"/><path d="M14 40l10-8 10 8M24 32v-4"/><path d="M12 16l4 4m0-4l-4 4M30 22l4 4m0-4l-4 4"/><path d="M19 22c3-6 8-6 10-2"/><path d="M27 18l2 2-3 1"/>'),
    ("Builder", "40+ projects overseen across Mumbai.",
     '<path d="M14 42V8M14 8h26M14 12l6-4M20 8v4M40 8v8"/><path d="M38 16h4v4h-4z"/><path d="M6 42h36"/><path d="M24 42V28h14v14M28 32h2M34 32h2M28 37h2M34 37h2"/><path d="M10 42v-6h4"/>'),
]

STATS = [("40+", "Projects overseen"), ("&#8377;1,600+ Cr", "Construction budgets managed"), ("10 Mn+", "Sq. ft. of construction area")]

DEVELOPERS = [
    ("hubtown", "Hubtown"), ("ram-developers", "Ram Developers"),
    ("bilakhia-group", "Bilakhia Group"), ("paradigm-realty", "Paradigm Realty"),
]

TEAM = [
    ("Swatantra S Anand", "Chairman Emeritus", "SA", None, True),
    ("Swadesh S Anand", "Founder & CEO", "SA", "assets/img/leadership/founder-portrait.jpg", True),
    ("Sandeep Mehta", "VP Operations", "SM", None, False),
    ("Binod Singh", "VP Operations", "BS", None, False),
]

A_MARK = ('<svg class="ld-amark" viewBox="0 0 260 230" aria-hidden="true">'
          '<path pathLength="1" d="M130 6L254 224H6z"/>'
          '<path pathLength="1" d="M130 70L214 216h-44L130 146 96 206h56"/>'
          '<path pathLength="1" d="M74 216l40-70"/></svg>')


def head():
    title = "Leadership | Argentec, Construction Company in Mumbai"
    desc = ("Meet Swadesh S Anand, Founder & CEO of Argentec, and the leadership team behind 40+ construction "
            "projects across Mumbai.")
    return """<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>%(title)s</title>
  <meta name="description" content="%(desc)s">
  <meta name="theme-color" content="#0c181e">
  <link rel="canonical" href="%(site)s/leadership.html">
  <link rel="icon" type="image/svg+xml" href="assets/img/argentec-logo.svg">

  <meta property="og:type" content="profile">
  <meta property="og:title" content="%(title)s">
  <meta property="og:description" content="%(desc)s">
  <meta property="og:url" content="%(site)s/leadership.html">
  <meta property="og:image" content="%(site)s/assets/img/leadership/founder-standing.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/footer.css">
  <link rel="stylesheet" href="css/leadership.css">
</head>""" % {"title": esc(title), "desc": esc(desc), "site": SITE}


def page_header(raw):
    hdr = set_menus(raw, "")
    a = '<a class="nav-link" href="leadership.html">Leadership</a>'
    assert a in hdr, "Leadership link missing from the shared header"
    return hdr.replace(a, '<a class="nav-link is-current" href="leadership.html" aria-current="page">Leadership</a>', 1)


def page():
    crumbs, crumbs_ld = breadcrumb("", [("Home", "index.html"), ("Leadership", None)])
    traits = "\n".join(
        """          <li class="ld-trait" data-reveal style="--i:%d">
            <svg class="ld-trait__icon" viewBox="0 0 48 48" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>
            <h3>%s.</h3>
            <p>%s</p>
          </li>""" % (i, svg, t, d) for i, (t, d, svg) in enumerate(TRAITS))
    stats = "\n".join('            <div><dt>%s</dt><dd>%s</dd></div>' % (l, v) for v, l in STATS)
    logos = "\n".join(
        '          <li data-reveal style="--i:%d"><img src="assets/img/leadership/logo-%s.png" alt="%s" loading="lazy"></li>' % (i, k, esc(n))
        for i, (k, n) in enumerate(DEVELOPERS))
    cards = []
    for i, (name, role, ini, photo, lead) in enumerate(TEAM):
        media = ('<img src="%s" alt="%s" loading="lazy">' % (photo, esc(name)) if photo
                 else '<span class="ld-card__mono" aria-hidden="true">%s</span>' % ini)
        cards.append("""          <li class="ld-card%(lead)s" data-reveal style="--i:%(i)d">
            <div class="ld-card__media%(ph)s">%(media)s<span class="ld-card__frame" aria-hidden="true"></span></div>
            <div class="ld-card__body">
              <p class="ld-card__role">%(role)s</p>
              <h3 class="ld-card__name">%(name)s</h3>
            </div>
          </li>""" % {"lead": " ld-card--lead" if lead else "", "i": i, "ph": " has-photo" if photo else "",
                      "media": media, "role": esc(role), "name": esc(name)})
    person_ld = {
        "@context": "https://schema.org", "@type": "Person", "name": FOUNDER["name"], "jobTitle": FOUNDER["role"],
        "image": SITE + "/assets/img/leadership/founder-standing.jpg",
        "worksFor": {"@type": "Organization", "name": "Argentec", "url": SITE},
        "alumniOf": [{"@type": "CollegeOrUniversity", "name": "University of Mumbai"}, {"@type": "CollegeOrUniversity", "name": "NICMAR"}],
    }
    import json
    body = """
<body class="page-leadership">

  %(header)s
  <main id="main">
    <!-- ============ HERO: THE MIND BEHIND ARGENTEC ============ -->
    <section class="ld-hero" aria-labelledby="ld-title">
      <div class="ld-grain" aria-hidden="true"></div>
      <div class="ld-hero__inner">
        <div class="ld-hero__frame" aria-hidden="true"><span class="ld-arrow"></span></div>
        <figure class="ld-hero__photo"><img src="assets/img/leadership/founder-standing.jpg" alt="Swadesh S Anand, Founder &amp; CEO of Argentec" width="500" height="748"></figure>
        <div class="ld-hero__copy">
          %(crumbs)s
          <p class="ld-eyebrow"><span class="ld-eyebrow__rule"></span>Leadership</p>
          <h1 class="ld-hero__title" id="ld-title"><span class="ld-line"><span>The Mind</span></span><span class="ld-line"><span>Behind</span></span><span class="ld-line"><span>Argentec</span></span></h1>
          <span class="ld-dash" aria-hidden="true"></span>
          <p class="ld-hero__name">Swadesh S Anand <span>Founder &amp; CEO</span></p>
        </div>
        %(amark)s
      </div>
    </section>

    <!-- ============ ENGINEER. STRATEGIST. BUILDER. ============ -->
    <section class="ld-traits" aria-labelledby="traits-title">
      <div class="ld-inner">
        <h2 class="ld-h2" id="traits-title" data-reveal>Engineer. Strategist. <em>Builder.</em></h2>
        <ul class="ld-traits__list">
%(traits)s
        </ul>
        <p class="ld-cred" data-reveal>BE, Mumbai University &middot; PGDM Construction Management, NICMAR</p>
      </div>
    </section>

    <!-- ============ FOUNDER PROFILE ============ -->
    <section class="ld-profile" aria-labelledby="profile-title">
      <div class="ld-inner ld-profile__grid">
        <figure class="ld-profile__photo" data-reveal>
          <img src="assets/img/leadership/founder-seated.jpg" alt="Swadesh S Anand" width="820" height="1010" loading="lazy">
          <span class="ld-profile__frame" aria-hidden="true"></span>
        </figure>
        <div class="ld-profile__copy">
          <p class="ld-eyebrow" data-reveal><span class="ld-eyebrow__rule"></span>Founder &amp; CEO</p>
          <h2 class="ld-h2" id="profile-title" data-reveal>Swadesh S Anand</h2>
          <div class="ld-profile__bio" data-reveal>
            <p>%(bio1)s</p>
            <p>%(bio2)s</p>
          </div>
          <dl class="ld-stats" data-reveal>
%(stats)s
          </dl>
          <p class="ld-sign" data-reveal>&mdash; Swadesh S Anand</p>
        </div>
      </div>
    </section>

    <!-- ============ TRUSTED BY LEADING DEVELOPERS ============ -->
    <section class="ld-trust" aria-labelledby="trust-title">
      <div class="ld-grain" aria-hidden="true"></div>
      <div class="ld-inner">
        <div class="ld-trust__box">
          <h2 class="ld-h2 ld-h2--center" id="trust-title" data-reveal>Trusted by Leading <em>Developers</em></h2>
          <span class="ld-dash ld-dash--center" aria-hidden="true"></span>
          <ul class="ld-logos">
%(logos)s
          </ul>
        </div>
      </div>
    </section>

    <!-- ============ LEADERSHIP TEAM ============ -->
    <section class="ld-team" aria-labelledby="team-title">
      <div class="ld-inner">
        <div class="ld-team__head">
          <p class="ld-eyebrow" data-reveal><span class="ld-eyebrow__rule"></span>The team</p>
          <h2 class="ld-h2" id="team-title" data-reveal>The people behind <em>every handover.</em></h2>
        </div>
        <ul class="ld-cards">
%(cards)s
        </ul>
      </div>
    </section>

    <!-- ============ CTA ============ -->
    <section class="ld-cta" aria-labelledby="cta-title">
      <div class="ld-inner ld-cta__inner">
        <h2 class="ld-h2 ld-h2--center" id="cta-title" data-reveal>Build with the people <em>who build.</em></h2>
        <div class="ld-cta__actions" data-reveal>
          <a class="ld-btn" href="contact.html?type=development"><span>Start a conversation</span>%(arrow)s</a>
          <a class="ld-link" href="careers.html"><span>Join the team</span>%(arrow)s</a>
        </div>
      </div>
    </section>
  </main>

%(footer)s

  <script src="js/main.js"></script>
  <script src="js/expertise.js"></script>
  %(crumbs_ld)s
  <script type="application/ld+json">%(person)s</script>
</body>
</html>
""" % {"header": page_header(shared_header()), "crumbs": crumbs, "amark": A_MARK, "traits": traits,
       "bio1": FOUNDER["bio"][0], "bio2": FOUNDER["bio"][1], "stats": stats, "logos": logos, "cards": "\n".join(cards),
       "arrow": ARROW, "footer": footer(""), "crumbs_ld": crumbs_ld, "person": json.dumps(person_ld, ensure_ascii=False)}
    return head() + body


def main():
    with open(os.path.join(ROOT, "leadership.html"), "w", encoding="utf-8") as f:
        f.write(page())
    print("wrote leadership.html")


if __name__ == "__main__":
    main()
    import cache_bust
    cache_bust.main()
