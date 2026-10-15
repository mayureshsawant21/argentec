# Argentec — Corporate Website

Static corporate website for **Argentec**, a construction company in Mumbai (argentec.com).

Plain HTML, CSS and JavaScript. There's no build step, so it can be hosted directly on GitHub Pages.

## Structure

```
index.html              Home page
about.html              About page ("Take the lift")
contact.html            Contact page: enquiry docket (supplier / home / development)
careers.html            Careers placeholder (contact page redirects careers enquiries here)
expertise/              Expertise hub + 7 service pages (GENERATED, see below)
css/style.css           Base styles, header and hero (section 1)
css/about.css           Section 2: Built for Progress (intro scene, diagonal edge)
css/overview.css        Section 2 continued: key numbers, services, sectors, closing
js/main.js              Shared: header, mobile menu, search; home-only preloader and hero video
css/about-page.css      About page: lift doors, floors, lift panel
js/about-page.js        About page: doors, floor reveals, headcount grid, lift panel
css/contact.css         Contact + careers styles
css/expertise.css       Expertise pages
css/footer.css          Shared footer (used on Expertise pages)
js/expertise.js         Expertise pages: reveals, methodology timeline
scripts/                build_expertise.py + expertise_content.py (Expertise generator)
docs/                   expertise-content-review.md (claims awaiting client approval)
js/contact.js           Contact form: type switching, validation, careers redirect, submit
js/about.js             Section 2 scroll scene (word reveal, tower drawing)
js/overview.js          Key-number count-up, sector drift, closing push-in
js/services-3d.js       Services: 3D chrome A (three.js) with hover-to-open services
assets/vendor/          three.js r128 (MIT), loaded only when Services comes near
assets/img/             Logo (SVG, taken from the company profile) and video posters
assets/video/           Hero videos: hero-desktop.mp4 (16:9), hero-mobile.mp4 (9:16)
```

## Sections

| # | Section | Status |
|---|---------|--------|
| 0 | Preloader: logo on a white screen, then the page reveals | Done |
| 1 | Header (utility row + primary nav) and full-screen video hero | Done |
| 2 | Built for Progress: pinned scroll scene with the tower elevation drawing | Done |
| 2b | Key numbers, services preview, sectors, closing statement | Done |
| Contact | Enquiry type tiles, adaptive docket form, office card; careers redirect | Done |
| Expertise | Mega menu + hub + 7 service pages, shared footer (generated) | Done, content pending approval |
| About | Lift doors, lobby, 5 floors (incl. Vision & Mission) and the roof (founder), with a lift-panel floor nav | Done |

## Expertise pages

The Expertise mega menu and the pages in `expertise/` are generated from
`scripts/expertise_content.py`. To change service content or the menu, edit that
file (or the template in `scripts/build_expertise.py`) and run:

```bash
python3 scripts/build_expertise.py
```

The script updates the Expertise menu in every root page (between
`<!-- expertise-menu -->` markers), rewrites `expertise/*.html` and refreshes
`docs/expertise-content-review.md`. It needs only Python 3, and the output is plain
HTML, so GitHub Pages needs no build step. Don't hand-edit generated pages.

**Before publishing**, go through `docs/expertise-content-review.md` with Argentec.
The BIM page carries `noindex` until its services are confirmed.

## Contact form delivery

GitHub Pages can't process forms, so the contact form posts to a form service.
Create a free endpoint (for example Formspree or Web3Forms), then set
`FORM_ENDPOINT` at the top of `js/contact.js`. Until then the form validates and
shows its "Received" screen, but **no enquiry is sent**.

`contact.html?type=supplier|home|development` opens the form preselected;
`?type=careers` redirects to `careers.html`.

## Preview locally

```bash
python3 -m http.server 8000
# open http://localhost:8000
```

## Deploy on GitHub Pages

1. Go to the repo on GitHub, then **Settings → Pages**.
2. Under **Build and deployment**, choose **Deploy from a branch**. Pick the branch and the `/ (root)` folder, then save.
3. The site will be live at `https://mayureshsawant21.github.io/argentec/`.

### Custom domain (argentec.com)

When the site is ready to go live:

1. In **Settings → Pages → Custom domain**, enter `argentec.com`. This commits a `CNAME` file.
2. At the domain registrar, add DNS records:
   - `A` records for `@`: `185.199.108.153`, `185.199.109.153`, `185.199.110.153`, `185.199.111.153`
   - `CNAME` record for `www`: `mayureshsawant21.github.io`
3. Turn on **Enforce HTTPS** once the certificate is issued.

## Re-encoding hero videos

The source clips were 1080p at about 13 MB each. They were compressed without audio for fast loading:

```bash
ffmpeg -i desktop.mp4 -an -c:v libx264 -preset slow -crf 29 -pix_fmt yuv420p -movflags +faststart assets/video/hero-desktop.mp4
ffmpeg -i mobile.mp4  -an -c:v libx264 -preset slow -crf 27 -vf scale=720:-2 -pix_fmt yuv420p -movflags +faststart assets/video/hero-mobile.mp4
```
