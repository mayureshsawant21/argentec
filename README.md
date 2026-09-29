# Argentec — Corporate Website

Static corporate website for **Argentec**, a construction company in Mumbai (argentec.com).

Plain HTML, CSS and JavaScript. There's no build step, so it can be hosted directly on GitHub Pages.

## Structure

```
index.html              Page markup (sections are added one at a time)
css/style.css           All styles
js/main.js              Preloader, hero video, header, mobile menu, search
assets/img/             Logo (SVG, taken from the company profile) and video posters
assets/video/           Hero videos: hero-desktop.mp4 (16:9), hero-mobile.mp4 (9:16)
```

## Sections

| # | Section | Status |
|---|---------|--------|
| 0 | Preloader: logo on a white screen, then the page reveals | Done |
| 1 | Header (utility row + primary nav) and full-screen video hero | Done |

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
