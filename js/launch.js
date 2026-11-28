/* Launch gate for static hosting (GitHub Pages). Loaded first in every
   page's <head>. Before the launch time it sends visitors to
   coming-soon.html (the crane page with the countdown), which returns them
   here at zero. On Hostinger, launch.php does this on the server instead;
   this file then has nothing to do.
   The admin panel (Settings → Launch) rewrites the next line. */
var ARGENTEC_LAUNCH = { at: "2026-11-28T12:12:00+05:30", enabled: true };

(function () {
  "use strict";
  var cfg = window.ARGENTEC_LAUNCH;
  if (!cfg || !cfg.enabled || window.ARGENTEC_ADMIN_PREVIEW) return;
  var launch = Date.parse(cfg.at);
  if (!launch) return;
  var offset = 0, launched = "";
  try {
    offset = +(localStorage.getItem("argentec_clock_offset") || 0);
    launched = localStorage.getItem("argentec_launched") || "";
  } catch (e) {}
  // the countdown page already saw the launch happen, or the time has come
  if (launched === String(launch) || Date.now() + offset >= launch) return;
  if (/[?&]preview=site\b/.test(location.search)) return;

  // where is the site root, relative to this page? (this script is <root>/js/launch.js)
  var me = document.currentScript && document.currentScript.getAttribute("src") || "js/launch.js";
  var rootRel = me.replace(/js\/launch\.js.*$/, "");
  var here = location.pathname.split("/").pop() || "index.html";
  var depth = (rootRel.match(/\.\.\//g) || []).length;
  var from = location.pathname.split("/").slice(-1 - depth).join("/") || "index.html";
  if (here === "coming-soon.html") return;
  document.documentElement.style.visibility = "hidden";
  location.replace(rootRel + "coming-soon.html?from=" + encodeURIComponent(from));
})();
