/* Expertise pages: page entrance, scroll reveals and the methodology
   timeline that fills as it scrolls past. */
(function () {
  "use strict";

  var body = document.body;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  requestAnimationFrame(function () { body.classList.add("is-ready"); });

  var year = document.querySelector("[data-year]");
  if (year) year.textContent = String(new Date().getFullYear());

  /* Reveals */
  var targets = document.querySelectorAll("[data-reveal]");
  if (reduceMotion || !("IntersectionObserver" in window)) {
    Array.prototype.forEach.call(targets, function (el) { el.classList.add("is-in"); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("is-in"); io.unobserve(e.target); }
      });
    }, { rootMargin: "0px 0px -10% 0px" });
    Array.prototype.forEach.call(targets, function (el) { io.observe(el); });
  }

  /* Methodology timeline */
  var steps = document.querySelector("[data-steps]");
  if (!steps) return;
  var items = Array.prototype.slice.call(steps.querySelectorAll(".xp-step"));
  if (reduceMotion) { items.forEach(function (s) { s.classList.add("is-on"); }); return; }

  var ticking = false;
  function update() {
    ticking = false;
    var vh = window.innerHeight;
    var r = steps.getBoundingClientRect();
    var p = Math.max(0, Math.min(1, (vh * 0.75 - r.top) / (r.height + vh * 0.25)));
    steps.style.setProperty("--done", p.toFixed(3));
    var lit = Math.ceil(p * items.length);
    items.forEach(function (s, i) { s.classList.toggle("is-on", i < lit); });
  }
  window.addEventListener("scroll", function () {
    if (!ticking) { ticking = true; requestAnimationFrame(update); }
  }, { passive: true });
  window.addEventListener("resize", update);
  update();
})();
