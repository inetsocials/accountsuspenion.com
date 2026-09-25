/* accountsuspension.com: all site behaviour. No dependencies. */
(function () {
  "use strict";
  var doc = document;
  doc.documentElement.classList.add("js");

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var REF_RE = /^AS-[A-F0-9]{8}$/;
  var params = new URLSearchParams(window.location.search);

  function $(sel, root) { return (root || doc).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

  /* footer year */
  $$("[data-year]").forEach(function (el) { el.textContent = String(new Date().getFullYear()); });

  /* reveal on scroll */
  var reveals = $$(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { rootMargin: "0px 0px -40px 0px", threshold: 0.05 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("in"); });
  }

  /* close mobile menu on link click and Escape */
  var toggle = $("#nav-toggle");
  if (toggle) {
    $$(".mnav a").forEach(function (a) { a.addEventListener("click", function () { toggle.checked = false; }); });
    doc.addEventListener("keydown", function (ev) { if (ev.key === "Escape") toggle.checked = false; });
  }

  /* live filters: input[data-filter-target] -> [data-filter-item] */
  $$("input[data-filter-target]").forEach(function (input) {
    var root = $(input.getAttribute("data-filter-target"));
    if (!root) return;
    var countEl = input.parentNode.querySelector("[data-filter-count]");
    var items = $$("[data-filter-item]", root);
    var groups = $$("[data-filter-group]", root);
    function run() {
      var q = input.value.trim().toLowerCase();
      var terms = q.split(/\s+/).filter(Boolean);
      var shown = 0;
      items.forEach(function (it) {
        var t = it.getAttribute("data-text") || "";
        var ok = terms.every(function (w) { return t.indexOf(w) !== -1; });
        it.hidden = !ok;
        if (ok) shown++;
      });
      groups.forEach(function (g) {
        if (g.classList.contains("cont")) return;
        g.hidden = !$$("[data-filter-item]", g).some(function (it) { return !it.hidden; });
      });
      if (countEl) countEl.textContent = q ? shown + " of " + items.length + " shown" : items.length + " listed";
    }
    input.addEventListener("input", run);
    run();
  });

  /* Reinstatement Readiness Score */
  var rs = $("#rs-form");
  if (rs) {
    rs.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var qs = $$("fieldset.q", rs);
      var total = 0, max = 0, gaps = [], missing = false;
      qs.forEach(function (fs) {
        var w = parseFloat(fs.getAttribute("data-w")) || 0;
        var picked = $("input:checked", fs);
        max += w;
        if (!picked) { missing = true; return; }
        var v = parseFloat(picked.value);
        total += w * v;
        if (v < 1) gaps.push({ w: w * (1 - v), text: fs.getAttribute("data-gap"), link: fs.getAttribute("data-link") });
      });
      var err = $("#rs-error");
      if (missing) { err.hidden = false; return; }
      err.hidden = true;
      var score = Math.round((total / max) * 100);
      var arc = $("#rs-arc");
      var C = 2 * Math.PI * 52;
      arc.classList.remove("mid", "low");
      if (score < 50) arc.classList.add("low"); else if (score < 75) arc.classList.add("mid");
      var out = $("#rs-result");
      out.hidden = false;
      requestAnimationFrame(function () { arc.setAttribute("stroke-dashoffset", String(C * (1 - score / 100))); });
      var n = 0, el = $("#rs-score");
      var t = setInterval(function () { n += Math.max(1, Math.round(score / 30)); if (n >= score) { n = score; clearInterval(t); } el.textContent = String(n); }, 30);
      var verdict, text;
      if (score >= 75) {
        verdict = "Well prepared";
        text = "Your preparation covers most of what reviewers look for. The remaining gaps are worth closing before you submit, because many platforms limit appeals.";
      } else if (score >= 50) {
        verdict = "Workable, with gaps";
        text = "The case has a foundation, but the gaps below are the kind that commonly lead to rejection. Close them before appealing.";
      } else {
        verdict = "Significant gaps";
        text = "Submitting now carries a real risk of rejection. Work on the gaps below first, and consider a case review before you use a limited appeal.";
      }
      $("#rs-verdict").textContent = verdict;
      $("#rs-text").textContent = text;
      var list = $("#rs-gaps");
      list.textContent = "";
      gaps.sort(function (a, b) { return b.w - a.w; }).slice(0, 4).forEach(function (g) {
        var li = doc.createElement("li");
        var a = doc.createElement("a");
        a.href = g.link; a.textContent = g.text;
        li.appendChild(a); list.appendChild(li);
      });
      if (!gaps.length) {
        var li = doc.createElement("li"); li.textContent = "No major gaps reported. Double-check document consistency before submitting."; list.appendChild(li);
      }
      var cta = $("#rs-cta");
      cta.href = cta.href.split("?")[0] + "?source=readiness&score=" + score;
      out.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  /* Multi-step intake */
  var form = $("#intake");
  if (form) {
    var panels = $$(".step-panel", form);
    var dots = $$(".stepper li", form);
    var status = $("#intake-status");
    var cur = 0;

    ["source", "score"].forEach(function (k) {
      var v = params.get(k);
      if (v && /^[a-z0-9_-]{1,40}$/i.test(v)) form.elements[k].value = v;
    });
    var plat = params.get("platform");
    if (plat && form.elements.platform) {
      var opt = $('option[value="' + plat.replace(/[^a-z0-9-]/gi, "") + '"]', form.elements.platform);
      if (opt) form.elements.platform.value = opt.value;
    }
    if (params.get("source") === "service" && params.get("service") === "priority") {
      var r = $('input[name="urgency"][value="deadline"]', form); if (r) r.checked = true;
    }
    form.elements.form_ts.value = String(Math.floor(Date.now() / 1000));

    function show(i) {
      cur = Math.max(0, Math.min(i, panels.length - 1));
      panels.forEach(function (p, k) { p.classList.toggle("active", k === cur); });
      dots.forEach(function (d, k) { d.classList.toggle("on", k <= cur); });
    }
    function valid(panel) {
      var ok = true;
      $$("input,select,textarea", panel).forEach(function (f) { f.classList.remove("field-err"); });
      var names = {};
      $$("input[type=radio][required]", panel).forEach(function (r) { names[r.name] = true; });
      Object.keys(names).forEach(function (n) {
        if (!$('input[name="' + n + '"]:checked', panel)) {
          ok = false; $$('input[name="' + n + '"]', panel).forEach(function (r) { r.parentNode.querySelector("span").classList.add("field-err"); });
        }
      });
      $$("input[required]:not([type=radio]),select[required],textarea[required]", panel).forEach(function (f) {
        var bad = f.type === "checkbox" ? !f.checked : !f.value.trim();
        if (f.type === "email" && f.value && !EMAIL_RE.test(f.value.trim())) bad = true;
        if (bad) { ok = false; f.classList.add("field-err"); }
      });
      if (!ok) {
        var first = $(".field-err", panel);
        if (first && first.focus) first.focus();
      }
      return ok;
    }
    function msg(text, kind) {
      if (!status) return;
      status.hidden = false; status.className = "form-status " + kind; status.textContent = text;
    }
    form.addEventListener("click", function (ev) {
      var t = ev.target.closest("[data-next],[data-prev]");
      if (!t) return;
      if (t.hasAttribute("data-next")) { if (valid(panels[cur])) show(cur + 1); }
      else show(cur - 1);
      form.scrollIntoView({ behavior: "smooth", block: "start" });
    });
    form.addEventListener("change", function (ev) {
      var f = ev.target;
      if (f.type === "radio") {
        $$('input[name="' + f.name + '"]', form).forEach(function (r) { r.parentNode.querySelector("span").classList.remove("field-err"); });
      } else {
        f.classList.remove("field-err");
      }
    });
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      for (var i = 0; i < panels.length; i++) { if (!valid(panels[i])) { show(i); msg("Please complete the highlighted fields.", "bad"); return; } }
      var btn = $('button[type="submit"]', form);
      btn.disabled = true; msg("Sending securely...", "ok");
      fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" }, credentials: "same-origin" })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (d) {
          if (d && d.ok && REF_RE.test(d.ref || "")) {
            window.location.href = form.getAttribute("data-thanks") + "?ref=" + encodeURIComponent(d.ref);
          } else {
            btn.disabled = false;
            msg((d && d.error) ? d.error : "We could not send your case. Please try again in a few minutes.", "bad");
          }
        })
        .catch(function () { btn.disabled = false; msg("Network problem. Your details are still here; please try again.", "bad"); });
    });
    show(0);
  }

  /* thank-you reference */
  var refEl = $("#case-ref");
  if (refEl) {
    var ref = (params.get("ref") || "").toUpperCase();
    refEl.textContent = REF_RE.test(ref) ? ref : "in your confirmation";
  }
})();
