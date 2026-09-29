(function () {
  "use strict";

  var body = document.body;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    setSearch(false);
    setMenu(false);
  });
})();
