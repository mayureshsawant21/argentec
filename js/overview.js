/* Section 2 (continued): key numbers, services, sectors, closing.
   One scroll loop writes progress values; CSS does the drawing. */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var wide = window.matchMedia("(min-width: 1024px)");

  var figures = document.querySelector(".figures");
  var services = document.querySelector(".services");
  var sectors = document.querySelector(".sectors");
  var closing = document.querySelector(".closing");
  if (!figures || !services || !sectors || !closing) return;

  function clamp(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }

  /* ------------------------------------------------------------------
     Blocks announce themselves once as they enter
     ------------------------------------------------------------------ */
  var blocks = [figures, services, sectors, closing];

  /* ------------------------------------------------------------------
     Key numbers: count up when switched on
     ------------------------------------------------------------------ */
  var figureEls = Array.prototype.slice.call(figures.querySelectorAll("[data-figure]"));
  var scale = figures.querySelector(".figures__scale");

  function countUp(el) {
    var num = el.querySelector("[data-count]");
    var target = parseInt(num.getAttribute("data-count"), 10);
    var start = null;
    var DURATION = 1600;
    function step(ts) {
      if (start === null) start = ts;
      var t = clamp((ts - start) / DURATION);
      var eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
      num.textContent = Math.round(target * eased).toLocaleString("en-US");
      if (t < 1) window.requestAnimationFrame(step);
    }
    num.textContent = "0";
    window.requestAnimationFrame(step);
  }

  function switchOn(el) {
    if (el.classList.contains("is-on")) return;
    el.classList.add("is-on");
    countUp(el);
  }

  /* ------------------------------------------------------------------
     Services
     ------------------------------------------------------------------ */
  var svcEls = Array.prototype.slice.call(services.querySelectorAll("[data-svc]"));
  var svcList = services.querySelector(".services__list");
  var activeSvc = null;

  function setActive(el) {
    if (el === activeSvc) return;
    if (activeSvc) activeSvc.classList.remove("is-active");
    if (el) el.classList.add("is-active");
    activeSvc = el;
  }

  /* ------------------------------------------------------------------
     Diagonal edges on these blocks (the section-2 intro edge is handled
     in about.js)
     ------------------------------------------------------------------ */
  var edges = Array.prototype.slice.call(document.querySelectorAll("[data-scroll-edge]"));

  if (reduceMotion) {
    blocks.forEach(function (b) { b.classList.add("in-view"); });
    figureEls.forEach(function (f) { f.classList.add("is-on"); });
    figures.style.setProperty("--fill", "1");
    services.style.setProperty("--fill", "1");
    svcEls.forEach(function (s) { s.classList.add("is-active"); });
    return;
  }

  /* ------------------------------------------------------------------
     Scroll loop
     ------------------------------------------------------------------ */
  var ticking = false;

  function update() {
    ticking = false;
    var vh = window.innerHeight;

    blocks.forEach(function (b) {
      if (!b.classList.contains("in-view") && b.getBoundingClientRect().top < vh * 0.78) b.classList.add("in-view");
    });

    // Key numbers
    if (wide.matches) {
      var sr = scale.getBoundingClientRect();
      var fill = clamp((vh * 0.85 - sr.top) / (vh * 0.45));
      figures.style.setProperty("--fill", fill.toFixed(4));
      figureEls.forEach(function (f, i) {
        if (fill >= (i + 0.05) / figureEls.length) switchOn(f);
      });
    } else {
      figureEls.forEach(function (f) {
        if (f.getBoundingClientRect().top < vh * 0.85) switchOn(f);
      });
    }

    // Services: the row crossing the middle of the screen is active
    var lr = svcList.getBoundingClientRect();
    if (lr.bottom > 0 && lr.top < vh) {
      var mid = vh * 0.5;
      var best = null;
      var bestDist = Infinity;
      svcEls.forEach(function (s) {
        var r = s.getBoundingClientRect();
        var d = Math.abs(r.top + Math.min(r.height, 90) / 2 - mid);
        if (d < bestDist) { bestDist = d; best = s; }
      });
      if (lr.top > mid) best = svcEls[0];
      setActive(best);
      services.style.setProperty("--fill", clamp((mid - lr.top) / (lr.height - 60)).toFixed(4));
    }

    // Sectors drift
    var kr = sectors.getBoundingClientRect();
    if (kr.bottom > 0 && kr.top < vh) {
      sectors.style.setProperty("--drift", clamp((vh - kr.top) / (vh + kr.height)).toFixed(4));
    }

    // Closing push-in
    var cr = closing.getBoundingClientRect();
    if (cr.bottom > 0 && cr.top < vh) {
      closing.style.setProperty("--zoom", clamp((vh - cr.top) / (vh + cr.height * 0.5)).toFixed(4));
    }

    // Edges level out as their block rises
    edges.forEach(function (e) {
      var r = e.parentElement.getBoundingClientRect();
      e.style.setProperty("--tilt", clamp((r.top - vh * 0.12) / (vh * 0.78)).toFixed(4));
    });
  }

  function requestUpdate() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(update);
    }
  }

  window.addEventListener("scroll", requestUpdate, { passive: true });
  window.addEventListener("resize", requestUpdate);
  update();
})();
