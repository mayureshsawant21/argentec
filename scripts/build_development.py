#!/usr/bin/env python3
"""Build the Development section: development/index.html.

Run from the repository root:

    python3 scripts/build_development.py

Development (Argentec's real estate business) uses its own palette: blue as
the primary colour with gold accents. Nothing is on sale yet, so the page is
positioned as "Upcoming in Navi Mumbai" and collects registrations of
interest. Copy that needs Argentec's confirmation is listed in
docs/development-content-review.md, which this script also writes.
"""
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scripts"))
from build_expertise import SITE, ARROW, esc, set_menus, footer, breadcrumb, shared_header, prefix_links  # noqa: E402

OUT = os.path.join(ROOT, "development")

PILLARS = [
    ("01", "Engineered in-house",
     "Planning, structure, services and site execution sit with the same teams that build for Mumbai's leading developers."),
    ("02", "A delivery record",
     "Over 40 projects and 10.75 million sq ft of construction across Mumbai and Thane since 2015."),
    ("03", "Run on a schedule",
     "Every project is planned in detail, tracked against its programme and checked through quality and safety audits."),
]

WHY_NAVI_MUMBAI = [
    ("A planned city", "Navi Mumbai was laid out as a planned city, with defined nodes, wide roads and open spaces."),
    ("Navi Mumbai International Airport", "A second international airport for the Mumbai region, within Navi Mumbai itself."),
    ("Atal Setu", "The Mumbai Trans Harbour Link connects Navi Mumbai directly to South Mumbai across the harbour."),
]

AUDIENCES = [
    ("homebuyer", "Homebuyers",
     "Be the first to hear about new homes, configurations and launch dates.",
     '<path d="M8 23 24 10l16 13"/><path d="M12 20v18h24V20"/><path d="M21 38v-9h6v9"/>'),
    ("broker", "Brokers &amp; channel partners",
     "Register your firm to receive project details and partner terms at launch.",
     '<rect x="9" y="14" width="30" height="22" rx="1"/><path d="M19 14v-4h10v4M9 23h30"/><path d="M22 23v4h4v-4"/>'),
    ("society", "Housing societies",
     "Planning redevelopment? Start a conversation about your building and your members' goals.",
     '<path d="M10 38V16l10-6v28M20 38V20l9 5v13M29 38V24l9 4v10"/><path d="M6 38h36"/>'),
]

REDEVELOPMENT = [
    ("Resolution &amp; feasibility", "The society agrees to explore redevelopment and studies what the plot, the rules and its members' needs allow."),
    ("Choosing a developer", "Proposals are compared on area, terms, track record and timeline, then the society selects a partner."),
    ("Agreement", "The development agreement sets out each member's new home, temporary accommodation and the schedule."),
    ("Approvals", "Plans are submitted to the planning authority, and construction starts once the permissions are in place."),
    ("Build &amp; handover", "Members move out, the new building is constructed, and they return to new homes on completion."),
]


def page_header(header_markup):
    hdr = set_menus(header_markup, "../")
    hdr = hdr.replace('<a class="biz-switch__opt is-active" href="../index.html" aria-current="true">Contracting</a>',
                      '<a class="biz-switch__opt" href="../index.html">Contracting</a>')
    hdr = hdr.replace('<a class="biz-switch__opt" href="../development/index.html">Development</a>',
                      '<a class="biz-switch__opt is-active" href="../development/index.html" aria-current="page">Development</a>')
    assert hdr.count('aria-current="page">Development</a>') == 2, "business switch not found in header"
    return hdr


def dev_footer():
    f = footer("../")
    f = f.replace('<footer class="site-footer">', '<footer class="site-footer site-footer--dev">', 1)
    f = f.replace('<a class="btn btn--navy site-footer__cta" href="../contact.html?type=development">Start a project</a>',
                  '<a class="btn dv-btn dv-btn--gold site-footer__cta" href="#register">Register interest</a>', 1)
    f = f.replace('<span data-cms="footer-note">Contracting arm of Paradigm Realty</span>', '<span data-cms="footer-dev-note">Argentec Development &middot; Navi Mumbai</span>', 1)
    return f


def head():
    title = "Upcoming in Navi Mumbai | Argentec Development"
    desc = ("Argentec, a Mumbai construction company with 40+ projects delivered, is planning residential development "
            "in Navi Mumbai. Register your interest as a homebuyer, broker or housing society.")
    return """<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>%(title)s</title>
  <meta name="description" content="%(desc)s">
  <meta name="theme-color" content="#0d2342">
  <link rel="canonical" href="%(site)s/development/">
  <link rel="icon" type="image/svg+xml" href="../assets/img/argentec-logo.svg">

  <meta property="og:type" content="website">
  <meta property="og:title" content="%(title)s">
  <meta property="og:description" content="%(desc)s">
  <meta property="og:url" content="%(site)s/development/">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/footer.css">
  <link rel="stylesheet" href="../css/development.css">
</head>""" % {"title": esc(title), "desc": esc(desc), "site": SITE}


def hero_visual():
    """Gold location rings around a pin, with the city's coordinates."""
    ticks = "".join(
        '<line x1="200" y1="22" x2="200" y2="%d" transform="rotate(%d 200 200)"/>' % (34 if a % 30 == 0 else 28, a)
        for a in range(0, 360, 10))
    return """<div class="dv-hero__visual" aria-hidden="true">
          <svg class="dv-rings" viewBox="0 0 400 400">
            <g class="dv-rings__ticks">%s</g>
            <circle class="dv-rings__c dv-rings__c--1" cx="200" cy="200" r="150" pathLength="1"/>
            <circle class="dv-rings__c dv-rings__c--2" cx="200" cy="200" r="108" pathLength="1"/>
            <circle class="dv-rings__c dv-rings__c--3" cx="200" cy="200" r="64" pathLength="1"/>
            <path class="dv-rings__cross" d="M200 40v320M40 200h320" pathLength="1"/>
            <circle class="dv-rings__pulse" cx="200" cy="200" r="22"/>
            <path class="dv-rings__pin" d="M200 222c-9-13-15-21-15-29a15 15 0 0 1 30 0c0 8-6 16-15 29Z"/>
            <circle class="dv-rings__dot" cx="200" cy="193" r="4.5"/>
          </svg>
          <p class="dv-hero__coords"><span>19.03&deg; N</span><span>73.03&deg; E</span></p>
        </div>""" % ticks


def form():
    return """    <section class="dv-register" id="register" aria-labelledby="register-title">
      <div class="dv-inner dv-register__grid">
        <div class="dv-register__intro">
          <p class="dv-eyebrow" data-reveal><span class="dv-eyebrow__rule"></span>Register interest</p>
          <h2 class="dv-h2" id="register-title" data-reveal>Hear about it <em>first.</em></h2>
          <p class="dv-lead" data-reveal>Tell us who you are and what you are looking for. We will contact you as projects are announced, and before they open to the public.</p>
          <ul class="dv-assure" data-reveal>
            <li>No sales calls before a project is registered</li>
            <li>Your details are used only by Argentec</li>
            <li>Unsubscribe at any time</li>
          </ul>
        </div>

        <form class="dv-form" id="dv-form" novalidate data-reveal>
          <fieldset class="dv-roles">
            <legend class="dv-form__label">I am a</legend>
            <label class="dv-role"><input type="radio" name="role" value="homebuyer" checked><span>Homebuyer</span></label>
            <label class="dv-role"><input type="radio" name="role" value="broker"><span>Broker</span></label>
            <label class="dv-role"><input type="radio" name="role" value="society"><span>Housing society</span></label>
          </fieldset>

          <div class="dv-fields">
            <label class="dv-field"><span>Full name</span><input name="name" autocomplete="name" required></label>
            <label class="dv-field"><span>Mobile number</span><input name="phone" type="tel" autocomplete="tel" inputmode="tel" required pattern="[0-9+ \\(\\)\\-]{10,16}"></label>
            <label class="dv-field dv-field--wide"><span>Email</span><input name="email" type="email" autocomplete="email" required></label>

            <label class="dv-field" data-role="homebuyer"><span>Looking for</span>
              <select name="configuration"><option value="">Select</option><option>1 BHK</option><option>2 BHK</option><option>3 BHK</option><option>4 BHK or larger</option><option>Not sure yet</option></select></label>
            <label class="dv-field" data-role="homebuyer"><span>Planning to buy</span>
              <select name="timeline"><option value="">Select</option><option>Within 6 months</option><option>6 to 12 months</option><option>Later than a year</option><option>Just exploring</option></select></label>

            <label class="dv-field" data-role="broker"><span>Firm name</span><input name="firm" autocomplete="organization" data-required></label>
            <label class="dv-field" data-role="broker"><span>MahaRERA agent no. <small>(optional)</small></span><input name="rera"></label>

            <label class="dv-field" data-role="society"><span>Society name</span><input name="society" data-required></label>
            <label class="dv-field" data-role="society"><span>Location</span><input name="location" placeholder="Area, city" data-required></label>
            <label class="dv-field" data-role="society"><span>Number of members</span><input name="members" type="number" min="1" inputmode="numeric"></label>
            <label class="dv-field" data-role="society"><span>Building age (years)</span><input name="age" type="number" min="0" inputmode="numeric"></label>

            <label class="dv-field dv-field--wide"><span>Anything else? <small>(optional)</small></span><textarea name="message" rows="3"></textarea></label>
          </div>

          <div class="hp" aria-hidden="true"><label>Website <input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
          <label class="dv-consent"><input type="checkbox" name="consent" required><span>I agree to be contacted by Argentec about its development projects.</span></label>
          <p class="dv-form__error" id="dv-error" role="alert" hidden></p>
          <button class="dv-btn dv-btn--gold dv-form__submit" type="submit"><span>Register interest</span>%(arrow)s</button>

          <div class="dv-done" id="dv-done" hidden tabindex="-1">
            <svg class="dv-done__mark" viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22"/><path d="M14 24.5l7 7 13-14"/></svg>
            <h3>You're on the list.</h3>
            <p>Thank you. We will be in touch as projects in Navi Mumbai are announced.</p>
            <p class="dv-done__ref">Reference <b id="dv-ref"></b></p>
            <button class="dv-link" type="button" id="dv-again"><span>Register someone else</span></button>
          </div>
        </form>
      </div>
    </section>""" % {"arrow": ARROW}


def page(header_markup):
    crumbs, crumbs_ld = breadcrumb("../", [("Home", "../index.html"), ("Development", None)])
    pillars = "\n".join(
        """          <li class="dv-pillar" data-reveal style="--i:%d">
            <span class="dv-pillar__num">%s</span>
            <h3>%s</h3>
            <p>%s</p>
          </li>""" % (i, n, t, d) for i, (n, t, d) in enumerate(PILLARS))
    why = "\n".join(
        """          <li class="dv-why__item" data-reveal style="--i:%d">
            <span class="dv-why__num">0%d</span>
            <h3>%s</h3>
            <p>%s</p>
          </li>""" % (i, i + 1, t, d) for i, (t, d) in enumerate(WHY_NAVI_MUMBAI))
    aud = "\n".join(
        """          <li class="dv-aud" data-reveal style="--i:%(i)d">
            <a href="?role=%(k)s#register" data-role-link="%(k)s">
              <svg class="dv-aud__icon" viewBox="0 0 48 48" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">%(svg)s</svg>
              <h3>%(t)s</h3>
              <p>%(d)s</p>
              <span class="dv-aud__go">Register %(arrow)s</span>
            </a>
          </li>""" % {"i": i, "k": k, "t": t, "d": d, "svg": svg, "arrow": ARROW} for i, (k, t, d, svg) in enumerate(AUDIENCES))
    steps = "\n".join(
        """          <li class="dv-step xp-step" data-reveal style="--i:%d">
            <span class="dv-step__num">%d</span>
            <h3>%s</h3>
            <p>%s</p>
          </li>""" % (i, i + 1, t, d) for i, (t, d) in enumerate(REDEVELOPMENT))
    org_ld = {"@context": "https://schema.org", "@type": "WebPage", "name": "Upcoming in Navi Mumbai",
              "url": SITE + "/development/", "about": {"@type": "Place", "name": "Navi Mumbai",
              "geo": {"@type": "GeoCoordinates", "latitude": 19.03, "longitude": 73.03}},
              "publisher": {"@type": "Organization", "name": "Argentec", "url": SITE}}
    body = """
<body class="page-dev">

  %(header)s
  <main id="main">
    <!-- ============ HERO ============ -->
    <section class="dv-hero" aria-labelledby="dv-title">
      <div class="dv-hero__grid" aria-hidden="true"></div>
      <div class="dv-inner dv-hero__inner">
        <div class="dv-hero__copy">
          %(crumbs)s
          <p class="dv-eyebrow dv-eyebrow--light"><span class="dv-eyebrow__rule"></span>Argentec Development</p>
          <h1 class="dv-hero__title" id="dv-title">
            <span class="dv-line"><span>Upcoming in</span></span>
            <span class="dv-line"><span><em>Navi Mumbai.</em></span></span>
          </h1>
          <p class="dv-hero__lead">After building over 40 projects for Mumbai's developers, Argentec is planning homes of its own, starting in Navi Mumbai. Register now to hear first.</p>
          <div class="dv-hero__actions">
            <a class="dv-btn dv-btn--gold" href="#register"><span>Register interest</span>%(arrow)s</a>
            <a class="dv-link dv-link--light" href="#redevelopment"><span>For housing societies</span>%(arrow)s</a>
          </div>
        </div>
        %(visual)s
      </div>
      <dl class="dv-inner dv-hero__facts">
        <div><dt>Location</dt><dd>Navi Mumbai</dd></div>
        <div><dt>Type</dt><dd>Residential</dd></div>
        <div><dt>Status</dt><dd>Registering interest</dd></div>
      </dl>
    </section>

    <!-- ============ STATEMENT ============ -->
    <section class="dv-statement" aria-labelledby="statement-title">
      <div class="dv-inner dv-statement__grid">
        <div>
          <p class="dv-eyebrow" data-reveal><span class="dv-eyebrow__rule"></span>Builder to developer</p>
          <h2 class="dv-h2" id="statement-title" data-reveal>From building for others to building <em>our own.</em></h2>
        </div>
        <div class="dv-statement__side">
          <p class="dv-lead" data-reveal>Since 2015, Argentec has been the contractor behind residential towers, redevelopment and rehab buildings across Mumbai and Thane. Our development projects will be designed, engineered and built by those same teams.</p>
          <dl class="dv-stats" data-reveal>
            <div><dt>Projects</dt><dd>40<span>+</span></dd></div>
            <div><dt>Built area</dt><dd>10.75 <small>Mn sq ft</small></dd></div>
            <div><dt>Building since</dt><dd>2015</dd></div>
          </dl>
        </div>
      </div>
    </section>

    <!-- ============ PILLARS ============ -->
    <section class="dv-pillars" aria-labelledby="pillars-title">
      <div class="dv-inner">
        <p class="dv-eyebrow dv-eyebrow--light" data-reveal><span class="dv-eyebrow__rule"></span>Why Argentec</p>
        <h2 class="dv-h2 dv-h2--light" id="pillars-title" data-reveal>Built by <em>the builder.</em></h2>
        <ol class="dv-pillars__list">
%(pillars)s
        </ol>
      </div>
    </section>

    <!-- ============ WHY NAVI MUMBAI ============ -->
    <section class="dv-why" aria-labelledby="why-title">
      <div class="dv-inner dv-why__grid">
        <div class="dv-why__head">
          <p class="dv-eyebrow" data-reveal><span class="dv-eyebrow__rule"></span>The location</p>
          <h2 class="dv-h2" id="why-title" data-reveal>Why <em>Navi Mumbai.</em></h2>
          <p class="dv-lead" data-reveal>A planned city with new connections to the rest of the region, and room to build well.</p>
        </div>
        <ol class="dv-why__list">
%(why)s
        </ol>
      </div>
    </section>

    <!-- ============ AUDIENCES ============ -->
    <section class="dv-auds" aria-labelledby="auds-title">
      <div class="dv-inner">
        <p class="dv-eyebrow" data-reveal><span class="dv-eyebrow__rule"></span>Who it's for</p>
        <h2 class="dv-h2" id="auds-title" data-reveal>Three ways <em>in.</em></h2>
        <ul class="dv-auds__list">
%(aud)s
        </ul>
      </div>
    </section>

    <!-- ============ REDEVELOPMENT ============ -->
    <section class="dv-redev" id="redevelopment" aria-labelledby="redev-title">
      <div class="dv-inner">
        <div class="dv-redev__head">
          <div>
            <p class="dv-eyebrow dv-eyebrow--light" data-reveal><span class="dv-eyebrow__rule"></span>For housing societies</p>
            <h2 class="dv-h2 dv-h2--light" id="redev-title" data-reveal>Redevelopment, <em>step by step.</em></h2>
          </div>
          <p class="dv-lead dv-lead--light" data-reveal>Redevelopment is one of the biggest decisions a society makes. This is how the process typically runs; the details depend on your building, your members and the approvals involved.</p>
        </div>
        <ol class="dv-steps" data-steps>
%(steps)s
        </ol>
        <a class="dv-btn dv-btn--gold dv-redev__cta" href="?role=society#register" data-role-link="society"><span>Talk to us about your society</span>%(arrow)s</a>
      </div>
    </section>

%(form)s

    <aside class="dv-note" aria-label="Disclaimer">
      <p class="dv-inner">This page does not offer any project for sale. Project details, including MahaRERA registration numbers, will be published once projects are registered.</p>
    </aside>
  </main>

%(footer)s

  <script src="../js/main.js"></script>
  <script src="../js/expertise.js"></script>
  <script src="../js/development.js"></script>
  %(crumbs_ld)s
  <script type="application/ld+json">%(ld)s</script>
</body>
</html>
""" % {"header": page_header(header_markup), "crumbs": crumbs, "arrow": ARROW, "visual": hero_visual(),
       "pillars": pillars, "why": why, "aud": aud, "steps": steps, "form": form(), "footer": dev_footer(),
       "crumbs_ld": crumbs_ld, "ld": json.dumps(org_ld, ensure_ascii=False)}
    return head() + body


def review_doc():
    lines = [
        "# Development page: statements to confirm",
        "",
        "Generated by `scripts/build_development.py`. Please confirm or correct each item",
        "with Argentec before the page goes live.",
        "",
        "- [ ] Positioning: Argentec is planning residential development in Navi Mumbai.",
        "- [ ] \"After building over 40 projects for Mumbai's developers\" (40+ from the company profile).",
        "- [ ] \"Our development projects will be designed, engineered and built by those same teams.\"",
        "- [ ] Assurances next to the form: no sales calls before a project is registered; details used only by Argentec.",
        "- [ ] Why Navi Mumbai: planned city; Navi Mumbai International Airport; Atal Setu (Mumbai Trans Harbour Link).",
        "- [ ] Redevelopment steps describe a typical process, not a commitment.",
        "- [ ] Disclaimer wording (no project offered for sale; MahaRERA numbers to follow) reviewed by legal.",
        "- [ ] Who should receive registration emails (admin panel → Settings → Email notifications).",
        "",
    ]
    return "\n".join(lines)


def main():
    header = prefix_links(shared_header(), "../")
    os.makedirs(OUT, exist_ok=True)
    open(os.path.join(OUT, "index.html"), "w", encoding="utf-8").write(page(header))
    os.makedirs(os.path.join(ROOT, "docs"), exist_ok=True)
    open(os.path.join(ROOT, "docs", "development-content-review.md"), "w", encoding="utf-8").write(review_doc())
    print("wrote development/index.html and docs/development-content-review.md")


if __name__ == "__main__":
    main()
    import cache_bust
    cache_bust.main()
