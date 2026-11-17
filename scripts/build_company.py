#!/usr/bin/env python3
"""Build the Vendors page (vendors/index.html) and the Careers page
(careers.html) from scripts/company_data.py.

Run from the repository root:

    python3 scripts/build_company.py

Both pages use the white and silver contracting palette (css/company.css)
and share the form behaviour in js/company.js.
"""
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "scripts"))
from build_expertise import SITE, ARROW, esc, set_menus, footer, breadcrumb, shared_header, prefix_links  # noqa: E402
import company_data as D  # noqa: E402

CHECK = '<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22"/><path d="M14 24.5l7 7 13-14"/></svg>'
PLUS = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2v12M2 8h12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'


def head(p, title, desc, canonical):
    return """<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>%(title)s</title>
  <meta name="description" content="%(desc)s">
  <meta name="theme-color" content="#ffffff">
  <link rel="canonical" href="%(canon)s">
  <link rel="icon" type="image/svg+xml" href="%(p)sassets/img/argentec-logo.svg">

  <meta property="og:type" content="website">
  <meta property="og:title" content="%(title)s">
  <meta property="og:description" content="%(desc)s">
  <meta property="og:url" content="%(canon)s">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="%(p)scss/style.css">
  <link rel="stylesheet" href="%(p)scss/footer.css">
  <link rel="stylesheet" href="%(p)scss/company.css">
</head>""" % {"p": p, "title": esc(title), "desc": esc(desc), "canon": canonical}


def stats(items):
    return "\n".join('          <div><dt>%s</dt><dd>%s</dd></div>' % (esc(label), esc(value)) for value, label in items)


def done_panel(title, text, again):
    return """          <div class="co-done" hidden tabindex="-1">
            %s
            <h3>%s</h3>
            <p>%s</p>
            <p class="co-done__ref">Reference <b data-ref-out></b></p>
            <button class="co-link" type="button" data-again><span>%s</span></button>
          </div>""" % (CHECK, title, text, again)


def scripts(p, backend=False):
    live = ("""
  <script src="%(p)sassets/vendor/supabase/supabase.js"></script>
  <script src="%(p)sjs/backend-config.js"></script>
  <script src="%(p)sjs/careers-backend.js"></script>""" % {"p": p}) if backend else ""
    return """  <script src="%(p)sjs/main.js"></script>
  <script src="%(p)sjs/expertise.js"></script>%(live)s
  <script src="%(p)sjs/company.js"></script>""" % {"p": p, "live": live}


# ---------------------------------------------------------------------------
# Vendors
# ---------------------------------------------------------------------------
def vendors(header_markup):
    p = "../"
    crumbs, crumbs_ld = breadcrumb(p, [("Home", p + "index.html"), ("Vendors & subcontractors", None)])
    materials = "\n".join(
        """          <li class="co-cat" data-reveal style="--i:%d">
            <h3><span>%02d</span>%s</h3>
            <ul>%s</ul>
          </li>""" % (i, i + 1, esc(group), "".join("<li>%s</li>" % esc(x) for x in items))
        for i, (group, items) in enumerate(D.MATERIALS))
    trades = "\n".join('          <li data-reveal style="--i:%d">%s</li>' % (i % 4, esc(t)) for i, t in enumerate(D.TRADES))
    steps = "\n".join(
        """          <li class="co-step xp-step" data-reveal style="--i:%d">
            <span class="co-step__num">%d</span>
            <h3>%s</h3>
            <p>%s</p>
          </li>""" % (i, i + 1, esc(t), esc(d)) for i, (t, d) in enumerate(D.VENDOR_STEPS))
    docs = "".join("<li>%s</li>" % esc(d) for d in D.VENDOR_DOCS)
    cat_chips = "\n".join(
        '              <label class="co-chip"><input type="checkbox" name="categories" value="%s"><span>%s</span></label>' % (esc(g), esc(g))
        for g, _ in D.MATERIALS)
    cat_chips += '\n              <label class="co-chip"><input type="checkbox" name="categories" value="Subcontract trades"><span>Subcontract trades</span></label>'
    city_chips = "\n".join(
        '              <label class="co-chip"><input type="checkbox" name="cities" value="%s"><span>%s</span></label>' % (esc(c), esc(c))
        for c in D.CITIES)

    body = """
<body class="page-company header-solid">

  %(header)s
  <main id="main">
    <section class="co-hero" aria-labelledby="co-title">
      <div class="co-hero__grid" aria-hidden="true"></div>
      <div class="co-inner co-hero__inner">
        %(crumbs)s
        <p class="co-eyebrow"><span class="co-eyebrow__rule"></span>Vendors &amp; subcontractors</p>
        <h1 class="co-hero__title" id="co-title">Build the supply chain <em>with us.</em></h1>
        <p class="co-hero__lead">Every Argentec site runs on a network of suppliers and specialist subcontractors. If you deliver quality on time, we would like to work with you.</p>
        <div class="co-hero__actions">
          <a class="btn btn--navy" href="#register">Register as a vendor</a>
          <a class="co-link" href="#categories"><span>What we buy</span>%(arrow)s</a>
        </div>
      </div>
      <dl class="co-inner co-stats">
%(stats)s
      </dl>
    </section>

    <section class="co-section" id="categories" aria-labelledby="cat-title">
      <div class="co-inner">
        <div class="co-head">
          <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>What we buy</p>
          <h2 class="co-h2" id="cat-title" data-reveal>Materials, equipment <em>and services.</em></h2>
        </div>
        <ul class="co-cats">
%(materials)s
        </ul>
      </div>
    </section>

    <section class="co-section co-section--silver" aria-labelledby="trade-title">
      <div class="co-inner co-split">
        <div class="co-head">
          <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>What we subcontract</p>
          <h2 class="co-h2" id="trade-title" data-reveal>Trade packages <em>on every site.</em></h2>
          <p class="co-lead" data-reveal>We award packages to specialist teams who can staff a site, meet the programme and work to our quality and safety standards.</p>
        </div>
        <ul class="co-trades">
%(trades)s
        </ul>
      </div>
    </section>

    <section class="co-section" aria-labelledby="steps-title">
      <div class="co-inner">
        <div class="co-head co-head--row">
          <div>
            <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>How it works</p>
            <h2 class="co-h2" id="steps-title" data-reveal>From registration <em>to first order.</em></h2>
          </div>
          <div class="co-docs" data-reveal>
            <p class="co-docs__title">Keep these ready</p>
            <ul>%(docs)s</ul>
          </div>
        </div>
        <ol class="co-steps" data-steps>
%(steps)s
        </ol>
      </div>
    </section>

    <section class="co-section co-section--silver co-register" id="register" aria-labelledby="reg-title">
      <div class="co-inner co-register__grid">
        <div class="co-head">
          <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>Vendor registration</p>
          <h2 class="co-h2" id="reg-title" data-reveal>Register <em>your company.</em></h2>
          <p class="co-lead" data-reveal>It takes about three minutes. Our procurement team reviews every registration and contacts you when a matching package comes up.</p>
          <p class="co-note" data-reveal>Selling a material for a one-off requirement? Use the <a href="../contact.html?type=supplier">supplier enquiry form</a> instead.</p>
        </div>

        <form class="co-form" data-ref="VEN" data-kind="vendor" novalidate data-reveal>
          <fieldset class="co-chips co-chips--single" data-min="1">
            <legend class="co-form__label">We are a</legend>
            <label class="co-chip"><input type="radio" name="vendor_type" value="Supplier" checked><span>Supplier</span></label>
            <label class="co-chip"><input type="radio" name="vendor_type" value="Subcontractor"><span>Subcontractor</span></label>
            <label class="co-chip"><input type="radio" name="vendor_type" value="Both"><span>Both</span></label>
          </fieldset>

          <div class="co-fields">
            <label class="co-field co-field--wide"><span>Company name</span><input name="company" autocomplete="organization" required></label>
            <label class="co-field"><span>Contact person</span><input name="name" autocomplete="name" required></label>
            <label class="co-field"><span>Mobile number</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" required pattern="[0-9+ ()-]{10,16}"></label>
            <label class="co-field"><span>Email</span><input name="email" type="email" autocomplete="email" required></label>
            <label class="co-field"><span>GST number</span><input name="gst" required maxlength="15" pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z][Zz][0-9A-Za-z]" placeholder="27ABCDE1234F1Z5" autocapitalize="characters" data-msg="Please enter a valid 15-character GSTIN."></label>
          </div>

          <fieldset class="co-chips" data-min="1" data-msg="Please choose at least one category.">
            <legend class="co-form__label">Categories</legend>
%(cat_chips)s
          </fieldset>
          <fieldset class="co-chips" data-min="1" data-msg="Please choose the cities you serve.">
            <legend class="co-form__label">Cities served</legend>
%(city_chips)s
          </fieldset>

          <div class="co-fields">
            <label class="co-field"><span>Years in business</span>
              <select name="years" required><option value="">Select</option><option>Less than 2</option><option>2 to 5</option><option>5 to 10</option><option>More than 10</option></select></label>
            <label class="co-field"><span>Capacity <small>(monthly supply or team size)</small></span><input name="capacity" placeholder="e.g. 500 MT a month, or 60 workers"></label>
            <label class="co-field co-field--wide"><span>Catalogue or company profile link <small>(optional)</small></span><input name="catalogue" type="url" placeholder="https://" data-msg="Please enter a full link starting with https://"></label>
            <label class="co-field co-field--wide"><span>Recent clients or projects <small>(optional)</small></span><textarea name="references" rows="3"></textarea></label>
          </div>

          <label class="co-consent"><input type="checkbox" name="consent" required><span>The details above are correct, and Argentec may contact me about procurement.</span></label>
          <p class="co-form__error" role="alert" hidden></p>
          <button class="btn btn--navy co-form__submit" type="submit">Submit registration</button>
%(done)s
        </form>
      </div>
    </section>
  </main>

%(footer)s

%(scripts)s
  %(crumbs_ld)s
</body>
</html>
""" % {"header": page_header(header_markup, p, None), "crumbs": crumbs, "arrow": ARROW, "stats": stats(D.VENDOR_STATS),
       "materials": materials, "trades": trades, "steps": steps, "docs": docs, "cat_chips": cat_chips,
       "city_chips": city_chips,
       "done": done_panel("Registration received.", "Thank you. Our procurement team will review your details and contact you when a matching package comes up.", "Register another company"),
       "footer": footer(p), "scripts": scripts(p), "crumbs_ld": crumbs_ld}
    return head(p, "Vendor & Subcontractor Registration | Argentec",
                "Supply materials or take on trade packages for Argentec's construction sites in Mumbai and Thane. See what we buy and register your company.",
                SITE + "/vendors/") + body


# ---------------------------------------------------------------------------
# Careers
# ---------------------------------------------------------------------------
def careers(header_markup):
    p = ""
    crumbs, crumbs_ld = breadcrumb(p, [("Home", "index.html"), ("Careers", None)])
    why = "\n".join(
        """          <li class="co-why" data-reveal style="--i:%d">
            <span class="co-why__num">%02d</span>
            <h3>%s</h3>
            <p>%s</p>
          </li>""" % (i, i + 1, esc(t), esc(d)) for i, (t, d) in enumerate(D.WHY_ARGENTEC))
    counts = {k: sum(1 for r in D.ROLES if r["department"] == k) for k in D.DEPARTMENTS}
    depts = "\n".join(
        """          <li class="co-dept" data-dept-name="%s" data-reveal style="--i:%d"><span>%s</span><b>%s</b></li>""" % (
            esc(name), i % 3, esc(name), ("%d open" % counts[k]) if counts[k] else "&nbsp;")
        for i, (k, name) in enumerate(D.DEPARTMENTS.items()))
    chips = ['<button class="co-filter is-active" type="button" data-filter="all" aria-pressed="true">All <span>%d</span></button>' % len(D.ROLES)]
    for k, name in D.DEPARTMENTS.items():
        if counts[k]:
            chips.append('<button class="co-filter" type="button" data-filter="%s" aria-pressed="false">%s <span>%d</span></button>' % (k, esc(name), counts[k]))
    roles = "\n".join(
        """          <li class="co-role" data-dept="%(dept)s">
            <details>
              <summary>
                <span class="co-role__title">%(title)s</span>
                <span class="co-role__meta"><span>%(dname)s</span><span>%(loc)s</span><span>%(exp)s</span></span>
                <span class="co-role__toggle">%(plus)s</span>
              </summary>
              <div class="co-role__body">
                <p class="co-role__summary">%(summary)s</p>
                <div class="co-role__cols">
                  <div><h4>What you will do</h4><ul>%(duties)s</ul></div>
                  <div><h4>What you bring</h4><ul>%(needs)s</ul></div>
                </div>
                <p class="co-role__facts">%(type)s &middot; %(loc)s &middot; %(exp)s experience</p>
                <a class="btn btn--navy co-role__apply" href="#apply" data-apply="%(slug)s">Apply for this role</a>
              </div>
            </details>
          </li>""" % {"dept": r["department"], "title": esc(r["title"]), "dname": esc(D.DEPARTMENTS[r["department"]]),
                     "loc": esc(r["location"]), "exp": esc(r["experience"]), "plus": PLUS, "summary": esc(r["summary"]),
                     "duties": "".join("<li>%s</li>" % esc(x) for x in r["duties"]),
                     "needs": "".join("<li>%s</li>" % esc(x) for x in r["needs"]), "type": esc(r["type"]), "slug": r["slug"]}
        for r in D.ROLES)
    if not D.ROLES:
        roles = '          <li class="co-empty">There are no open roles right now. Send a general application below and we will keep your resume on file.</li>'
    role_opts = "".join('<option value="%s">%s</option>' % (r["slug"], esc(r["title"])) for r in D.ROLES)
    jobs_ld = [] if D.SAMPLE_ROLES else [{
        "@context": "https://schema.org", "@type": "JobPosting", "title": r["title"], "description": r["summary"],
        "employmentType": "FULL_TIME" if r["type"] == "Full-time" else r["type"].upper(),
        "hiringOrganization": {"@type": "Organization", "name": "Argentec", "sameAs": SITE},
        "jobLocation": {"@type": "Place", "address": {"@type": "PostalAddress", "addressLocality": r["location"], "addressRegion": "Maharashtra", "addressCountry": "IN"}},
    } for r in D.ROLES]

    body = """
<body class="page-company header-solid">

  %(header)s
  <main id="main">
    <section class="co-hero" aria-labelledby="co-title">
      <div class="co-hero__grid" aria-hidden="true"></div>
      <div class="co-inner co-hero__inner">
        %(crumbs)s
        <p class="co-eyebrow"><span class="co-eyebrow__rule"></span>Careers</p>
        <h1 class="co-hero__title" id="co-title">Build your <em>career here.</em></h1>
        <p class="co-hero__lead">Engineers, planners, surveyors and site teams who take pride in getting buildings right. Join the people behind 40+ projects across Mumbai and Thane.</p>
        <div class="co-hero__actions">
          <a class="btn btn--navy" href="#openings">See open roles</a>
          <a class="co-link" href="#apply"><span>Send your resume</span>%(arrow)s</a>
        </div>
      </div>
      <dl class="co-inner co-stats">
%(stats)s
      </dl>
    </section>

    <section class="co-section" aria-labelledby="why-title">
      <div class="co-inner">
        <div class="co-head">
          <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>Why Argentec</p>
          <h2 class="co-h2" id="why-title" data-reveal>Where good builders <em>grow.</em></h2>
        </div>
        <ul class="co-whys">
%(why)s
        </ul>
      </div>
    </section>

    <section class="co-section co-section--silver" aria-labelledby="dept-title">
      <div class="co-inner co-split">
        <div class="co-head">
          <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>Teams</p>
          <h2 class="co-h2" id="dept-title" data-reveal>Six teams, <em>one site.</em></h2>
          <p class="co-lead" data-reveal>From the head office in Khar West to sites across the city, every team works toward the same handover.</p>
        </div>
        <ul class="co-depts">
%(depts)s
        </ul>
      </div>
    </section>

    <section class="co-section" id="openings" aria-labelledby="open-title">
      <div class="co-inner">
        <div class="co-head co-head--row">
          <div>
            <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>Open roles</p>
            <h2 class="co-h2" id="open-title" data-reveal>Current <em>openings.</em></h2>
          </div>
        </div>
        <div class="co-filters" role="group" aria-label="Filter by team">%(chips)s</div>
        <ul class="co-roles" data-roles>
%(roles)s
        </ul>
        <p class="co-roles__none" hidden>No openings in this team right now.</p>
      </div>
    </section>

    <section class="co-section co-section--silver co-register" id="apply" aria-labelledby="apply-title">
      <div class="co-inner co-register__grid">
        <div class="co-head">
          <p class="co-eyebrow" data-reveal><span class="co-eyebrow__rule"></span>Apply</p>
          <h2 class="co-h2" id="apply-title" data-reveal>Send us <em>your resume.</em></h2>
          <p class="co-lead" data-reveal>Apply for an open role, or send a general application and we will keep it on file for the next suitable opening.</p>
          
        </div>

        <form class="co-form" data-ref="CAR" data-kind="careers" novalidate data-reveal>
          <div class="co-fields">
            <label class="co-field co-field--wide"><span>Role</span>
              <select name="role" required><option value="general">General application</option>%(role_opts)s</select></label>
            <label class="co-field"><span>Full name</span><input name="name" autocomplete="name" required></label>
            <label class="co-field"><span>Mobile number</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" required pattern="[0-9+ ()-]{10,16}"></label>
            <label class="co-field"><span>Email</span><input name="email" type="email" autocomplete="email" required></label>
            <label class="co-field"><span>Current location</span><input name="location" autocomplete="address-level2" required></label>
            <label class="co-field"><span>Total experience</span>
              <select name="experience" required><option value="">Select</option><option>Fresher</option><option>Less than 2 years</option><option>2 to 5 years</option><option>5 to 10 years</option><option>More than 10 years</option></select></label>
            <label class="co-field"><span>Current or last company <small>(optional)</small></span><input name="company" autocomplete="organization"></label>
            <label class="co-field"><span>Current CTC <small>(&#8377; lakhs per annum)</small></span><input name="current_ctc" inputmode="decimal" required pattern="[0-9]+([.][0-9]{1,2})?" placeholder="e.g. 6.5 (0 if fresher)" data-msg="Please enter your CTC in lakhs, for example 6.5"></label>
            <label class="co-field"><span>Expected CTC <small>(&#8377; lakhs per annum)</small></span><input name="expected_ctc" inputmode="decimal" required pattern="[0-9]+([.][0-9]{1,2})?" placeholder="e.g. 8" data-msg="Please enter your CTC in lakhs, for example 8"></label>
            <div class="co-field co-field--wide co-upload">
              <span id="resume-label">Upload resume</span>
              <label class="co-upload__drop">
                <input name="resume" type="file" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-labelledby="resume-label" data-max-mb="5" data-msg="Please upload your resume as a PDF or Word file, up to 5 MB.">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                <span class="co-upload__text" data-file-name>Choose a file or drag it here</span>
                <span class="co-upload__hint">PDF or Word, up to 5 MB</span>
              </label>
            </div>
            <label class="co-field co-field--wide"><span>LinkedIn profile <small>(optional)</small></span><input name="linkedin" type="url" placeholder="https://www.linkedin.com/in/..." data-msg="Please enter a full link starting with https://"></label>
            <label class="co-field co-field--wide"><span>Any specific thoughts or questions? <small>(optional)</small></span><textarea name="message" rows="3"></textarea></label>
          </div>
          <label class="co-consent"><input type="checkbox" name="consent" required><span>Argentec may store my details for recruitment and contact me about openings.</span></label>
          <p class="co-form__error" role="alert" hidden></p>
          <button class="btn btn--navy co-form__submit" type="submit">Submit application</button>
%(done)s
        </form>
      </div>
    </section>
  </main>

%(footer)s

%(scripts)s
  %(crumbs_ld)s%(jobs_ld)s
</body>
</html>
""" % {"header": page_header(header_markup, p, "careers"), "crumbs": crumbs, "arrow": ARROW, "stats": stats(D.CAREER_STATS),
       "why": why, "depts": depts, "chips": "".join(chips), "roles": roles, "role_opts": role_opts,
       "done": done_panel("Application received.", "Thank you. Our team reviews every application and will contact you if your profile matches an opening.", "Send another application"),
       "footer": footer(p), "scripts": scripts(p, backend=True), "crumbs_ld": crumbs_ld,
       "jobs_ld": "".join('\n  <script type="application/ld+json">%s</script>' % json.dumps(j, ensure_ascii=False) for j in jobs_ld)}
    return head(p, "Careers | Argentec, Construction Company in Mumbai",
                "Build your career with Argentec: open roles for site engineers, MEP engineers, planners, quantity surveyors and safety officers in Mumbai.",
                SITE + "/careers.html") + body


def page_header(header_markup, p, current):
    hdr = set_menus(header_markup, p)
    if current == "careers":
        a = '<a class="btn btn--navy" href="%scareers.html">Careers</a>' % p
        assert a in hdr
        hdr = hdr.replace(a, '<a class="btn btn--navy" href="%scareers.html" aria-current="page">Careers</a>' % p, 1)
    return hdr


def main():
    raw = shared_header()
    os.makedirs(os.path.join(ROOT, "vendors"), exist_ok=True)
    open(os.path.join(ROOT, "vendors", "index.html"), "w", encoding="utf-8").write(vendors(prefix_links(raw, "../")))
    open(os.path.join(ROOT, "careers.html"), "w", encoding="utf-8").write(careers(raw))
    print("wrote vendors/index.html and careers.html (%d roles%s)" % (len(D.ROLES), ", sample" if D.SAMPLE_ROLES else ""))


if __name__ == "__main__":
    main()
    import cache_bust
    cache_bust.main()
