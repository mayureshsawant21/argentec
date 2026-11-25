/* Argentec admin panel: small progressive enhancements. Every page works
   without this file; it adds the media picker, the blog editor toolbar,
   bulk selection, confirmations and other conveniences. */
(function () {
  "use strict";
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf"]') || {}).content || "";

  function toast(msg) {
    var t = $(".toast") || document.body.appendChild(Object.assign(document.createElement("div"), { className: "toast", role: "status" }));
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { t.hidden = true; }, 2400);
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
  }

  /* Password show/hide */
  $$("[data-toggle-pass]").forEach(function (b) {
    b.addEventListener("click", function () {
      var i = b.parentNode.querySelector("input");
      var show = i.type === "password";
      i.type = show ? "text" : "password";
      b.textContent = show ? "Hide" : "Show";
    });
  });

  /* Mobile sidebar */
  var menuBtn = $(".tb__menu"), sb = $("#sidebar");
  if (menuBtn && sb) {
    menuBtn.addEventListener("click", function () {
      var open = sb.classList.toggle("is-open");
      menuBtn.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.addEventListener("click", function (e) {
      if (sb.classList.contains("is-open") && !sb.contains(e.target) && !menuBtn.contains(e.target)) sb.classList.remove("is-open");
    });
  }

  /* Clickable table rows */
  document.addEventListener("click", function (e) {
    var tr = e.target.closest("tr[data-href]");
    if (!tr || e.target.closest("a, button, input, label, select")) return;
    if (e.metaKey || e.ctrlKey) window.open(tr.getAttribute("data-href"), "_blank");
    else window.location = tr.getAttribute("data-href");
  });

  /* Confirmations: forms and individual buttons */
  document.addEventListener("submit", function (e) {
    var f = e.target;
    var btn = e.submitter;
    var msg = (btn && btn.getAttribute("data-confirm")) || f.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  /* Bulk selection */
  $$("[data-bulk]").forEach(function (form) {
    var bar = $(".bulk", form), count = $("[data-bulk-count]", form), all = $("[data-check-all]", form);
    function update() {
      var n = $$('input[name="ids[]"]:checked', form).length;
      bar.hidden = n === 0;
      count.textContent = n + " selected";
      if (all) all.checked = n > 0 && n === $$('input[name="ids[]"]', form).length;
    }
    form.addEventListener("change", function (e) {
      if (e.target === all) $$('input[name="ids[]"]', form).forEach(function (c) { c.checked = all.checked; });
      update();
    });
    var go = $("[data-confirm-bulk]", form);
    if (go) go.addEventListener("click", function (e) {
      var sel = $('select[name="bulk"]', form);
      if (!sel.value) { e.preventDefault(); sel.focus(); return; }
      if (sel.value === "delete" && !window.confirm("Delete the selected submissions? Their resumes are deleted too. This cannot be undone.")) e.preventDefault();
    });
  });

  /* Auto-submitting filters */
  $$("[data-autosubmit]").forEach(function (s) { s.addEventListener("change", function () { s.form.submit(); }); });

  /* Character counters (search titles and descriptions) */
  $$("[data-count-max]").forEach(function (el) {
    var max = +el.getAttribute("data-count-max");
    var note = document.createElement("span");
    note.className = "count-note";
    el.insertAdjacentElement("afterend", note);
    function upd() {
      var n = el.value.length;
      note.textContent = n + " / " + max + (n > max ? " — may be cut off in Google" : "");
      note.classList.toggle("is-over", n > max);
    }
    el.addEventListener("input", upd);
    upd();
  });

  /* Filter a list or grid by name */
  $$("[data-filter-grid]").forEach(function (input) {
    var targets = $$(input.getAttribute("data-filter-grid"));
    input.addEventListener("input", function () {
      var q = input.value.trim().toLowerCase();
      targets.forEach(function (t) {
        $$("[data-name]", t).forEach(function (li) { li.hidden = q && li.getAttribute("data-name").indexOf(q) === -1; });
      });
    });
  });

  /* Copy to clipboard */
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-copy]");
    if (!b) return;
    var text = b.getAttribute("data-copy");
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { toast("Link copied"); }, function () { window.prompt("Copy this link", text); });
  });

  /* Media library drop zone */
  $$("[data-dropzone]").forEach(function (zone) {
    var input = $('input[type="file"]', zone);
    input.addEventListener("change", function () { if (input.files.length) zone.submit(); });
    ["dragenter", "dragover"].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.add("is-over"); }); });
    ["dragleave", "drop"].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.remove("is-over"); }); });
    zone.addEventListener("drop", function (e) {
      if (!e.dataTransfer.files.length) return;
      input.files = e.dataTransfer.files;
      zone.submit();
    });
  });

  /* ------------------------------------------------------------ media picker */
  var dlg = $("#media-picker");
  var pickTarget = null, pickType = "image", items = [];
  function relFor(target, path) {
    // pages in sub-folders need ../ in front of site paths
    var from = target.getAttribute("data-relative-from");
    if (from === null || from === "*") return path;
    var depth = (from.match(/\//g) || []).length;
    return new Array(depth + 1).join("../") + path;
  }
  function renderGrid() {
    var q = ($("[data-mp-search]", dlg).value || "").toLowerCase();
    var grid = $("[data-mp-grid]", dlg);
    var list = items.filter(function (m) { return !q || m.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 300);
    grid.innerHTML = list.length ? list.map(function (m) {
      var thumb = m.kind === "video" ? '<video src="../' + esc(m.path) + '" muted preload="metadata"></video>' : '<img src="../' + esc(m.path) + '" alt="" loading="lazy">';
      return '<button type="button" class="mp-item" data-path="' + esc(m.path) + '">' + thumb + "<span>" + esc(m.name) + "</span></button>";
    }).join("") : '<p class="muted">No files yet. Upload one above.</p>';
  }
  function loadMedia() {
    $("[data-mp-status]", dlg).textContent = "Loading…";
    return fetch("media.php?json=1&type=" + encodeURIComponent(pickType), { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (j) { items = j.items || []; $("[data-mp-status]", dlg).textContent = items.length + " files"; renderGrid(); })
      .catch(function () { $("[data-mp-status]", dlg).textContent = "Could not load the media library."; });
  }
  function choose(path) {
    if (pickTarget === "rte") { insertImage(path); dlg.close(); return; }
    var box = pickTarget, input = $("[data-picker-value]", box), prev = $(".picker__preview", box);
    input.value = input.hasAttribute("data-absolute") ? path : relFor(input, path);
    if (prev) { prev.src = "../" + path; prev.hidden = false; }
    var label = $(".mono", box);
    if (label) label.textContent = input.value;
    input.dispatchEvent(new Event("change", { bubbles: true }));
    dlg.close();
  }
  if (dlg) {
    document.addEventListener("click", function (e) {
      var b = e.target.closest("[data-pick]");
      if (b) {
        pickTarget = b.closest("[data-picker]");
        pickType = b.getAttribute("data-pick") || "image";
        dlg.showModal();
        loadMedia();
        return;
      }
      var clear = e.target.closest("[data-pick-clear]");
      if (clear) {
        var box = clear.closest("[data-picker]");
        $("[data-picker-value]", box).value = "";
        var p = $(".picker__preview", box);
        if (p) p.hidden = true;
      }
    });
    $("[data-mp-grid]", dlg).addEventListener("click", function (e) {
      var it = e.target.closest(".mp-item");
      if (it) choose(it.getAttribute("data-path"));
    });
    $("[data-mp-search]", dlg).addEventListener("input", renderGrid);
    $("[data-mp-upload]", dlg).addEventListener("change", function (e) {
      var f = e.target.files[0];
      if (!f) return;
      var fd = new FormData();
      fd.append("file", f);
      fd.append("_csrf", csrf);
      $("[data-mp-status]", dlg).textContent = "Uploading " + f.name + "…";
      fetch("media.php?ajax=1", { method: "POST", body: fd, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          e.target.value = "";
          if (!j.ok) { $("[data-mp-status]", dlg).textContent = j.error || "Upload failed."; return; }
          choose(j.path);
        })
        .catch(function () { $("[data-mp-status]", dlg).textContent = "Upload failed."; });
    });
  }

  /* ------------------------------------------------------------- page editor */
  var pageForm = $("[data-page-form]");
  if (pageForm) {
    var dirty = false, note = $("[data-dirty-note]");
    pageForm.addEventListener("input", function (e) {
      dirty = true;
      if (note) note.hidden = false;
      var f = e.target.closest(".cms-field");
      if (f) f.classList.add("is-changed");
    });
    pageForm.addEventListener("change", function (e) {
      dirty = true;
      if (note) note.hidden = false;
      var f = e.target.closest(".cms-field");
      if (f) f.classList.add("is-changed");
    });
    pageForm.addEventListener("submit", function () { dirty = false; });
    window.addEventListener("beforeunload", function (e) { if (dirty) { e.preventDefault(); e.returnValue = ""; } });
    var search = $("[data-field-search]");
    if (search) search.addEventListener("input", function () {
      var q = search.value.trim().toLowerCase();
      $$(".cms-field", pageForm).forEach(function (f) {
        var val = (f.getAttribute("data-text") || "") + " " + $$("input, textarea", f).map(function (i) { return i.value.toLowerCase(); }).join(" ");
        f.hidden = q && val.indexOf(q) === -1;
      });
      $$(".fields-group", pageForm).forEach(function (g) {
        var any = $$(".cms-field:not([hidden])", g).length;
        g.hidden = q && !any;
        if (q && any) g.open = true;
      });
    });
    // Google result preview
    var t = $('[name="meta[title]"]'), d = $('[name="meta[description]"]');
    if (t) t.addEventListener("input", function () { $("[data-serp-title]").textContent = t.value; });
    if (d) d.addEventListener("input", function () { $("[data-serp-desc]").textContent = d.value; });
  }
  $$("textarea[data-autosize]").forEach(function (ta) {
    function fit() { ta.style.height = "auto"; ta.style.height = Math.min(ta.scrollHeight + 2, 400) + "px"; }
    ta.addEventListener("input", fit);
    fit();
  });

  /* ------------------------------------------------------------- blog editor */
  var rte = $("[data-rte]");
  var area, src, hidden, info;
  function insertImage(path) {
    area.focus();
    var alt = window.prompt("Describe the image (for screen readers and Google):", "") || "";
    document.execCommand("insertHTML", false, '<figure><img src="../' + esc(path) + '" alt="' + esc(alt) + '" loading="lazy"></figure><p><br></p>');
    sync();
  }
  function sync() {
    if (!area) return;
    hidden.value = src.hidden ? area.innerHTML : src.value;
    var words = (src.hidden ? area.innerText : src.value.replace(/<[^>]+>/g, " ")).trim().split(/\s+/).filter(Boolean).length;
    info.textContent = words + " words · about " + Math.max(1, Math.ceil(words / 200)) + " min read";
  }
  if (rte) {
    area = $(".rte__area", rte);
    src = $(".rte__source", rte);
    hidden = $('textarea[name="body"]', rte);
    info = $("[data-rte-info]", rte);
    document.execCommand("defaultParagraphSeparator", false, "p");
    $(".rte__bar", rte).addEventListener("mousedown", function (e) { if (e.target.closest("button")) e.preventDefault(); });
    $(".rte__bar", rte).addEventListener("click", function (e) {
      var b = e.target.closest("button[data-cmd]");
      if (!b) return;
      var cmd = b.getAttribute("data-cmd"), v = b.getAttribute("data-val");
      if (cmd === "source") {
        if (src.hidden) { src.value = area.innerHTML; area.hidden = true; src.hidden = false; b.classList.add("is-on"); }
        else { area.innerHTML = src.value; src.hidden = true; area.hidden = false; b.classList.remove("is-on"); }
        sync();
        return;
      }
      if (!src.hidden) return;
      area.focus();
      if (cmd === "createLink") {
        var url = window.prompt("Link address (https://… or a page such as ../contact.html):", "https://");
        if (url && !/^\s*javascript:/i.test(url)) document.execCommand("createLink", false, url);
      } else if (cmd === "image") {
        if (dlg) { pickTarget = "rte"; pickType = "image"; dlg.showModal(); loadMedia(); }
      } else if (cmd === "formatBlock") {
        document.execCommand("formatBlock", false, "<" + v + ">");
      } else {
        document.execCommand(cmd, false, v);
      }
      sync();
    });
    area.addEventListener("input", sync);
    src.addEventListener("input", sync);
    // paste as clean text with paragraphs, not Word/Docs styling
    area.addEventListener("paste", function (e) {
      var html = (e.clipboardData || window.clipboardData).getData("text/html");
      var text = (e.clipboardData || window.clipboardData).getData("text/plain");
      if (!html && !text) return;
      e.preventDefault();
      if (html) {
        var tmp = document.createElement("div");
        tmp.innerHTML = html;
        $$("style, script, meta, link", tmp).forEach(function (n) { n.remove(); });
        $$("*", tmp).forEach(function (n) {
          Array.prototype.slice.call(n.attributes).forEach(function (a) { if (a.name !== "href" && a.name !== "src" && a.name !== "alt") n.removeAttribute(a.name); });
        });
        document.execCommand("insertHTML", false, tmp.innerHTML);
      } else {
        document.execCommand("insertHTML", false, text.split(/\n{2,}/).map(function (p) { return "<p>" + esc(p).replace(/\n/g, "<br>") + "</p>"; }).join(""));
      }
      sync();
    });
    var form = rte.closest("form"), changed = false;
    form.addEventListener("input", function () { changed = true; });
    form.addEventListener("submit", function () { sync(); changed = false; });
    window.addEventListener("beforeunload", function (e) { if (changed) { e.preventDefault(); e.returnValue = ""; } });
    sync();
  }
})();
