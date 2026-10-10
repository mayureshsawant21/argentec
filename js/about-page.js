/* About page: lift doors on arrival, floor reveals, the headcount grid,
   and the lift panel that tracks (and travels to) each floor. */
(function () {
  "use strict";

  var body = document.body;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var floors = Array.prototype.slice.call(document.querySelectorAll(".floor[data-floor]"));

  function clamp(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }

  /* ------------------------------------------------------------------
     Lift doors
     ------------------------------------------------------------------ */
  var doors = document.getElementById("doors");
  var opened = false;

  function openDoors() {
    if (opened) return;
    opened = true;
    doors.classList.add("is-open");
    body.classList.remove("is-boarding");
    setTimeout(function () { body.classList.add("is-ready"); }, reduceMotion ? 0 : 450);
    setTimeout(function () { doors.classList.add("is-gone"); }, reduceMotion ? 0 : 1700);
  }

  if (reduceMotion) {
    openDoors();
  } else {
    var start = performance.now();
    window.addEventListener("load", function () {
      // hold the closed doors for a beat so the arrival reads
      setTimeout(openDoors, Math.max(0, 1100 - (performance.now() - start)));
    });
    setTimeout(openDoors, 3500);
  }

  /* ------------------------------------------------------------------
     Reveals
     ------------------------------------------------------------------ */
  var revealTargets = document.querySelectorAll(".floor:not(.lobby) [data-reveal], [data-borehole], [data-frame], [data-spec], [data-gantt]");
  if (reduceMotion || !("IntersectionObserver" in window)) {
    Array.prototype.forEach.call(revealTargets, function (el) { el.classList.add("is-in"); });
    floors.forEach(function (f) { f.classList.add("is-in"); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("is-in");
          io.unobserve(e.target);
        }
      });
    }, { rootMargin: "0px 0px -12% 0px" });
    Array.prototype.forEach.call(revealTargets, function (el) { io.observe(el); });

    var slabIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.parentElement.classList.add("is-in");
          slabIo.unobserve(e.target);
        }
      });
    }, { rootMargin: "0px 0px -8% 0px" });
    floors.forEach(function (f) {
      var slab = f.querySelector(".slab");
      if (slab) slabIo.observe(slab); else f.classList.add("is-in");
    });
  }

  /* ------------------------------------------------------------------
     Headcount grid: one dot per ten people
     ------------------------------------------------------------------ */
  var grid = document.querySelector("[data-headcount]");
  var counter = document.getElementById("headcount");
  var dots = [];
  var PEOPLE = 3950;
  if (grid) {
    var tech = parseInt(grid.getAttribute("data-tech"), 10);
    var labour = parseInt(grid.getAttribute("data-labour"), 10);
    var frag = document.createDocumentFragment();
    for (var i = 0; i < tech + labour; i++) {
      var dot = document.createElement("i");
      dot.className = i < tech ? "is-tech" : "is-labour";
      frag.appendChild(dot);
      dots.push(dot);
    }
    grid.appendChild(frag);
  }
  var litCount = -1;
  function renderHeadcount(vh) {
    if (!grid) return;
    var r = grid.getBoundingClientRect();
    var p = reduceMotion ? 1 : clamp((vh * 0.9 - r.top) / (r.height + vh * 0.25));
    var lit = Math.round(p * dots.length);
    if (lit === litCount) return;
    for (var j = 0; j < dots.length; j++) dots[j].classList.toggle("on", j < lit);
    counter.textContent = Math.round(PEOPLE * (lit / dots.length)).toLocaleString("en-US");
    litCount = lit;
  }

  /* ------------------------------------------------------------------
     Lift panel
     ------------------------------------------------------------------ */
  var lift = document.getElementById("lift");
  var list = document.getElementById("lift-buttons");
  var floorOut = document.getElementById("lift-floor");
  var nameOut = document.getElementById("lift-name");
  var dirOut = document.getElementById("lift-dir");
  var buttons = [];

  // Buttons run top (roof) to bottom (lobby), like a real panel
  floors.slice().reverse().forEach(function (floor) {
    var li = document.createElement("li");
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "lift__btn";
    btn.textContent = floor.getAttribute("data-floor");
    btn.setAttribute("aria-label", "Go to " + floor.getAttribute("data-name") + ", level " + floor.getAttribute("data-level") + " m");
    var label = document.createElement("span");
    label.className = "lift__btn-label";
    label.textContent = floor.getAttribute("data-name");
    btn.appendChild(label);
    btn.addEventListener("click", function () { travelTo(floor, btn); });
    li.appendChild(btn);
    list.appendChild(li);
    buttons.push({ floor: floor, btn: btn });
  });

  function travelTo(floor, btn) {
    buttons.forEach(function (b) { b.btn.classList.remove("is-called"); });
    btn.classList.add("is-called");
    var offset = floor.id === "lobby" ? 0 : floor.getBoundingClientRect().top + window.scrollY - 90;
    window.scrollTo({ top: Math.max(0, offset), behavior: reduceMotion ? "auto" : "smooth" });
  }

  var current = -1;
  var idleTimer = null;
  function setFloor(idx) {
    if (idx === current) return;
    var goingUp = idx > current;
    var floor = floors[idx];
    floorOut.textContent = floor.getAttribute("data-floor");
    nameOut.textContent = floor.getAttribute("data-name");
    if (current !== -1 && !reduceMotion) {
      floorOut.classList.remove("roll-up", "roll-down");
      void floorOut.offsetWidth;
      floorOut.classList.add(goingUp ? "roll-up" : "roll-down");
      dirOut.textContent = goingUp ? "▲" : "▼";
      lift.classList.add("is-moving");
      clearTimeout(idleTimer);
      idleTimer = setTimeout(function () { dirOut.textContent = ""; lift.classList.remove("is-moving"); }, 1100);
    }
    buttons.forEach(function (b) {
      var on = b.floor === floor;
      b.btn.classList.toggle("is-current", on);
      if (on) { b.btn.setAttribute("aria-current", "location"); b.btn.classList.remove("is-called"); }
      else b.btn.removeAttribute("aria-current");
    });
    current = idx;
  }

  /* ------------------------------------------------------------------
     Scroll loop
     ------------------------------------------------------------------ */
  var ticking = false;
  function update() {
    ticking = false;
    var vh = window.innerHeight;
    var idx = 0;
    for (var k = 0; k < floors.length; k++) {
      if (floors[k].getBoundingClientRect().top <= vh * 0.45) idx = k;
    }
    setFloor(idx);
    renderHeadcount(vh);
  }
  window.addEventListener("scroll", function () {
    if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
  }, { passive: true });
  window.addEventListener("resize", update);
  update();
})();
