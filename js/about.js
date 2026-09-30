/* Section 2 — Built for Progress
   Scroll progress drives the headline entrance, the word-by-word lead,
   and a tower elevation that rises one floor at a time. */
(function () {
  "use strict";

  var section = document.getElementById("about");
  if (!section) return;

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var pinQuery = window.matchMedia("(min-width: 1024px)");
  var SVG_NS = "http://www.w3.org/2000/svg";

  /* ------------------------------------------------------------------
     Split the lead paragraph into words
     ------------------------------------------------------------------ */
  var lead = section.querySelector("[data-illuminate]");
  var words = [];
  (function splitWords() {
    var text = lead.textContent.trim().split(/\s+/);
    lead.textContent = "";
    text.forEach(function (word, i) {
      var span = document.createElement("span");
      span.className = "w";
      span.textContent = word;
      lead.appendChild(span);
      if (i < text.length - 1) lead.appendChild(document.createTextNode(" "));
      words.push(span);
    });
  })();

  /* ------------------------------------------------------------------
     Build the tower elevation
     ------------------------------------------------------------------ */
  var svg = document.getElementById("tower-svg");
  var FLOORS = 16;
  var FLOOR_H = 28;          // drawing units per storey
  var FLOOR_M = 3.15;        // metres per storey
  var GROUND_Y = 510;
  var SETBACK_FROM = 12;     // floors 12+ step in
  var DIM_X = 72;
  var MULLIONS = 5;

  function el(name, attrs, parent) {
    var node = document.createElementNS(SVG_NS, name);
    for (var k in attrs) node.setAttribute(k, attrs[k]);
    (parent || svg).appendChild(node);
    return node;
  }
  function line(x1, y1, x2, y2, cls, parent) {
    return el("path", { d: "M" + x1 + " " + y1 + "L" + x2 + " " + y2, pathLength: 1, "class": "t-line " + cls }, parent);
  }

  // Ground line with earth hatching
  var ground = [line(30, GROUND_Y, 340, GROUND_Y, "t-ground")];
  for (var hx = 40; hx <= 330; hx += 14) {
    ground.push(line(hx, GROUND_Y + 2, hx - 10, GROUND_Y + 14, "t-hatch"));
  }

  // Floors
  var floors = [];
  for (var i = 0; i < FLOORS; i++) {
    var inset = i >= SETBACK_FROM ? 22 : 0;
    var x0 = 120 + inset;
    var x1 = 300 - inset;
    var yb = GROUND_Y - i * FLOOR_H;
    var yt = yb - FLOOR_H;
    var g = el("g", {});
    var glass = el("rect", { x: x0, y: yt, width: x1 - x0, height: FLOOR_H, "class": "t-glass" }, g);
    var cols = [line(x0, yb, x0, yt, "t-col", g), line(x1, yb, x1, yt, "t-col", g)];
    var mull = [];
    for (var m = 1; m <= MULLIONS; m++) {
      var mx = x0 + ((x1 - x0) / (MULLIONS + 1)) * m;
      mull.push(line(mx, yb - 3, mx, yt + 3, "t-mullion", g));
    }
    // Setback ledge is part of the first stepped floor
    var slabX0 = x0, slabX1 = x1;
    if (i === SETBACK_FROM - 1) { slabX0 = 120; slabX1 = 300; }
    var slab = line(slabX0 - 6, yt, slabX1 + 6, yt, "t-slab", g);
    floors.push({ glass: glass, cols: cols, mull: mull, slab: slab });
  }

  // Crown: parapet, plant room and mast
  var topY = GROUND_Y - FLOORS * FLOOR_H;
  var crown = [
    line(170, topY, 170, topY - 16, "t-col"),
    line(250, topY, 250, topY - 16, "t-col"),
    line(164, topY - 16, 256, topY - 16, "t-slab"),
    line(210, topY - 16, 210, topY - 58, "t-col")
  ];
  var beacon = el("circle", { cx: 210, cy: topY - 60, r: 2.6, "class": "t-beacon" });

  // Dimension line with level tags every 4 floors
  var dim = line(DIM_X, GROUND_Y, DIM_X, topY, "t-dim");
  var tags = [];
  el("text", { x: DIM_X - 10, y: GROUND_Y - 5, "text-anchor": "end", "class": "t-tag is-on" }).textContent = "±0.00";
  line(DIM_X - 5, GROUND_Y, DIM_X + 5, GROUND_Y, "t-dim").style.strokeDashoffset = 0;
  for (var t = 4; t <= FLOORS; t += 4) {
    var ty = GROUND_Y - t * FLOOR_H;
    var tick = line(DIM_X - 5, ty, DIM_X + 5, ty, "t-dim");
    var label = el("text", { x: DIM_X - 10, y: ty + 3, "text-anchor": "end", "class": "t-tag" });
    label.textContent = "+" + (t * FLOOR_M).toFixed(2);
    tags.push({ floor: t, tick: tick, label: label });
  }

  /* ------------------------------------------------------------------
     Render for a given progress value
     ------------------------------------------------------------------ */
  var levelOut = document.getElementById("tower-level");
  var floorOut = document.getElementById("tower-floor");

  function clamp(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }
  function draw(node, amount) { node.style.strokeDashoffset = String(1 - clamp(amount)); }

  var lastLevelText = "";
  function renderTower(p) {
    // 0.00–0.06 ground · 0.06–0.84 floors · 0.84–0.92 crown · 0.92+ beacon
    var gp = clamp(p / 0.06);
    ground.forEach(function (n, idx) { draw(n, idx === 0 ? gp : gp * 1.4 - 0.4); });

    var built = clamp((p - 0.06) / 0.78) * FLOORS;
    floors.forEach(function (f, idx) {
      var local = clamp(built - idx);
      draw(f.cols[0], local * 2);
      draw(f.cols[1], local * 2);
      f.mull.forEach(function (n) { draw(n, local * 2 - 0.6); });
      draw(f.slab, local * 2 - 1);
      f.glass.classList.toggle("is-on", local >= 1);
    });

    draw(dim, built / FLOORS);
    tags.forEach(function (tg) {
      var on = built >= tg.floor;
      draw(tg.tick, on ? 1 : 0);
      tg.label.classList.toggle("is-on", on);
    });

    var cp = clamp((p - 0.84) / 0.08);
    crown.forEach(function (n, idx) { draw(n, cp * crown.length - idx); });
    beacon.classList.toggle("is-on", p >= 0.92);

    var metres = built * FLOOR_M;
    var levelText = metres < 0.005 ? "±0.00" : "+" + metres.toFixed(2);
    if (levelText !== lastLevelText) {
      levelOut.textContent = levelText;
      var n = Math.floor(built);
      floorOut.textContent = n < 1 ? "G" : String(n);
      lastLevelText = levelText;
    }
  }

  function renderWords(amount) {
    var lit = Math.round(clamp(amount) * words.length);
    for (var w = 0; w < words.length; w++) words[w].classList.toggle("is-lit", w < lit);
  }

  /* ------------------------------------------------------------------
     Scroll handling
     ------------------------------------------------------------------ */
  var visual = section.querySelector(".about__visual");
  var edge = section.querySelector(".edge");
  var detail = section.querySelector(".about__detail");
  var ticking = false;

  // Redraw the edge geometry rather than stretching it, so the hairline
  // renders as one clean line at every angle.
  var edgeShape = edge && edge.querySelector(".edge__shape path");
  var edgeLine = edge && edge.querySelector(".edge__line path");
  var lastTilt = -1;
  function setEdge(el, tilt) {
    var t = Math.round(tilt * 1000) / 1000;
    if (t === lastTilt) return;
    lastTilt = t;
    var y = (100 - t * 100).toFixed(2);
    edgeShape.setAttribute("d", "M0 100 L1000 " + y + " L1000 100 Z");
    edgeLine.setAttribute("d", "M0 100 L1000 " + y);
  }
  // Once the draw-in has finished, drop the clip so nothing re-rasterises
  if (edge) {
    var edgeLineSvg = edge.querySelector(".edge__line");
    edgeLineSvg.addEventListener("transitionend", function () { edgeLineSvg.style.clipPath = "none"; });
  }

  function update() {
    ticking = false;
    var vh = window.innerHeight;
    var rect = section.getBoundingClientRect();

    if (rect.top < vh * 0.65) section.classList.add("in-view");

    // Diagonal edge levels out as the section rises to meet the header
    if (edge) setEdge(edge, clamp((rect.top - vh * 0.12) / (vh * 0.78)));

    if (pinQuery.matches) {
      // Pinned: progress across the section's scroll distance
      var p = clamp(-rect.top / (rect.height - vh));
      renderWords((p - 0.02) / 0.4);
      renderTower(clamp((p - 0.08) / 0.84));
      section.classList.toggle("is-late", p > 0.38);
      section.style.setProperty("--p", p.toFixed(4));
    } else {
      // Stacked: each part plays as it crosses the viewport
      var lr = lead.getBoundingClientRect();
      renderWords((vh * 0.85 - lr.top) / (lr.height + vh * 0.3));
      var vr = visual.getBoundingClientRect();
      renderTower((vh * 0.92 - vr.top) / (vr.height * 0.95));
      if (detail.getBoundingClientRect().top < vh * 0.88) section.classList.add("is-late");
      section.style.setProperty("--p", clamp((vh - rect.top) / (rect.height + vh)).toFixed(4));
    }
  }

  function requestUpdate() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(update);
    }
  }

  if (reduceMotion) {
    section.classList.add("in-view", "is-late");
    renderWords(1);
    renderTower(1);
    return;
  }

  window.addEventListener("scroll", requestUpdate, { passive: true });
  window.addEventListener("resize", requestUpdate);
  update();
})();
