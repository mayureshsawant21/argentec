/* Vendors and Careers pages: form validation and submission (to the site
   backend via window.ArgentecForms), the role filter and "Apply for this
   role" on Careers. */
(function () {
  "use strict";

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  /* ---------------------------------------------------------------- forms */
  each(document.querySelectorAll(".co-form"), function (form) {
    var errorOut = form.querySelector(".co-form__error");
    var done = form.querySelector(".co-done");
    var submit = form.querySelector(".co-form__submit");
    var groups = form.querySelectorAll(".co-chips[data-min]");

    function wrap(el) { return el.closest(".co-field") || el.closest(".co-consent"); }
    function clear() {
      each(form.querySelectorAll(".is-invalid"), function (w) { w.classList.remove("is-invalid"); });
      each(form.querySelectorAll("[aria-invalid]"), function (el) { el.removeAttribute("aria-invalid"); });
      errorOut.hidden = true;
    }
    function message(el) {
      if (el.type === "checkbox") return "Please tick the box to confirm.";
      if (el.validity && el.validity.valueMissing) return "Please fill in the highlighted fields.";
      if (el.getAttribute("data-msg")) return el.getAttribute("data-msg");
      if (el.type === "email") return "Please check the email address.";
      if (el.type === "tel") return "Please enter a valid mobile number.";
      return "Please check the highlighted fields.";
    }

    form.addEventListener("input", function (e) {
      var w = wrap(e.target);
      if (w && e.target.checkValidity()) { w.classList.remove("is-invalid"); e.target.removeAttribute("aria-invalid"); }
      var g = e.target.closest(".co-chips");
      if (g) g.classList.remove("is-invalid");
    });
    // Resume upload: show the chosen file, check type and size
    var ALLOWED = /\.(pdf|docx?)$/i;
    function fileProblem(el) {
      var f = el.files && el.files[0];
      if (!f) return "";
      var max = (+el.getAttribute("data-max-mb") || 5) * 1024 * 1024;
      if (!ALLOWED.test(f.name) || f.size > max) return el.getAttribute("data-msg") || "Please check the file.";
      return "";
    }
    each(form.querySelectorAll('input[type="file"]'), function (el) {
      var out = el.closest(".co-upload").querySelector("[data-file-name]");
      var empty = out.textContent;
      var drop = el.closest(".co-upload__drop");
      el.addEventListener("change", function () {
        var f = el.files && el.files[0];
        out.textContent = f ? f.name + " (" + Math.max(1, Math.round(f.size / 1024)) + " KB)" : empty;
        drop.classList.toggle("has-file", !!f);
        var bad = fileProblem(el);
        el.setCustomValidity(bad);
        var w = wrap(el);
        if (w) w.classList.toggle("is-invalid", !!bad);
        if (bad) { errorOut.textContent = bad; errorOut.hidden = false; } else { errorOut.hidden = true; }
      });
      ["dragenter", "dragover"].forEach(function (t) { el.addEventListener(t, function () { drop.classList.add("is-over"); }); });
      ["dragleave", "drop"].forEach(function (t) { el.addEventListener(t, function () { drop.classList.remove("is-over"); }); });
    });

    // GSTIN is always upper case
    each(form.querySelectorAll('input[name="gst"]'), function (el) {
      el.addEventListener("input", function () { var pos = el.selectionStart; el.value = el.value.toUpperCase(); el.setSelectionRange(pos, pos); });
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      clear();
      var firstBad = null, firstMsg = "";
      each(form.querySelectorAll("input, select, textarea"), function (el) {
        if (el.disabled || el.type === "radio" || (el.type === "checkbox" && el.name !== "consent")) return;
        if (!el.checkValidity()) {
          var w = wrap(el); if (w) w.classList.add("is-invalid");
          el.setAttribute("aria-invalid", "true");
          if (!firstBad) { firstBad = el; firstMsg = message(el); }
        }
      });
      each(groups, function (g) {
        var min = +g.getAttribute("data-min") || 1;
        if (g.querySelectorAll("input:checked").length < min) {
          g.classList.add("is-invalid");
          if (!firstBad) { firstBad = g.querySelector("input"); firstMsg = g.getAttribute("data-msg") || "Please choose an option."; }
        }
      });
      if (firstBad) {
        errorOut.textContent = firstMsg;
        errorOut.hidden = false;
        firstBad.focus();
        return;
      }

      var kind = form.getAttribute("data-kind");
      var data = {}, files = {};
      each(form.querySelectorAll("input, select, textarea"), function (el) {
        if (el.disabled || !el.name) return;
        if (el.type === "file") {
          if (el.files && el.files[0]) files[el.name] = el.files[0];
        } else if (el.type === "checkbox" && el.name !== "consent") {
          if (el.checked) (data[el.name] = data[el.name] || []).push(el.value);
        } else if (el.type === "radio") {
          if (el.checked) data[el.name] = el.value;
        } else if (el.type === "checkbox") {
          data[el.name] = el.checked ? "yes" : "";
        } else {
          data[el.name] = el.value.trim();
        }
      });
      // the role select sends its visible title too, for the admin's designation filter
      var roleSel = form.querySelector('select[name="role"]');
      if (roleSel) data.role_title = roleSel.options[roleSel.selectedIndex].text;

      submit.disabled = true;
      var send = window.ArgentecForms.send(kind, data, files);
      send.then(function (ref) {
        form.querySelector("[data-ref-out]").textContent = ref;
        form.classList.add("is-done");
        done.hidden = false;
        done.focus();
      }).catch(function (err) {
        if (window.console) console.error("Argentec " + kind + " form:", err);
        errorOut.textContent = (err && err.userMessage) || "Something went wrong while sending. Please try again in a moment.";
        errorOut.hidden = false;
      }).then(function () { submit.disabled = false; });
    });

    form.querySelector("[data-again]").addEventListener("click", function () {
      form.reset();
      each(form.querySelectorAll('input[type="file"]'), function (el) { el.setCustomValidity(""); el.dispatchEvent(new Event("change")); });
      clear();
      form.classList.remove("is-done");
      done.hidden = true;
      var first = form.querySelector("input:not([type=radio]):not([type=checkbox]), select");
      if (first) first.focus();
    });
  });

  /* --------------------------------------------------- careers: filters */
  var list = document.querySelector("[data-roles]");
  if (!list) return;
  var none = document.querySelector(".co-roles__none");
  var filterBar = document.querySelector(".co-filters");
  filterBar.addEventListener("click", function (e) {
    var btn = e.target.closest(".co-filter");
    if (!btn) return;
    var f = btn.getAttribute("data-filter");
    each(filterBar.querySelectorAll(".co-filter"), function (b) {
      var on = b === btn;
      b.classList.toggle("is-active", on);
      b.setAttribute("aria-pressed", on ? "true" : "false");
    });
    var shown = 0;
    each(list.querySelectorAll(".co-role"), function (li) {
      var on = f === "all" || li.getAttribute("data-dept") === f;
      li.hidden = !on;
      if (on) shown++;
    });
    none.hidden = shown > 0 || !list.querySelector(".co-role");
  });

  /* "Apply for this role" picks the role in the form */
  list.addEventListener("click", function (e) {
    var a = e.target.closest("[data-apply]");
    if (!a) return;
    e.preventDefault();
    var select = document.querySelector('.co-form select[name="role"]');
    if (select) select.value = a.getAttribute("data-apply");
    document.getElementById("apply").scrollIntoView({ behavior: reduce ? "auto" : "smooth" });
    setTimeout(function () {
      var name = document.querySelector('.co-form input[name="name"]');
      if (name) name.focus({ preventScroll: true });
    }, 700);
  });

  // careers.html#site-engineer-civil opens that role
  function openFromHash() {
    var hash = window.location.hash.slice(1).replace(/[^A-Za-z0-9-]/g, "");
    if (!hash || hash === "apply" || hash === "openings") return;
    var btn = list.querySelector('[data-apply="' + hash + '"]');
    if (btn) { var det = btn.closest("details"); det.open = true; det.scrollIntoView(); }
  }
  openFromHash();
})();
