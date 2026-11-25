<?php
/* The under-construction page shown before launch (see launch.php).
   $launchTs: launch time (Unix seconds), $nowTs: server time now.
   The countdown runs from the server's clock, not the visitor's. */

function render_coming_soon(int $launchTs, int $nowTs, bool $preview = false): string {
    $remainingMs = max(0, ($launchTs - $nowTs) * 1000);
    $when = date('l, j F Y', $launchTs);
    $time = date('g:i A', $launchTs);
    $v = function (string $rel) { return is_file(SITE_ROOT . '/' . $rel) ? substr(sha1_file(SITE_ROOT . '/' . $rel), 0, 8) : '0'; };
    ob_start();
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Argentec | Launching <?= h(date('j F Y', $launchTs)) ?></title>
  <meta name="description" content="Argentec, a Mumbai construction company, is building its new website. It opens on <?= h($when) ?> at <?= h($time) ?> IST.">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#0b1d36">
  <link rel="icon" type="image/svg+xml" href="/assets/img/argentec-logo.svg">
  <meta property="og:title" content="Argentec | Launching <?= h(date('j F Y', $launchTs)) ?>">
  <meta property="og:description" content="Shaping the foundation of growth. Our new website opens on <?= h($when) ?>.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500&display=swap">
  <link rel="stylesheet" href="/css/coming-soon.css?v=<?= $v('css/coming-soon.css') ?>">
</head>
<body class="cs">
<?php if ($preview): ?>
  <div class="cs-preview">Preview of the page visitors see until launch · <a href="/admin/settings.php#launch">Launch settings</a></div>
<?php endif; ?>
  <div class="cs-grid" aria-hidden="true"></div>
  <main class="cs-main">
    <section class="cs-copy">
      <img class="cs-word" src="/assets/img/argentec-logo.svg" alt="Argentec" width="64" height="64">
      <p class="cs-eyebrow"><span></span>Site under construction</p>
      <h1 class="cs-title">Something solid <em>is rising.</em></h1>
      <p class="cs-lead">Our new website is in its final fit-out. It opens on <strong><?= h($when) ?></strong> at <strong><?= h($time) ?> IST</strong>.</p>

      <div class="cs-count" role="timer" aria-live="off" aria-label="Time until launch" data-remaining="<?= (int)$remainingMs ?>">
        <div><b data-unit="d">00</b><span>Days</span></div>
        <div><b data-unit="h">00</b><span>Hours</span></div>
        <div><b data-unit="m">00</b><span>Minutes</span></div>
        <div><b data-unit="s">00</b><span>Seconds</span></div>
      </div>
      <p class="cs-live" hidden role="status">We're live. Opening the website&hellip;</p>
      <noscript><p class="cs-lead">Opening <?= h($when) ?>, <?= h($time) ?> IST.</p></noscript>

      <div class="cs-progress" aria-hidden="true"><span></span></div>
      <p class="cs-foot">Argentec Infrastructure Limited &middot; Prabhat Chambers, SV Road, Khar West, Mumbai 400052</p>
    </section>

    <figure class="cs-crane" aria-hidden="true">
      <svg viewBox="0 0 640 560" role="presentation">
        <defs>
          <pattern id="lattice" width="18" height="18" patternUnits="userSpaceOnUse">
            <path d="M0 0L18 18M18 0L0 18" stroke="currentColor" stroke-width="1" fill="none"/>
          </pattern>
          <pattern id="jib" width="20" height="22" patternUnits="userSpaceOnUse">
            <path d="M0 22L10 0L20 22" stroke="currentColor" stroke-width="1" fill="none"/>
          </pattern>
        </defs>
        <!-- ground and the building rising under the crane -->
        <path class="cs-ground" d="M10 540H630"/>
        <g class="cs-building">
          <rect x="300" y="430" width="190" height="110"/>
          <path d="M300 466H490M300 502H490M338 430V540M376 430V540M414 430V540M452 430V540"/>
          <path class="cs-rebar" d="M308 430V404M346 430V404M384 430V404M422 430V404M460 430V404M482 430V404"/>
        </g>
        <!-- mast -->
        <g class="cs-steel">
          <rect x="150" y="128" width="30" height="412" fill="url(#lattice)"/>
          <path d="M150 128V540M180 128V540"/>
          <!-- cab -->
          <rect class="cs-cab" x="180" y="110" width="30" height="26" rx="2"/>
          <!-- apex -->
          <path d="M150 100L165 40L180 100"/>
          <!-- counter-jib with counterweight -->
          <rect x="40" y="100" width="110" height="18" fill="url(#jib)"/>
          <path d="M40 100H150M40 118H150"/>
          <rect class="cs-weight" x="46" y="118" width="40" height="34"/>
          <!-- jib -->
          <rect x="180" y="100" width="420" height="22" fill="url(#jib)"/>
          <path d="M180 100H600M180 122H600M600 100V122"/>
          <!-- tie bars -->
          <path class="cs-tie" d="M165 40L600 100M165 40L40 100"/>
          <!-- aviation light -->
          <circle class="cs-beacon" cx="165" cy="38" r="4"/>
        </g>
        <!-- trolley, cable and the logo, moving along the jib -->
        <g class="cs-trolley">
          <rect class="cs-trolley__box" x="-14" y="122" width="28" height="10" rx="2"/>
          <g class="cs-load">
            <line class="cs-cable" x1="-5" y1="132" x2="-5" y2="300"/>
            <line class="cs-cable" x1="5" y1="132" x2="5" y2="300"/>
            <path class="cs-hook" d="M-10 300h20l-4 10h-12z"/>
            <path class="cs-sling" d="M0 310L-34 344M0 310L34 344"/>
            <circle class="cs-badge" cx="0" cy="392" r="56"/>
            <image href="/assets/img/argentec-logo.svg" x="-50" y="342" width="100" height="100"/>
          </g>
        </g>
      </svg>
    </figure>
  </main>

  <script>
  (function () {
    var box = document.querySelector(".cs-count");
    var remaining = +box.getAttribute("data-remaining");
    // count from the moment the page arrived, using the server's remaining time
    var start = (window.performance && performance.now) ? performance.now() : Date.now();
    var now = function () { return (window.performance && performance.now) ? performance.now() : Date.now(); };
    var end = start + remaining;
    var units = { d: box.querySelector('[data-unit="d"]'), h: box.querySelector('[data-unit="h"]'), m: box.querySelector('[data-unit="m"]'), s: box.querySelector('[data-unit="s"]') };
    var bar = document.querySelector(".cs-progress span");
    var done = false;
    function pad(n) { return (n < 10 ? "0" : "") + n; }
    function set(el, v) { if (el.textContent !== v) { el.textContent = v; el.classList.remove("tick"); void el.offsetWidth; el.classList.add("tick"); } }
    function tick() {
      var left = Math.max(0, end - now());
      var s = Math.floor(left / 1000);
      set(units.d, pad(Math.floor(s / 86400)));
      set(units.h, pad(Math.floor(s % 86400 / 3600)));
      set(units.m, pad(Math.floor(s % 3600 / 60)));
      set(units.s, pad(s % 60));
      // "construction progress": fills over the final 14 days
      if (bar) bar.style.transform = "scaleX(" + Math.max(0.04, Math.min(1, 1 - left / (14 * 86400000))).toFixed(4) + ")";
      if (left <= 0 && !done) {
        done = true;
        document.querySelector(".cs-live").hidden = false;
        document.body.classList.add("is-live");
        // the server opens the site at the same moment; reload to show it
        setTimeout(function () { window.location.reload(); }, 1800);
        return;
      }
      if (!done) setTimeout(tick, 1000 - (left % 1000) + 5);
    }
    tick();
    // after the tab sleeps (phones), re-sync with the server
    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "visible" && !done && end - now() < 60000) window.location.reload();
    });
  })();
  </script>
</body>
</html>
<?php
    return (string)ob_get_clean();
}
