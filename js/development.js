/* Development page: register-interest form (role switches the fields,
   validation, submission) and the hero entrance.

   FORM DELIVERY: set FORM_ENDPOINT to a form service URL (for example a
   Formspree or Web3Forms endpoint). While it is empty the form validates and
   shows the thank-you state, but nothing is sent anywhere. */
(function () {
  "use strict";

  var FORM_ENDPOINT = "";

  var form = document.getElementById("dv-form");
  if (!form) return;
  var done = document.getElementById("dv-done");
  var errorOut = document.getElementById("dv-error");
  var refOut = document.getElementById("dv-ref");
  var submit = form.querySelector(".dv-form__submit");
  var roles = Array.prototype.slice.call(form.querySelectorAll('input[name="role"]'));
  var scoped = Array.prototype.slice.call(form.querySelectorAll("[data-role]"));

  function role() {
    for (var i = 0; i < roles.length; i++) if (roles[i].checked) return roles[i].value;
    return "homebuyer";
  }

  function setRole(value) {
    roles.forEach(function (r) { r.checked = r.value === value; });
    scoped.forEach(function (el) {
      var on = el.getAttribute("data-role") === value;
      el.hidden = !on;
      Array.prototype.forEach.call(el.querySelectorAll("input, select, textarea"), function (f) {
        f.disabled = !on;
        if (f.hasAttribute("data-required")) f.required = on;
      });
    });
    clearErrors();
  }

  roles.forEach(function (r) { r.addEventListener("change", function () { setRole(r.value); }); });

  // ?role=society (from the audience cards and the redevelopment CTA)
  var asked = new URLSearchParams(window.location.search).get("role");
  setRole(/^(homebuyer|broker|society)$/.test(asked || "") ? asked : "homebuyer");
  Array.prototype.forEach.call(document.querySelectorAll("[data-role-link]"), function (a) {
    a.addEventListener("click", function (e) {
      e.preventDefault();
      setRole(a.getAttribute("data-role-link"));
      var target = document.getElementById("register");
      target.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
      if (window.history && history.replaceState) history.replaceState(null, "", "?role=" + a.getAttribute("data-role-link") + "#register");
      setTimeout(function () { form.querySelector('input[name="name"]').focus({ preventScroll: true }); }, 700);
    });
  });

  /* Validation */
  function wrap(el) { return el.closest(".dv-field") || el.closest(".dv-consent"); }
  function clearErrors() {
    Array.prototype.forEach.call(form.querySelectorAll(".is-invalid"), function (w) { w.classList.remove("is-invalid"); });
    Array.prototype.forEach.call(form.querySelectorAll("[aria-invalid]"), function (el) { el.removeAttribute("aria-invalid"); });
    errorOut.hidden = true;
  }
  function message(el) {
    if (el.type === "checkbox") return "Please agree to be contacted so we can reply.";
    if (el.validity.valueMissing) return "Please fill in the highlighted fields.";
    if (el.type === "email") return "Please check the email address.";
    if (el.type === "tel") return "Please enter a valid mobile number.";
    return "Please check the highlighted fields.";
  }
  form.addEventListener("input", function (e) {
    var w = wrap(e.target);
    if (w && e.target.checkValidity()) { w.classList.remove("is-invalid"); e.target.removeAttribute("aria-invalid"); }
  });

  function newRef() {
    var d = new Date();
    var ymd = String(d.getFullYear()).slice(2) + String(d.getMonth() + 1).padStart(2, "0") + String(d.getDate()).padStart(2, "0");
    return "ADV-" + ymd + "-" + String(Math.floor(1000 + Math.random() * 9000));
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    clearErrors();
    var bad = Array.prototype.filter.call(form.querySelectorAll("input, select, textarea"), function (el) {
      return !el.disabled && !el.checkValidity();
    });
    if (bad.length) {
      bad.forEach(function (el) { var w = wrap(el); if (w) w.classList.add("is-invalid"); el.setAttribute("aria-invalid", "true"); });
      errorOut.textContent = message(bad[0]);
      errorOut.hidden = false;
      bad[0].focus();
      return;
    }

    var ref = newRef();
    var data = { reference: ref, role: role(), page: "development" };
    Array.prototype.forEach.call(form.querySelectorAll("input, select, textarea"), function (el) {
      if (el.disabled || el.name === "role") return;
      data[el.name] = el.type === "checkbox" ? el.checked : el.value.trim();
    });

    submit.disabled = true;
    var send = FORM_ENDPOINT
      ? fetch(FORM_ENDPOINT, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify(data)
        }).then(function (r) { if (!r.ok) throw new Error(r.status); })
      : new Promise(function (resolve) {
          if (window.console) console.warn("Argentec development form: FORM_ENDPOINT is not set, registration not sent.", data);
          setTimeout(resolve, 500);
        });

    send.then(function () {
      refOut.textContent = ref;
      form.classList.add("is-done");
      done.hidden = false;
      done.focus();
    }).catch(function () {
      errorOut.textContent = "Something went wrong while sending. Please try again in a moment.";
      errorOut.hidden = false;
    }).then(function () { submit.disabled = false; });
  });

  document.getElementById("dv-again").addEventListener("click", function () {
    var keep = role();
    form.reset();
    setRole(keep);
    form.classList.remove("is-done");
    done.hidden = true;
    form.querySelector('input[name="name"]').focus();
  });
})();
