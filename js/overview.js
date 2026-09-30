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
     Services: surveyor's dial
     ------------------------------------------------------------------ */
  var SVG_NS = "http://www.w3.org/2000/svg";
  var dial = services.querySelector(".dial");
  var rotor = document.getElementById("dial-rotor");
  var dialItems = Array.prototype.slice.call(services.querySelectorAll("[data-dial-item]"));
  var itemsBox = services.querySelector(".dial__items");
  var arm = services.querySelector(".dial__arm");
  var bearingOut = document.getElementById("dial-bearing");
  var indexOut = document.getElementById("dial-index");
  var STEP = 36;                 // degrees between services
  var labels = [];
  var dots = [];

  function svgEl(name, attrs, parent) {
    var n = document.createElementNS(SVG_NS, name);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    parent.appendChild(n);
    return n;
  }
  function polar(r, deg) {
    var a = deg * Math.PI / 180;
    return [(r * Math.cos(a)).toFixed(2), (r * Math.sin(a)).toFixed(2)];
  }

  (function buildDial() {
    svgEl("circle", { r: 480, "class": "ring ring--outer" }, rotor);
    svgEl("circle", { r: 400, "class": "ring" }, rotor);
    for (var d = 0; d < 360; d += 3) {
      var major = d % 15 === 0;
      var p1 = polar(480, d), p2 = polar(major ? 448 : 464, d);
      svgEl("line", { x1: p1[0], y1: p1[1], x2: p2[0], y2: p2[1], "class": major ? "tick tick--major" : "tick" }, rotor);
      if (d % 30 === 0) {
        var t = polar(424, d);
        var txt = svgEl("text", { x: t[0], y: t[1], "text-anchor": "middle", "dominant-baseline": "middle", "class": "deg",
          transform: "rotate(" + (d + 90) + " " + t[0] + " " + t[1] + ")" }, rotor);
        txt.textContent = String(d).padStart(3, "0");
      }
    }
    // Service names sit inside the inner ring, one every STEP degrees
    dialItems.forEach(function (item, i) {
      var deg = i * STEP;
      var dp = polar(400, deg);
      dots.push(svgEl("circle", { cx: dp[0], cy: dp[1], r: 4, "class": "svc-dot" }, rotor));
      var lp = polar(380, deg);
      var label = svgEl("text", { x: lp[0], y: lp[1], dy: -16, "text-anchor": "end", "dominant-baseline": "middle", "class": "svc-label",
        transform: "rotate(" + deg + " " + lp[0] + " " + lp[1] + ")" }, rotor);
      label.textContent = item.getAttribute("data-label");
      labels.push(label);
    });
  })();

  var dialBase = 0;              // angle of the arm: 0 = 3 o'clock, -90 = 12 o'clock
  var activeIndex = -1;
  var lastAngle = null;

  function setDialActive(idx) {
    if (idx === activeIndex) return;
    activeIndex = idx;
    dialItems.forEach(function (item, i) {
      item.classList.toggle("is-active", i === idx);
      item.classList.toggle("is-past", i < idx);
    });
    labels.forEach(function (l, i) { l.classList.toggle("is-active", i === idx); });
    dots.forEach(function (d, i) { d.classList.toggle("is-active", i === idx); });
    indexOut.textContent = String(idx + 1).padStart(2, "0");
  }

  function smooth(t) { return t * t * (3 - 2 * t); }

  function renderDial(vh) {
    dialBase = wide.matches ? 0 : -90;
    alignItems();
    var r = dial.getBoundingClientRect();
    var p = clamp(-r.top / (r.height - vh));
    var raw = clamp((p - 0.04) / 0.9) * (dialItems.length - 1);
    var base = Math.floor(raw);
    var frac = raw - base;
    // dwell on each service: the dial only turns through the middle half of each step
    var eased = Math.min(base + smooth(clamp((frac - 0.25) / 0.5)), dialItems.length - 1);
    var angle = eased * STEP;
    if (angle !== lastAngle) {
      rotor.setAttribute("transform", "rotate(" + (dialBase - angle).toFixed(3) + ")");
      bearingOut.textContent = String(Math.round(angle)).padStart(3, "0") + "\u00B0";
      lastAngle = angle;
    }
    setDialActive(Math.round(eased));
  }

  // Keep the service name level with the arm
  function alignItems() {
    if (!wide.matches) { itemsBox.style.removeProperty("--pivot-offset"); return; }
    var armY = arm.getBoundingClientRect().top;
    var boxY = itemsBox.getBoundingClientRect().top;
    itemsBox.style.setProperty("--pivot-offset", (armY - boxY) + "px");
  }

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
    dialItems.forEach(function (d) { d.classList.add("is-active"); });
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

    // Services dial
    var dr = dial.getBoundingClientRect();
    if (dr.bottom > 0 && dr.top < vh) renderDial(vh);

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
  window.addEventListener("resize", function () { alignItems(); lastAngle = null; requestUpdate(); });
  alignItems();
  renderDial(window.innerHeight);
  update();
})();
