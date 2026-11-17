/* Vendors and Careers pages: form validation and submission, the role
   filter and "Apply for this role" on Careers.

   FORM DELIVERY: set an endpoint per form below (for example a Formspree
   or Web3Forms URL). While a value is empty the form validates and shows
   its thank-you state, but nothing is sent anywhere. */
(function () {
  "use strict";

  var FORM_ENDPOINTS = { vendor: "", careers: "" };
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

      var d = new Date();
      var ref = form.getAttribute("data-ref") + "-" + String(d.getFullYear()).slice(2) +
        String(d.getMonth() + 1).padStart(2, "0") + String(d.getDate()).padStart(2, "0") + "-" +
        String(Math.floor(1000 + Math.random() * 9000));
      var kind = form.getAttribute("data-kind");
      var data = { reference: ref, form: kind };
      each(form.querySelectorAll("input, select, textarea"), function (el) {
        if (el.disabled || !el.name) return;
        if (el.type === "checkbox" && el.name !== "consent") {
          if (el.checked) (data[el.name] = data[el.name] || []).push(el.value);
        } else if (el.type === "radio") {
          if (el.checked) data[el.name] = el.value;
        } else if (el.type === "checkbox") {
          data[el.name] = el.checked;
        } else {
          data[el.name] = el.value.trim();
        }
      });

      submit.disabled = true;
      var endpoint = FORM_ENDPOINTS[kind];
      var send = endpoint
        ? fetch(endpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify(data)
          }).then(function (r) { if (!r.ok) throw new Error(r.status); })
        : new Promise(function (resolve) {
            if (window.console) console.warn("Argentec " + kind + " form: endpoint is not set, nothing was sent.", data);
            setTimeout(resolve, 500);
          });
      send.then(function () {
        form.querySelector("[data-ref-out]").textContent = ref;
        form.classList.add("is-done");
        done.hidden = false;
        done.focus();
      }).catch(function () {
        errorOut.textContent = "Something went wrong while sending. Please try again in a moment.";
        errorOut.hidden = false;
      }).then(function () { submit.disabled = false; });
    });

    form.querySelector("[data-again]").addEventListener("click", function () {
      form.reset();
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
  var filters = document.querySelectorAll(".co-filter");
  each(filters, function (btn) {
    btn.addEventListener("click", function () {
      var f = btn.getAttribute("data-filter");
      each(filters, function (b) {
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
      none.hidden = shown > 0;
    });
  });

  /* "Apply for this role" picks the role in the form */
  var select = document.querySelector('.co-form select[name="role"]');
  each(document.querySelectorAll("[data-apply]"), function (a) {
    a.addEventListener("click", function (e) {
      e.preventDefault();
      if (select) select.value = a.getAttribute("data-apply");
      var target = document.getElementById("apply");
      target.scrollIntoView({ behavior: reduce ? "auto" : "smooth" });
      setTimeout(function () {
        var name = document.querySelector('.co-form input[name="name"]');
        if (name) name.focus({ preventScroll: true });
      }, 700);
    });
  });

  // careers.html#site-engineer-civil opens that role
  var hash = window.location.hash.slice(1);
  if (hash) {
    var btn = document.querySelector('[data-apply="' + hash.replace(/[^a-z0-9-]/g, "") + '"]');
    if (btn) { var det = btn.closest("details"); det.open = true; det.scrollIntoView(); }
  }
})();
