/* Careers page, live mode: when js/backend-config.js is filled in, open
   roles come from the job_postings table (managed in /admin/), and
   applications, with the resume, are saved to Supabase.
   Loaded before js/company.js, which calls window.ARGENTEC_SUBMIT.careers. */
(function () {
  "use strict";

  var cfg = window.ARGENTEC_BACKEND || {};
  if (!cfg.url || !cfg.anonKey || !window.supabase) return;
  var db = window.supabase.createClient(cfg.url, cfg.anonKey, { auth: { persistSession: false } });

  var PLUS = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2v12M2 8h12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function slug(s) { return String(s).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, ""); }

  /* ------------------------------------------------------------ open roles */
  var list = document.querySelector("[data-roles]");
  var filters = document.querySelector(".co-filters");
  var select = document.querySelector('.co-form select[name="role"]');
  if (list) list.classList.add("is-loading");

  function role(r) {
    var meta = [r.department, r.location, r.experience].filter(Boolean);
    return '<li class="co-role" data-dept="' + esc(slug(r.department)) + '">' +
      '<details><summary><span class="co-role__title">' + esc(r.title) + '</span>' +
      '<span class="co-role__meta">' + meta.map(function (m) { return "<span>" + esc(m) + "</span>"; }).join("") + "</span>" +
      '<span class="co-role__toggle">' + PLUS + "</span></summary>" +
      '<div class="co-role__body">' +
      (r.summary ? '<p class="co-role__summary">' + esc(r.summary) + "</p>" : "") +
      '<div class="co-role__cols">' +
      ((r.duties || []).length ? "<div><h4>What you will do</h4><ul>" + r.duties.map(function (x) { return "<li>" + esc(x) + "</li>"; }).join("") + "</ul></div>" : "") +
      ((r.requirements || []).length ? "<div><h4>What you bring</h4><ul>" + r.requirements.map(function (x) { return "<li>" + esc(x) + "</li>"; }).join("") + "</ul></div>" : "") +
      "</div>" +
      '<p class="co-role__facts">' + [r.employment_type, r.location, r.experience ? r.experience + " experience" : ""].filter(Boolean).map(esc).join(" &middot; ") + "</p>" +
      '<a class="btn btn--navy co-role__apply" href="#apply" data-apply="' + esc(r.id) + '">Apply for this role</a>' +
      "</div></details></li>";
  }

  function render(rows) {
    var counts = {};
    rows.forEach(function (r) { counts[r.department] = (counts[r.department] || 0) + 1; });
    list.innerHTML = rows.length ? rows.map(role).join("")
      : '<li class="co-empty">There are no open roles right now. Send a general application below and we will keep your resume on file.</li>';
    var chips = ['<button class="co-filter is-active" type="button" data-filter="all" aria-pressed="true">All <span>' + rows.length + "</span></button>"];
    Object.keys(counts).forEach(function (d) {
      chips.push('<button class="co-filter" type="button" data-filter="' + esc(slug(d)) + '" aria-pressed="false">' + esc(d) + " <span>" + counts[d] + "</span></button>");
    });
    filters.innerHTML = chips.join("");
    filters.hidden = rows.length === 0;
    Array.prototype.forEach.call(document.querySelectorAll(".co-dept"), function (li) {
      var n = counts[li.getAttribute("data-dept-name")] || 0;
      li.querySelector("b").innerHTML = n ? n + " open" : "&nbsp;";
    });
    if (select) {
      select.innerHTML = '<option value="general">General application</option>' +
        rows.map(function (r) { return '<option value="' + esc(r.id) + '">' + esc(r.title) + "</option>"; }).join("");
    }
    document.dispatchEvent(new CustomEvent("careers:roles"));
  }

  if (list) {
    db.from("job_postings").select("*").eq("status", "open")
      .order("sort_order", { ascending: true }).order("created_at", { ascending: false })
      .then(function (res) {
        list.classList.remove("is-loading");
        if (res.error) { if (window.console) console.warn("Careers: could not load roles, showing the built-in list.", res.error); return; }
        render(res.data || []);
      });
  }

  /* ---------------------------------------------------------- applications */
  function num(v) { return v === "" || v == null ? null : Number(v); }
  window.ARGENTEC_SUBMIT = window.ARGENTEC_SUBMIT || {};
  window.ARGENTEC_SUBMIT.careers = function (form, ref) {
    var f = function (n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ""; };
    var file = form.querySelector('input[name="resume"]').files[0];
    var roleSel = form.querySelector('select[name="role"]');
    var jobId = roleSel.value;
    var isUuid = /^[0-9a-f-]{36}$/i.test(jobId);
    var path = ref + "/" + file.name.replace(/[^A-Za-z0-9._-]+/g, "_").slice(-80);
    return db.storage.from("resumes").upload(path, file, { contentType: file.type || undefined, upsert: false })
      .then(function (up) {
        if (up.error) throw up.error;
        return db.from("applications").insert({
          reference: ref,
          job_id: isUuid ? jobId : null,
          job_title: roleSel.options[roleSel.selectedIndex].text,
          name: f("name"), phone: f("phone"), email: f("email"), location: f("location"),
          experience: f("experience"), current_company: f("company") || null,
          current_ctc: num(f("current_ctc")), expected_ctc: num(f("expected_ctc")),
          resume_path: path, linkedin: f("linkedin") || null, message: f("message") || null,
          consent: form.querySelector('input[name="consent"]').checked
        });
      })
      .then(function (ins) { if (ins.error) throw ins.error; });
  };
})();
