/* Argentec admin panel (admin/index.html).
   Sign in with a Supabase admin account, review careers applications
   (status, notes, resume download, CSV export) and manage job postings.
   Database rules live in supabase/setup.sql: only users listed in the
   admins table can read applications or change job postings. */
(function () {
  "use strict";

  var cfg = window.ARGENTEC_BACKEND || {};
  var $ = function (id) { return document.getElementById(id); };
  function show(id) { ["setup", "login", "app"].forEach(function (v) { $(v).hidden = v !== id; }); }

  if (!cfg.url || !cfg.anonKey || !window.supabase) { show("setup"); return; }
  var db = window.supabase.createClient(cfg.url, cfg.anonKey);

  var STATUS = { new: "New", shortlisted: "Shortlisted", interview: "Interview", offered: "Offered", hired: "Hired", rejected: "Rejected" };
  var JOB_STATUS = { open: "Open", draft: "Draft", closed: "Closed" };
  var apps = [], jobs = [], current = null, editing = null;

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function date(d, withTime) {
    var x = new Date(d);
    var s = x.toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
    return withTime ? s + ", " + x.toLocaleTimeString("en-IN", { hour: "numeric", minute: "2-digit" }) : s;
  }
  function lakhs(v) { return v == null ? "–" : String(Number(v)); }
  function toast(msg, bad) {
    var t = $("toast");
    t.textContent = msg;
    t.classList.toggle("is-bad", !!bad);
    t.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { t.hidden = true; }, 3200);
  }
  function fail(err, msg) {
    if (window.console) console.error(err);
    toast(msg || "Something went wrong. Please try again.", true);
  }

  /* ----------------------------------------------------------------- auth */
  var loginForm = $("login-form");
  var passToggle = loginForm.querySelector(".ad-pass__toggle");
  passToggle.addEventListener("click", function () {
    var input = loginForm.querySelector('input[name="password"]');
    var showing = input.type === "text";
    input.type = showing ? "password" : "text";
    passToggle.textContent = showing ? "Show" : "Hide";
    passToggle.setAttribute("aria-pressed", showing ? "false" : "true");
  });

  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var err = $("login-error");
    var email = loginForm.email.value.trim(), password = loginForm.password.value;
    err.hidden = true;
    if (!email || !password) { err.textContent = "Enter your email and password."; err.hidden = false; return; }
    var btn = loginForm.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = "Signing in…";
    db.auth.signInWithPassword({ email: email, password: password }).then(function (res) {
      if (res.error) throw res.error;
      return enter(res.data.session);
    }).catch(function (e2) {
      err.textContent = /invalid/i.test(e2 && e2.message || "") ? "Email or password is incorrect." : (e2 && e2.message) || "Could not sign in.";
      err.hidden = false;
    }).then(function () { btn.disabled = false; btn.textContent = "Sign in"; });
  });

  function enter(session) {
    if (!session) { show("login"); return Promise.resolve(); }
    return db.rpc("is_admin").then(function (res) {
      if (res.error) throw res.error;
      if (res.data !== true) {
        return db.auth.signOut().then(function () {
          show("login");
          var err = $("login-error");
          err.textContent = "This account does not have admin access.";
          err.hidden = false;
        });
      }
      $("user-email").textContent = session.user.email;
      show("app");
      return Promise.all([loadJobs(), loadApps()]);
    });
  }

  $("sign-out").addEventListener("click", function () {
    db.auth.signOut().then(function () { loginForm.reset(); show("login"); });
  });

  db.auth.getSession().then(function (res) { return enter(res.data && res.data.session); })
    .catch(function (e) { fail(e, "Could not reach the database."); show("login"); });

  /* ----------------------------------------------------------------- tabs */
  Array.prototype.forEach.call(document.querySelectorAll(".ad-tab"), function (tab) {
    tab.addEventListener("click", function () {
      Array.prototype.forEach.call(document.querySelectorAll(".ad-tab"), function (t) {
        var on = t === tab;
        t.classList.toggle("is-active", on);
        t.setAttribute("aria-selected", on ? "true" : "false");
        $("view-" + t.getAttribute("data-view")).hidden = !on;
      });
    });
  });

  /* --------------------------------------------------------- applications */
  function loadApps() {
    return db.from("applications").select("*").order("created_at", { ascending: false }).then(function (res) {
      if (res.error) throw res.error;
      apps = res.data || [];
      fillRoleFilter();
      renderApps();
      if (jobs.length) renderJobs(); // refresh per-job application counts
    }).catch(function (e) { fail(e, "Could not load applications."); });
  }

  function fillRoleFilter() {
    var sel = $("app-role"), keep = sel.value;
    var titles = {};
    apps.forEach(function (a) { titles[a.job_title] = (titles[a.job_title] || 0) + 1; });
    sel.innerHTML = '<option value="">All roles</option>' + Object.keys(titles).sort().map(function (t) {
      return '<option value="' + esc(t) + '">' + esc(t) + " (" + titles[t] + ")</option>";
    }).join("");
    sel.value = keep;
  }

  function filtered() {
    var q = $("app-search").value.trim().toLowerCase();
    var role = $("app-role").value, st = $("app-status").value;
    return apps.filter(function (a) {
      if (role && a.job_title !== role) return false;
      if (st && a.status !== st) return false;
      if (!q) return true;
      return [a.name, a.email, a.phone, a.reference, a.location, a.current_company].join(" ").toLowerCase().indexOf(q) !== -1;
    });
  }

  function badge(st) { return '<span class="ad-badge ad-badge--' + esc(st) + '">' + esc(STATUS[st] || st) + "</span>"; }

  function renderApps() {
    var rows = filtered();
    $("app-rows").innerHTML = rows.map(function (a) {
      return '<tr tabindex="0" data-id="' + esc(a.id) + '">' +
        "<td><b>" + esc(date(a.created_at)) + "</b><small>" + esc(a.reference) + "</small></td>" +
        "<td><b>" + esc(a.name) + "</b><small>" + esc(a.email) + "</small></td>" +
        "<td>" + esc(a.job_title) + "</td>" +
        "<td>" + esc(a.experience || "–") + "</td>" +
        "<td>" + lakhs(a.current_ctc) + " &rarr; " + lakhs(a.expected_ctc) + "</td>" +
        "<td>" + badge(a.status) + "</td></tr>";
    }).join("");
    $("app-empty").hidden = rows.length > 0;
    var fresh = apps.filter(function (a) { return a.status === "new"; }).length;
    $("count-new").textContent = fresh;
    $("count-new").hidden = !fresh;
    $("app-summary").textContent = apps.length + " application" + (apps.length === 1 ? "" : "s") +
      (fresh ? " · " + fresh + " new" : "") + (rows.length !== apps.length ? " · showing " + rows.length : "");
  }

  ["app-search", "app-role", "app-status"].forEach(function (id) {
    $(id).addEventListener(id === "app-search" ? "input" : "change", renderApps);
  });
  $("app-refresh").addEventListener("click", function () { loadApps().then(function () { toast("Applications updated."); }); });

  $("app-rows").addEventListener("click", function (e) {
    var tr = e.target.closest("tr[data-id]");
    if (tr) openApp(tr.getAttribute("data-id"));
  });
  $("app-rows").addEventListener("keydown", function (e) {
    var tr = e.target.closest("tr[data-id]");
    if (tr && (e.key === "Enter" || e.key === " ")) { e.preventDefault(); openApp(tr.getAttribute("data-id")); }
  });

  function openApp(id) {
    current = apps.filter(function (a) { return a.id === id; })[0];
    if (!current) return;
    var a = current;
    $("app-d-name").textContent = a.name;
    $("app-d-meta").textContent = a.job_title + " · " + date(a.created_at, true) + " · " + a.reference;
    $("app-d-mail").href = "mailto:" + a.email;
    $("app-d-call").href = "tel:" + a.phone.replace(/[^0-9+]/g, "");
    $("app-d-resume").disabled = !a.resume_path;
    var fields = [
      ["Email", a.email], ["Mobile", a.phone], ["Location", a.location], ["Experience", a.experience],
      ["Current company", a.current_company], ["Current CTC", a.current_ctc == null ? "" : lakhs(a.current_ctc) + " lakhs p.a."],
      ["Expected CTC", a.expected_ctc == null ? "" : lakhs(a.expected_ctc) + " lakhs p.a."],
      ["LinkedIn", a.linkedin]
    ];
    $("app-d-fields").innerHTML = fields.filter(function (f) { return f[1]; }).map(function (f) {
      var v = f[0] === "LinkedIn" && /^https?:\/\//i.test(f[1])
        ? '<a href="' + esc(f[1]) + '" target="_blank" rel="noopener noreferrer">' + esc(f[1]) + "</a>" : esc(f[1]);
      return "<div><dt>" + esc(f[0]) + "</dt><dd>" + v + "</dd></div>";
    }).join("");
    $("app-d-msg-wrap").hidden = !a.message;
    $("app-d-msg").textContent = a.message || "";
    var form = $("app-d-form");
    form.status.value = a.status;
    form.notes.value = a.notes || "";
    $("app-d-saved").hidden = true;
    openDrawer("app-drawer");
  }

  $("app-d-resume").addEventListener("click", function () {
    if (!current || !current.resume_path) return;
    var win = window.open("", "_blank");
    db.storage.from("resumes").createSignedUrl(current.resume_path, 120).then(function (res) {
      if (res.error) throw res.error;
      if (win) win.location = res.data.signedUrl; else window.location = res.data.signedUrl;
    }).catch(function (e) { if (win) win.close(); fail(e, "Could not open the resume."); });
  });

  $("app-d-form").addEventListener("submit", function (e) {
    e.preventDefault();
    var form = e.target, a = current;
    var patch = { status: form.status.value, notes: form.notes.value.trim() || null };
    db.from("applications").update(patch).eq("id", a.id).then(function (res) {
      if (res.error) throw res.error;
      a.status = patch.status; a.notes = patch.notes;
      renderApps();
      $("app-d-saved").hidden = false;
      toast("Application updated.");
    }).catch(function (e2) { fail(e2, "Could not save."); });
  });

  $("app-d-delete").addEventListener("click", function () {
    var a = current;
    if (!a || !window.confirm("Delete the application from " + a.name + "? This also deletes the resume and cannot be undone.")) return;
    var removeFile = a.resume_path ? db.storage.from("resumes").remove([a.resume_path]) : Promise.resolve({});
    removeFile.then(function () { return db.from("applications").delete().eq("id", a.id); }).then(function (res) {
      if (res.error) throw res.error;
      apps = apps.filter(function (x) { return x.id !== a.id; });
      fillRoleFilter(); renderApps(); closeDrawers();
      toast("Application deleted.");
    }).catch(function (e) { fail(e, "Could not delete."); });
  });

  $("app-export").addEventListener("click", function () {
    var rows = filtered();
    var cols = [["reference", "Reference"], ["created_at", "Received"], ["job_title", "Role"], ["name", "Name"], ["email", "Email"],
      ["phone", "Mobile"], ["location", "Location"], ["experience", "Experience"], ["current_company", "Current company"],
      ["current_ctc", "Current CTC (L)"], ["expected_ctc", "Expected CTC (L)"], ["linkedin", "LinkedIn"],
      ["message", "Thoughts or questions"], ["status", "Status"], ["notes", "Notes"]];
    function cell(v) {
      var s = v == null ? "" : String(v);
      if (/^[=+\-@]/.test(s)) s = "'" + s; // stop spreadsheet formula injection
      return '"' + s.replace(/"/g, '""') + '"';
    }
    var csv = [cols.map(function (c) { return cell(c[1]); }).join(",")].concat(rows.map(function (a) {
      return cols.map(function (c) { return cell(c[0] === "created_at" ? date(a.created_at, true) : a[c[0]]); }).join(",");
    })).join("\r\n");
    var link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" }));
    link.download = "argentec-applications-" + new Date().toISOString().slice(0, 10) + ".csv";
    document.body.appendChild(link); link.click(); link.remove();
  });

  /* --------------------------------------------------------- job postings */
  function loadJobs() {
    return db.from("job_postings").select("*").order("sort_order", { ascending: true }).order("created_at", { ascending: false })
      .then(function (res) {
        if (res.error) throw res.error;
        jobs = res.data || [];
        renderJobs();
      }).catch(function (e) { fail(e, "Could not load job postings."); });
  }

  function renderJobs() {
    var counts = {};
    apps.forEach(function (a) { if (a.job_id) counts[a.job_id] = (counts[a.job_id] || 0) + 1; });
    $("job-list").innerHTML = jobs.map(function (j) {
      var n = counts[j.id] || 0;
      return '<li class="ad-job" data-id="' + esc(j.id) + '">' +
        '<div class="ad-job__main"><span class="ad-badge ad-badge--' + esc(j.status) + '">' + esc(JOB_STATUS[j.status]) + "</span>" +
        "<h3>" + esc(j.title) + "</h3>" +
        "<p>" + [j.department, j.location, j.employment_type, j.experience].filter(Boolean).map(esc).join(" · ") + "</p>" +
        "<small>Updated " + esc(date(j.updated_at)) + " · " + n + " application" + (n === 1 ? "" : "s") + "</small></div>" +
        '<div class="ad-job__actions">' +
        '<button class="ad-btn ad-btn--ghost" type="button" data-act="edit">Edit</button>' +
        (j.status === "open"
          ? '<button class="ad-btn ad-btn--ghost" type="button" data-act="close">Close</button>'
          : '<button class="ad-btn ad-btn--ghost" type="button" data-act="open">Publish</button>') +
        '<button class="ad-btn ad-btn--danger" type="button" data-act="delete">Delete</button></div></li>';
    }).join("");
    $("job-empty").hidden = jobs.length > 0;
    var open = jobs.filter(function (j) { return j.status === "open"; }).length;
    $("job-summary").textContent = jobs.length + " posting" + (jobs.length === 1 ? "" : "s") + " · " + open + " live on the website";
  }

  $("job-list").addEventListener("click", function (e) {
    var btn = e.target.closest("[data-act]");
    if (!btn) return;
    var id = btn.closest(".ad-job").getAttribute("data-id");
    var job = jobs.filter(function (j) { return j.id === id; })[0];
    var act = btn.getAttribute("data-act");
    if (act === "edit") return openJob(job);
    if (act === "delete") {
      if (!window.confirm("Delete “" + job.title + "”? Applications already received for it are kept.")) return;
      return db.from("job_postings").delete().eq("id", id).then(function (res) {
        if (res.error) throw res.error;
        toast("Job posting deleted."); return loadJobs();
      }).catch(function (e2) { fail(e2, "Could not delete."); });
    }
    var status = act === "open" ? "open" : "closed";
    db.from("job_postings").update({ status: status }).eq("id", id).then(function (res) {
      if (res.error) throw res.error;
      toast(status === "open" ? "“" + job.title + "” is live on the careers page." : "“" + job.title + "” is closed.");
      return loadJobs();
    }).catch(function (e2) { fail(e2); });
  });

  function lines(s) { return String(s || "").split(/\r?\n/).map(function (x) { return x.replace(/^[\s•*-]+/, "").trim(); }).filter(Boolean); }

  function openJob(job) {
    editing = job || null;
    var f = $("job-form");
    f.reset();
    $("job-error").hidden = true;
    $("job-d-title").textContent = job ? "Edit job posting" : "New job posting";
    if (job) {
      ["title", "department", "location", "employment_type", "experience", "summary", "sort_order"].forEach(function (k) {
        if (f[k]) f[k].value = job[k] == null ? "" : job[k];
      });
      f.duties.value = (job.duties || []).join("\n");
      f.requirements.value = (job.requirements || []).join("\n");
      f.querySelector('input[name="status"][value="' + job.status + '"]').checked = true;
    }
    syncSaveLabel();
    openDrawer("job-drawer");
    setTimeout(function () { f.title.focus(); }, 50);
  }
  function syncSaveLabel() {
    var st = $("job-form").querySelector('input[name="status"]:checked').value;
    $("job-save").textContent = st === "open" ? (editing ? "Save and publish" : "Publish") : "Save";
  }
  $("job-form").addEventListener("change", function (e) { if (e.target.name === "status") syncSaveLabel(); });
  $("job-form").addEventListener("input", function (e) {
    var w = e.target.closest(".ad-field");
    if (w && e.target.value.trim()) w.classList.remove("is-invalid");
    if (!$("job-form").querySelector(".is-invalid")) $("job-error").hidden = true;
  });
  $("job-new").addEventListener("click", function () { openJob(null); });

  $("job-form").addEventListener("submit", function (e) {
    e.preventDefault();
    var f = e.target, err = $("job-error");
    var missing = ["title", "department", "location"].filter(function (k) { return !f[k].value.trim(); });
    Array.prototype.forEach.call(f.querySelectorAll(".is-invalid"), function (x) { x.classList.remove("is-invalid"); });
    if (missing.length) {
      missing.forEach(function (k) { f[k].closest(".ad-field").classList.add("is-invalid"); });
      err.textContent = "Please fill in the job title, department and location.";
      err.hidden = false; f[missing[0]].focus(); return;
    }
    var row = {
      title: f.title.value.trim(), department: f.department.value.trim(), location: f.location.value.trim(),
      employment_type: f.employment_type.value, experience: f.experience.value.trim() || null,
      summary: f.summary.value.trim() || null, duties: lines(f.duties.value), requirements: lines(f.requirements.value),
      status: f.querySelector('input[name="status"]:checked').value, sort_order: parseInt(f.sort_order.value, 10) || 0
    };
    var btn = $("job-save"); btn.disabled = true;
    var q = editing ? db.from("job_postings").update(row).eq("id", editing.id) : db.from("job_postings").insert(row);
    q.then(function (res) {
      if (res.error) throw res.error;
      closeDrawers();
      toast(row.status === "open" ? "“" + row.title + "” is live on the careers page." : "Job posting saved.");
      return loadJobs();
    }).catch(function (e2) { err.textContent = (e2 && e2.message) || "Could not save."; err.hidden = false; })
      .then(function () { btn.disabled = false; });
  });

  /* -------------------------------------------------------------- drawers */
  var lastFocus = null;
  function openDrawer(id) {
    lastFocus = document.activeElement;
    var d = $(id);
    d.hidden = false;
    requestAnimationFrame(function () { d.classList.add("is-open"); });
    var first = d.querySelector(".ad-close");
    if (first) first.focus();
  }
  function closeDrawers() {
    Array.prototype.forEach.call(document.querySelectorAll(".ad-drawer"), function (d) {
      if (d.hidden) return;
      d.classList.remove("is-open");
      setTimeout(function () { d.hidden = true; }, 300);
    });
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  Array.prototype.forEach.call(document.querySelectorAll(".ad-drawer"), function (d) {
    d.addEventListener("click", function (e) {
      if (e.target === d || e.target.closest("[data-close]")) closeDrawers();
    });
  });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeDrawers(); });
})();
