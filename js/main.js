(function () {
  "use strict";

  var body = document.body;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* Home page only: hero video + logo preloader. Other pages (about.html)
     run their own entrance and add .is-ready themselves. */
  if (document.getElementById("hero-video") && document.getElementById("preloader")) {
    /* ------------------------------------------------------------------
       Hero video: portrait clip on phones, landscape clip elsewhere
       ------------------------------------------------------------------ */
    var video = document.getElementById("hero-video");
    var mobileQuery = window.matchMedia("(max-width: 767px), (orientation: portrait) and (max-width: 1023px)");
    var currentVariant = null;

    function loadVideo() {
      var variant = mobileQuery.matches ? "mobile" : "desktop";
      if (variant === currentVariant) return;
      currentVariant = variant;
      video.poster = video.dataset["poster" + (variant === "mobile" ? "Mobile" : "Desktop")];
      video.src = video.dataset["src" + (variant === "mobile" ? "Mobile" : "Desktop")];
      video.load();
      if (!reduceMotion) {
        var p = video.play();
        if (p && p.catch) p.catch(function () {});
      }
    }

    loadVideo();
    if (mobileQuery.addEventListener) mobileQuery.addEventListener("change", loadVideo);
    else if (mobileQuery.addListener) mobileQuery.addListener(loadVideo);

    /* ------------------------------------------------------------------
       Preloader: white screen + logo, ring fills as the page loads,
       then the panel lifts and the hero plays in.
       ------------------------------------------------------------------ */
    var preloader = document.getElementById("preloader");
    var ring = preloader.querySelector(".preloader__ring-progress");
    var RING_LEN = 358.1;
    var MIN_TIME = reduceMotion ? 0 : 1800;
    var MAX_TIME = 6000;
    var start = performance.now();
    var progress = 0;
    var finished = false;
    var pageLoaded = false;
    var videoReady = false;

    function setProgress(value) {
      progress = Math.max(progress, Math.min(value, 1));
      ring.style.strokeDashoffset = String(RING_LEN * (1 - progress));
    }

    // Ease the ring forward on its own so it never looks stuck
    var creep = setInterval(function () {
      if (progress < 0.85) setProgress(progress + (0.85 - progress) * 0.08);
    }, 120);

    function checkDone() {
      if (finished) return;
      if (pageLoaded && videoReady) finish();
    }

    function finish() {
      if (finished) return;
      finished = true;
      clearInterval(creep);
      setProgress(1);
      var wait = Math.max(0, MIN_TIME - (performance.now() - start));
      setTimeout(reveal, wait + (reduceMotion ? 0 : 450));
    }

    function reveal() {
      preloader.classList.add("is-leaving");
      body.classList.remove("is-loading");
      // Let the panel start lifting before the hero choreography begins
      setTimeout(function () { body.classList.add("is-ready"); }, reduceMotion ? 0 : 350);
      setTimeout(function () { preloader.classList.add("is-done"); }, reduceMotion ? 0 : 1200);
    }

    window.addEventListener("load", function () {
      pageLoaded = true;
      setProgress(0.6);
      checkDone();
    });

    function onVideoReady() {
      if (videoReady) return;
      videoReady = true;
      setProgress(progress + 0.25);
      checkDone();
    }
    if (video.readyState >= 3) onVideoReady();
    video.addEventListener("canplay", onVideoReady);
    video.addEventListener("error", onVideoReady);

    setTimeout(finish, MAX_TIME);
  }

  /* ------------------------------------------------------------------
     Header: stays fixed on every section; turns compact white glass
     once the page is scrolled
     ------------------------------------------------------------------ */
  var header = document.getElementById("site-header");
  var ticking = false;

  function onScroll() {
    header.classList.toggle("is-scrolled", window.scrollY > 40);
    ticking = false;
  }
  window.addEventListener("scroll", function () {
    if (!ticking) {
      window.requestAnimationFrame(onScroll);
      ticking = true;
    }
  }, { passive: true });
  onScroll();

  /* ------------------------------------------------------------------
     Mobile menu
     ------------------------------------------------------------------ */
  var menuToggle = document.getElementById("menu-toggle");
  var mobileMenu = document.getElementById("mobile-menu");

  function setMenu(open) {
    body.classList.toggle("menu-open", open);
    menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
    menuToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    mobileMenu.setAttribute("aria-hidden", open ? "false" : "true");
  }
  menuToggle.addEventListener("click", function () {
    setMenu(!body.classList.contains("menu-open"));
  });
  mobileMenu.addEventListener("click", function (e) {
    if (e.target.closest("a")) setMenu(false);
  });

  /* ------------------------------------------------------------------
     Search overlay
     ------------------------------------------------------------------ */
  var search = document.getElementById("search-overlay");
  var searchInput = document.getElementById("search-input");

  function setSearch(open) {
    search.classList.toggle("is-open", open);
    search.setAttribute("aria-hidden", open ? "false" : "true");
    if (open) setTimeout(function () { searchInput.focus(); }, 300);
  }
  document.getElementById("search-open").addEventListener("click", function () { setSearch(true); });
  document.getElementById("search-open-mobile").addEventListener("click", function () { setSearch(true); });
  document.getElementById("search-close").addEventListener("click", function () { setSearch(false); });
  document.getElementById("search-form").addEventListener("submit", function (e) { e.preventDefault(); });

  /* ------------------------------------------------------------------
     Expertise mega menu (desktop): opens on hover with a short grace
     period, on the chevron button, and from the keyboard. Escape closes
     and returns focus to the toggle.
     ------------------------------------------------------------------ */
  var megaItems = Array.prototype.slice.call(document.querySelectorAll("[data-mega]"));
  var hoverCapable = window.matchMedia("(hover: hover) and (pointer: fine)");

  function setMega(item, open) {
    var btn = item.querySelector(".nav-item__toggle");
    item.classList.toggle("is-open", open);
    btn.setAttribute("aria-expanded", open ? "true" : "false");
    header.classList.toggle("has-mega", megaItems.some(function (m) { return m.classList.contains("is-open"); }));
  }

  megaItems.forEach(function (item) {
    var btn = item.querySelector(".nav-item__toggle");
    var panel = item.querySelector(".mega");
    var closeTimer = null;

    btn.addEventListener("click", function () {
      var open = !item.classList.contains("is-open");
      setMega(item, open);
      if (open) {
        var first = panel.querySelector("a");
        if (first) first.focus();
      }
    });
    item.addEventListener("mouseenter", function () {
      if (!hoverCapable.matches) return;
      clearTimeout(closeTimer);
      setMega(item, true);
    });
    item.addEventListener("mouseleave", function () {
      if (!hoverCapable.matches) return;
      closeTimer = setTimeout(function () { setMega(item, false); }, 180);
    });
    // Closing when focus leaves the menu (Tab past the last link)
    item.addEventListener("focusout", function (e) {
      if (!item.contains(e.relatedTarget)) setMega(item, false);
    });
    // Arrow keys move between links inside the panel
    panel.addEventListener("keydown", function (e) {
      if (e.key !== "ArrowDown" && e.key !== "ArrowUp") return;
      var links = Array.prototype.slice.call(panel.querySelectorAll("a"));
      var i = links.indexOf(document.activeElement);
      if (i === -1) return;
      e.preventDefault();
      links[(i + (e.key === "ArrowDown" ? 1 : -1) + links.length) % links.length].focus();
    });
  });

  document.addEventListener("click", function (e) {
    megaItems.forEach(function (item) {
      if (item.classList.contains("is-open") && !item.contains(e.target)) setMega(item, false);
    });
  });

  /* Mobile menu: expandable Expertise group */
  Array.prototype.forEach.call(document.querySelectorAll(".mm-group__toggle"), function (btn) {
    var list = document.getElementById(btn.getAttribute("aria-controls"));
    btn.addEventListener("click", function () {
      var open = btn.getAttribute("aria-expanded") !== "true";
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      list.hidden = !open;
    });
  });

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    setSearch(false);
    setMenu(false);
    megaItems.forEach(function (item) {
      if (item.classList.contains("is-open")) {
        setMega(item, false);
        if (item.contains(document.activeElement)) item.querySelector(".nav-item__toggle").focus();
      }
    });
  });
})();
