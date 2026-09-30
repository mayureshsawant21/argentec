/* Services orbit: a chrome Argentec "A" built in three.js from the logo's
   own vector path, surrounded by the six services. Hovering (or tapping)
   a service opens its description and turns the A toward it.
   three.js is bundled at assets/vendor/three.min.js and loaded only when
   the section comes near; without WebGL the flat SVG mark stays. */
(function () {
  "use strict";

  var section = document.getElementById("expertise");
  var core = section && section.querySelector(".orbit__core");
  if (!core) return;

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var fineHover = window.matchMedia("(hover: hover) and (pointer: fine)");
  var items = Array.prototype.slice.call(section.querySelectorAll("[data-orbit-item]"));

  /* ------------------------------------------------------------------
     Service items: hover / focus / tap to open
     ------------------------------------------------------------------ */
  var focus = { yaw: 0, pitch: 0, active: false };

  function open(item) {
    items.forEach(function (it) {
      var on = it === item;
      it.classList.toggle("is-open", on);
      it.querySelector(".orbit__btn").setAttribute("aria-expanded", on ? "true" : "false");
    });
    if (item) {
      var k = parseInt(item.style.getPropertyValue("--k"), 10) || 0;
      focus.yaw = item.getAttribute("data-side") === "left" ? -0.42 : 0.42;
      focus.pitch = (k - 1) * -0.16;
      focus.active = true;
    } else {
      focus.active = false;
    }
  }

  items.forEach(function (item) {
    var btn = item.querySelector(".orbit__btn");
    item.addEventListener("mouseenter", function () { if (fineHover.matches) open(item); });
    item.addEventListener("mouseleave", function () { if (fineHover.matches) open(null); });
    btn.addEventListener("focus", function () { if (fineHover.matches) open(item); });
    btn.addEventListener("click", function () {
      if (fineHover.matches) return;
      open(item.classList.contains("is-open") ? null : item);
    });
  });
  section.addEventListener("focusout", function (e) {
    if (fineHover.matches && !section.contains(e.relatedTarget)) open(null);
  });

  /* ------------------------------------------------------------------
     Lazy-load three.js when the section is near
     ------------------------------------------------------------------ */
  function loadThree(cb) {
    if (window.THREE) { cb(); return; }
    var s = document.createElement("script");
    s.src = "assets/vendor/three.min.js";
    s.async = true;
    s.onload = cb;
    document.head.appendChild(s);
  }

  var started = false;
  var nearObserver = new IntersectionObserver(function (entries) {
    if (started || !entries[0].isIntersecting) return;
    started = true;
    nearObserver.disconnect();
    loadThree(init);
  }, { rootMargin: "100% 0px" });
  nearObserver.observe(core);

  /* ------------------------------------------------------------------
     Build the A from the logo path
     ------------------------------------------------------------------ */
  function shapeFromPath(d, THREE) {
    var tokens = d.match(/[MLCZ]|-?\d*\.?\d+/g);
    var shape = new THREE.Shape();
    var i = 0;
    var cmd = null;
    function n() { return parseFloat(tokens[i++]); }
    while (i < tokens.length) {
      if (/[MLCZ]/.test(tokens[i])) cmd = tokens[i++];
      if (cmd === "M") { shape.moveTo(n(), -n()); cmd = "L"; }
      else if (cmd === "L") { shape.lineTo(n(), -n()); }
      else if (cmd === "C") { shape.bezierCurveTo(n(), -n(), n(), -n(), n(), -n()); }
      else if (cmd === "Z") { shape.closePath(); }
    }
    return shape;
  }

  function buildEnvironment(THREE, renderer) {
    // A small studio of coloured panels; the chrome reflects these.
    var env = new THREE.Scene();
    function panel(color, w, h, x, y, z, ry, rx) {
      var m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: color, side: THREE.DoubleSide }));
      m.position.set(x, y, z);
      m.rotation.y = ry || 0;
      m.rotation.x = rx || 0;
      env.add(m);
    }
    env.background = new THREE.Color(0x2a3a52);
    panel(0xffffff, 14, 4, 0, 6, 0, 0, Math.PI / 2);        // soft box overhead
    panel(0x194376, 12, 12, -7, 0, 0, Math.PI / 2);         // brand navy, left
    panel(0x0f2a4d, 12, 8, 0, 0, 7.5, Math.PI);             // deep navy behind the camera
    panel(0xc9d6e6, 6, 8, 7, 0, 0, -Math.PI / 2);           // cool silver, right
    panel(0xf1c9ae, 4, 3, 3, -1, 6, Math.PI);               // warm accent, front right
    panel(0x6fb3c9, 3, 3, -3, 1.5, 6, Math.PI);             // teal accent, front left
    panel(0x0e1b2e, 20, 20, 0, -5, 0, 0, -Math.PI / 2);     // dark floor
    panel(0xffffff, 3, 6, 0, 1, -7, 0);                     // back strip light
    var pmrem = new THREE.PMREMGenerator(renderer);
    var tex = pmrem.fromScene(env, 0.035).texture;
    pmrem.dispose();
    return tex;
  }

  function init() {
    var THREE = window.THREE;
    var canvas = document.getElementById("orbit-canvas");
    var renderer;
    try {
      renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
    } catch (e) {
      return; // no WebGL: keep the SVG mark
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;

    var scene = new THREE.Scene();
    scene.environment = buildEnvironment(THREE, renderer);

    var camera = new THREE.PerspectiveCamera(28, 1, 0.1, 100);
    camera.position.set(0, 0, 8.2);

    // Geometry: the A mark plus the thin rule beneath it
    var markPath = core.querySelector(".orbit__fallback path[fill]");
    var shapes = [shapeFromPath(markPath.getAttribute("d"), THREE)];
    var rule = new THREE.Shape();
    rule.moveTo(321.1, -502.9); rule.lineTo(387.45, -502.9); rule.lineTo(387.45, -504.7); rule.lineTo(321.1, -504.7); rule.closePath();
    shapes.push(rule);

    var geo = new THREE.ExtrudeGeometry(shapes, {
      depth: 9, bevelEnabled: true, bevelThickness: 1.6, bevelSize: 0.9, bevelSegments: 8, curveSegments: 28
    });
    geo.center();
    geo.computeVertexNormals();
    var size = new THREE.Vector3();
    geo.computeBoundingBox();
    geo.boundingBox.getSize(size);
    var scale = 2.6 / Math.max(size.x, size.y);

    var material = new THREE.MeshPhysicalMaterial({
      color: 0xffffff,
      metalness: 1,
      roughness: 0.1,
      clearcoat: 1,
      clearcoatRoughness: 0.06,
      envMapIntensity: 1.25
    });
    var mesh = new THREE.Mesh(geo, material);
    mesh.scale.setScalar(scale);

    var group = new THREE.Group();
    group.add(mesh);
    scene.add(group);

    function resize() {
      var w = canvas.clientWidth, h = canvas.clientHeight;
      if (!w || !h) return;
      renderer.setSize(w, h, false);
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
    }
    resize();
    window.addEventListener("resize", resize);

    core.classList.add("is-3d");

    // Pointer parallax
    var pointer = { x: 0, y: 0 };
    section.addEventListener("pointermove", function (e) {
      var r = core.getBoundingClientRect();
      pointer.x = clamp(((e.clientX - (r.left + r.width / 2)) / r.width), -1, 1);
      pointer.y = clamp(((e.clientY - (r.top + r.height / 2)) / r.height), -1, 1);
    });

    if (reduceMotion) {
      group.rotation.set(-0.12, -0.5, 0);
      renderer.render(scene, camera);
      return;
    }

    // Only render while visible
    var visible = false;
    new IntersectionObserver(function (entries) {
      visible = entries[0].isIntersecting;
      if (visible) { last = performance.now(); requestAnimationFrame(frame); }
    }).observe(core);

    var t = 0;
    var last = performance.now();
    var spin = -0.6;
    var enter = 0;       // 0 → 1 once the section is in view
    var tiltX = 0, yawOff = 0;

    function frame(now) {
      if (!visible) return;
      var dt = Math.min((now - last) / 1000, 0.05);
      last = now;
      t += dt;

      if (section.classList.contains("in-view") && enter < 1) enter = Math.min(1, enter + dt / 1.8);
      var e = easeOutBack(enter);

      var k = 1 - Math.exp(-dt * 4);
      if (focus.active) {
        // Turn to face the hovered service
        var turns = Math.round(spin / (Math.PI * 2)) * Math.PI * 2;
        spin += (turns + focus.yaw - spin) * k;
      } else {
        spin += dt * 0.55;
      }
      yawOff += ((focus.active ? 0 : pointer.x * 0.3) - yawOff) * k;
      var targetTilt = (focus.active ? focus.pitch : Math.sin(t * 0.7) * 0.2) + pointer.y * 0.25;
      tiltX += (targetTilt - tiltX) * k;

      group.rotation.y = spin + yawOff + (1 - enter) * -4;
      group.rotation.x = tiltX;
      group.rotation.z = Math.sin(t * 0.5) * 0.06;
      group.position.y = Math.sin(t * 0.9) * 0.08;
      group.scale.setScalar(0.001 + e * (1 + Math.sin(t * 1.3) * 0.015));

      renderer.render(scene, camera);
      requestAnimationFrame(frame);
    }
  }

  function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }
  function easeOutBack(x) {
    var c1 = 1.4, c3 = c1 + 1;
    return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2);
  }
})();
