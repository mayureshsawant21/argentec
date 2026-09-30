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
     Services: stacking cards
     ------------------------------------------------------------------ */
  var stackItems = Array.prototype.slice.call(services.querySelectorAll("[data-stack]"));

  /* ------------------------------------------------------------------
     Diagonal edges on these blocks (the section-2 intro edge is handled
     in about.js)
     ------------------------------------------------------------------ */
  var edges = Array.prototype.slice.call(document.querySelectorAll("[data-scroll-edge]")).map(function (el) {
    return { el: el, shape: el.querySelector(".edge__shape path"), line: el.querySelector(".edge__line path"), last: -1 };
  });
  // Mirrored diagonal (high on the left). Geometry is redrawn, never stretched,
  // so the hairline stays solid while it levels out.
  function setEdge(e, tilt) {
    var t = Math.round(tilt * 1000) / 1000;
    if (t === e.last) return;
    e.last = t;
    var y = (100 - t * 100).toFixed(2);
    e.shape.setAttribute("d", "M0 " + y + " L1000 100 L0 100 Z");
    e.line.setAttribute("d", "M0 " + y + " L1000 100");
  }

  if (reduceMotion) {
    blocks.forEach(function (b) { b.classList.add("in-view"); });
    figureEls.forEach(function (f) { f.classList.add("is-on"); });
    figures.style.setProperty("--fill", "1");
    stackItems.forEach(function (s) { s.classList.add("is-active"); });
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

    // Services: cards beneath the newest one settle back as others stack on
    var arrivals = stackItems.map(function (item) {
      var stick = parseFloat(window.getComputedStyle(item).top) || 0;
      var r = item.getBoundingClientRect();
      var a = clamp(1 - (r.top - stick) / (vh * 0.65));
      if (a > 0.7) item.classList.add("is-active");
      return a;
    });
    stackItems.forEach(function (item, i) {
      var depth = 0;
      for (var j = i + 1; j < arrivals.length; j++) depth += arrivals[j];
      item.style.setProperty("--depth", Math.min(depth, 3).toFixed(3));
    });

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
      var r = e.el.parentElement.getBoundingClientRect();
      setEdge(e, clamp((r.top - vh * 0.12) / (vh * 0.78)));
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
