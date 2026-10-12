/* Contact page: enquiry type switches the docket's fields, validation,
   careers redirect, and submission.

   FORM DELIVERY: set FORM_ENDPOINT to a form service URL (for example a
   Formspree or Web3Forms endpoint) to receive enquiries by email. While it
   is empty the form validates and shows the "Received" state, but nothing
   is sent anywhere. */
(function () {
  "use strict";

  var FORM_ENDPOINT = "";

  var body = document.body;
  body.classList.add("is-ready");

  var params = new URLSearchParams(window.location.search);
  var TYPES = {
    supplier: { label: "Material supply", team: "procurement team" },
    home: { label: "Home re-construction", team: "residential projects team" },
    development: { label: "Development", team: "business development team" }
  };

  // Careers enquiries belong on the careers page
  var asked = (params.get("type") || window.location.hash.slice(1) || "").toLowerCase();
  if (/^(careers?|jobs?)$/.test(asked)) {
    window.location.replace("careers.html");
    return;
  }

  var form = document.getElementById("docket");
  var empty = document.getElementById("docket-empty");
  var fields = document.getElementById("docket-fields");
  var done = document.getElementById("docket-done");
  var typeOut = document.getElementById("docket-type");
  var refOut = document.getElementById("docket-ref");
  var errorOut = document.getElementById("docket-error");
  var radios = Array.prototype.slice.call(document.querySelectorAll('input[name="enquiry-type"]'));
  var scoped = Array.prototype.slice.call(form.querySelectorAll("[data-for]"));
  var current = null;

  /* Docket header: reference and date */
  var ref = "ARG-" + String(new Date().getFullYear()).slice(2) + "-" + String(Math.floor(1000 + Math.random() * 9000));
  refOut.textContent = ref;
  document.getElementById("docket-date").textContent = new Date().toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });

  /* Type selection */
  function setType(type, scroll) {
    if (!TYPES[type]) return;
    var first = current === null;
    current = type;
    radios.forEach(function (r) {
      r.checked = r.value === type;
      r.closest(".type").classList.toggle("is-checked", r.checked);
    });
    typeOut.textContent = TYPES[type].label;
    scoped.forEach(function (el) {
      el.classList.toggle("is-active", el.getAttribute("data-for").split(" ").indexOf(type) !== -1);
    });
    empty.hidden = true;
    done.hidden = true;
    fields.hidden = false;
    if (first) {
      fields.classList.remove("is-entering");
      void fields.offsetWidth;
      fields.classList.add("is-entering");
    }
    clearErrors();
    if (scroll) {
      var top = form.getBoundingClientRect().top + window.scrollY - 110;
      window.scrollTo({ top: top, behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
    }
  }
  radios.forEach(function (r) {
    r.addEventListener("change", function () { setType(r.value, true); });
  });
  if (TYPES[asked]) setType(asked, false);

  /* Careers words in the message: point to the careers page */
  var message = document.getElementById("f-message");
  var hint = document.getElementById("careers-hint");
  var careersWords = /\b(job|jobs|career|careers|vacanc(y|ies)|opening|openings|resume|r[eé]sum[eé]|cv|internship|intern|hiring|apply|application for (the )?post)\b/i;
  message.addEventListener("input", function () { hint.hidden = !careersWords.test(message.value); });

  /* Validation */
  function fieldWrap(el) { return el.closest(".field") || el.closest(".consent"); }
  function isActive(el) { return !el.closest(".group:not(.is-active)") && !el.closest("[data-for]:not(.is-active)"); }
  function required(el) {
    if (el.required) return true;
    var only = el.getAttribute("data-required-for");
    return !!only && only.split(" ").indexOf(current) !== -1;
  }
  function setInvalid(el, msg) {
    var wrap = fieldWrap(el);
    wrap.classList.add("is-invalid");
    if (wrap.classList.contains("field") && !wrap.querySelector(".field__msg")) {
      var p = document.createElement("p");
      p.className = "field__msg";
      p.textContent = msg;
      wrap.appendChild(p);
    }
    el.setAttribute("aria-invalid", "true");
  }
  function clearErrors() {
    Array.prototype.forEach.call(form.querySelectorAll(".is-invalid"), function (w) { w.classList.remove("is-invalid"); });
    Array.prototype.forEach.call(form.querySelectorAll(".field__msg"), function (m) { m.remove(); });
    Array.prototype.forEach.call(form.querySelectorAll("[aria-invalid]"), function (el) { el.removeAttribute("aria-invalid"); });
    errorOut.hidden = true;
  }
  form.addEventListener("input", function (e) {
    var wrap = fieldWrap(e.target);
    if (wrap && wrap.classList.contains("is-invalid")) {
      wrap.classList.remove("is-invalid");
      var m = wrap.querySelector(".field__msg");
      if (m) m.remove();
      e.target.removeAttribute("aria-invalid");
    }
  });

  function validate() {
    clearErrors();
    var firstBad = null;
    Array.prototype.forEach.call(form.querySelectorAll("input, select, textarea"), function (el) {
      if (!isActive(el) || el.type === "radio" || el.type === "checkbox" && el.id !== "f-consent") return;
      var v = (el.value || "").trim();
      var bad = null;
      if (el.id === "f-consent") { if (!el.checked) bad = "Please tick to continue."; }
      else if (required(el) && !v) bad = "Please fill this in.";
      else if (v && el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) bad = "Enter an email like name@company.com.";
      else if (v && el.type === "tel" && v.replace(/\D/g, "").length < 10) bad = "Enter a phone number with at least 10 digits.";
      else if (v && el.type === "url" && !/^https?:\/\/\S+\.\S+/.test(v)) bad = "Enter a full link starting with https://";
      if (bad) { setInvalid(el, bad); if (!firstBad) firstBad = el; }
    });
    if (current === "supplier" && !form.querySelector('input[name="materials"]:checked')) {
      errorOut.textContent = "Choose at least one material you supply.";
      errorOut.hidden = false;
      if (!firstBad) firstBad = form.querySelector('input[name="materials"]');
    }
    if (firstBad) firstBad.focus();
    return !firstBad;
  }

  /* Submit */
  function collect() {
    var data = { enquiry_type: TYPES[current].label, reference: ref };
    Array.prototype.forEach.call(form.querySelectorAll("input, select, textarea"), function (el) {
      if (!el.name || !isActive(el) || el.name === "enquiry-type") return;
      if (el.type === "checkbox" && el.name === "materials") {
        if (el.checked) data.materials = (data.materials ? data.materials + ", " : "") + el.value;
      } else if (el.type === "checkbox" || el.type === "radio") {
        if (el.checked) data[el.name] = el.type === "checkbox" ? "yes" : el.value;
      } else if (el.value.trim()) {
        data[el.name] = el.value.trim();
      }
    });
    return data;
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!current) return;
    if (document.getElementById("f-website").value) return; // spam trap
    if (!validate()) return;

    var btn = form.querySelector(".docket__btn");
    var data = collect();
    btn.disabled = true;
    btn.firstElementChild.textContent = "Sending…";

    var send = FORM_ENDPOINT
      ? fetch(FORM_ENDPOINT, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify(data)
        }).then(function (r) { if (!r.ok) throw new Error(r.status); })
      : new Promise(function (resolve) {
          if (window.console) console.warn("Argentec contact form: FORM_ENDPOINT is not set, enquiry not sent.", data);
          setTimeout(resolve, 600);
        });

    send.then(function () {
      document.getElementById("done-name").textContent = (data.name || "").split(" ")[0] || "there";
      document.getElementById("done-ref").textContent = ref;
      document.getElementById("stamp-ref").textContent = ref;
      document.getElementById("done-team").textContent = TYPES[current].team;
      fields.hidden = true;
      done.hidden = false;
      done.focus();
    }).catch(function () {
      errorOut.textContent = "Your enquiry couldn't be sent. Check your connection and try again.";
      errorOut.hidden = false;
    }).then(function () {
      btn.disabled = false;
      btn.firstElementChild.textContent = "Send enquiry";
    });
  });

  document.getElementById("docket-again").addEventListener("click", function () {
    form.reset();
    hint.hidden = true;
    ref = "ARG-" + String(new Date().getFullYear()).slice(2) + "-" + String(Math.floor(1000 + Math.random() * 9000));
    refOut.textContent = ref;
    var t = current;
    current = null;
    setType(t, true);
  });
})();
