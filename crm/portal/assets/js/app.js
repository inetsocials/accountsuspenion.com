/* AS Case Vault client script. CSP-safe: no inline handlers, no eval. */
(function () {
  "use strict";

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var meta = function (name) { var m = $('meta[name="' + name + '"]'); return m ? m.getAttribute("content") : ""; };
  var BASE = meta("ascv-base");
  var CSRF = meta("csrf-token");
  var esc = function (s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; });
  };

  function api(path, opts) {
    opts = opts || {};
    var headers = opts.headers || {};
    headers["X-CSRF-Token"] = CSRF;
    headers["X-Requested-With"] = "fetch";
    headers["Accept"] = "application/json";
    return fetch(BASE + path, { method: opts.method || "POST", body: opts.body, headers: headers, credentials: "same-origin" })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, error: "Unexpected response (" + r.status + ")." }; })
          .then(function (d) { if (!r.ok || !d.ok) { throw new Error(d.error || ("Request failed (" + r.status + ").")); } return d; });
      });
  }

  function toast(kind, text) {
    var stack = $(".flash-stack");
    if (!stack) { stack = document.createElement("div"); stack.className = "flash-stack"; document.body.appendChild(stack); }
    var el = document.createElement("div");
    el.className = "flash " + kind;
    el.setAttribute("role", "status");
    el.innerHTML = '<svg class="ic"><use href="#i-' + (kind === "ok" ? "check" : "alert") + '"></use></svg><div>' + esc(text) + '</div><button type="button" aria-label="Dismiss"><svg class="ic"><use href="#i-x"></use></svg></button>';
    stack.appendChild(el);
    wireFlash(el);
  }

  function wireFlash(el) {
    var close = function () { el.classList.add("out"); setTimeout(function () { el.remove(); }, 320); };
    var b = $("button", el); if (b) { b.addEventListener("click", close); }
    if (!el.classList.contains("error")) { setTimeout(close, 6500); }
  }
  $$(".flash").forEach(wireFlash);

  /* ---------------------------------------------------------------- theme toggle */
  $$("[data-theme-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var cur = document.documentElement.getAttribute("data-theme");
      var dark = cur === "dark" || (cur === "auto" && window.matchMedia("(prefers-color-scheme: dark)").matches);
      var next = dark ? "light" : "dark";
      document.documentElement.setAttribute("data-theme", next);
      try { localStorage.setItem("ascv-theme", next); } catch (e) { /* ignore */ }
    });
  });

  /* close <details> menus on outside click / Escape */
  document.addEventListener("click", function (e) {
    $$("details.menu[open]").forEach(function (d) { if (!d.contains(e.target)) { d.removeAttribute("open"); } });
  });

  /* ---------------------------------------------------------------- confirm dialogs */
  var confirmDlg = $("#confirm-dialog");
  $$("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      if (form.dataset.confirmed === "1" || !confirmDlg) { return; }
      e.preventDefault();
      $("#confirm-text", confirmDlg).textContent = form.getAttribute("data-confirm");
      var ok = $("#confirm-ok", confirmDlg);
      ok.className = "btn " + (form.hasAttribute("data-danger") ? "btn-danger" : "btn-primary");
      ok.onclick = function () { confirmDlg.close(); form.dataset.confirmed = "1"; if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); } };
      confirmDlg.showModal();
    });
  });
  if (confirmDlg) { $("#confirm-cancel", confirmDlg).addEventListener("click", function () { confirmDlg.close(); }); }

  /* double-submit guard */
  $$("form").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (e.defaultPrevented) { return; }
      if ((f.getAttribute("method") || "").toLowerCase() === "post" && !f.hasAttribute("data-compose")) {
        $$('button[type="submit"], button:not([type])', f).forEach(function (b) { setTimeout(function () { b.classList.add("is-busy"); }, 0); });
      }
    });
  });

  /* ---------------------------------------------------------------- auto submit */
  $$("[data-autosubmit]").forEach(function (el) {
    el.addEventListener("change", function () { if (el.form) { el.form.submit(); } });
  });

  /* ---------------------------------------------------------------- copy + print */
  $$("[data-copy]").forEach(function (b) {
    b.addEventListener("click", function () {
      var t = $(b.getAttribute("data-copy"));
      if (!t || !navigator.clipboard) { return; }
      navigator.clipboard.writeText(t.innerText.trim()).then(function () { toast("ok", "Copied to clipboard."); });
    });
  });
  $$("[data-print]").forEach(function (b) { b.addEventListener("click", function () { window.print(); }); });

  /* ---------------------------------------------------------------- password strength */
  $$("input[data-strength]").forEach(function (inp) {
    var m = document.createElement("div"); m.className = "meter"; m.innerHTML = "<i class=\"s0\"></i>";
    var hint = document.createElement("span"); hint.className = "hint";
    inp.insertAdjacentElement("afterend", hint); inp.insertAdjacentElement("afterend", m);
    inp.addEventListener("input", function () {
      var v = inp.value, score = 0;
      if (v.length >= 12) { score++; } if (v.length >= 16) { score++; }
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) { score++; } if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) { score++; }
      if (/(password|qwerty|123456|letmein)/i.test(v)) { score = 0; }
      if (v.length < 12) { score = Math.min(score, 1); }
      $("i", m).className = "s" + score;
      hint.textContent = v.length === 0 ? "" : (v.length < 12 ? "At least 12 characters." : ["Weak", "Weak", "Fair", "Good", "Strong"][score]);
    });
  });

  /* ---------------------------------------------------------------- chunked encrypted uploads */
  function uploadFile(file, fields, onProgress) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) { if (fields[k] !== undefined && fields[k] !== null && fields[k] !== "") { fd.append(k, fields[k]); } });
    fd.append("name", file.name); fd.append("size", String(file.size));
    return api("/uploads/start", { body: fd }).then(function (s) {
      var i = 0;
      var next = function () {
        if (i >= s.chunks) { return api("/uploads/" + s.id + "/finish", { body: new FormData() }); }
        var blob = file.slice(i * s.chunk_bytes, Math.min(file.size, (i + 1) * s.chunk_bytes));
        return api("/uploads/" + s.id + "/chunk?i=" + i, { body: blob, headers: { "Content-Type": "application/octet-stream" } })
          .then(function () { i++; onProgress(i / s.chunks); return next(); });
      };
      return next();
    });
  }

  function uploadRow(list, file) {
    var li = document.createElement("li"); li.className = "up-item";
    li.innerHTML = '<span class="truncate"></span><span class="st">Encrypting</span><div class="up-bar"><progress max="100" value="0"></progress></div>';
    $(".truncate", li).textContent = file.name;
    list.appendChild(li);
    return {
      progress: function (p) { $("progress", li).value = Math.round(p * 100); $(".st", li).textContent = Math.round(p * 100) + "%"; },
      done: function () { li.classList.add("done"); $("progress", li).value = 100; $(".st", li).textContent = "Stored encrypted"; },
      fail: function (msg) { li.classList.add("error"); $(".st", li).textContent = msg; }
    };
  }

  $$("[data-uploader]").forEach(function (box) {
    var input = $('input[type="file"]', box);
    var list = $(".up-list", box);
    var zone = $(".dropzone", box);
    var fieldsOf = function () {
      var f = { client_id: box.dataset.client || "", case_id: box.dataset.case || "", replaces_id: box.dataset.replaces || "" };
      var vis = $('[name="visibility"]', box); if (vis) { f.visibility = vis.value; }
      var fol = $('[name="folder_id"]', box); if (fol) { f.folder_id = fol.value; }
      var cs = $('[name="case_pick"]', box); if (cs) { f.case_id = cs.value; }
      return f;
    };
    var run = function (files) {
      files = Array.prototype.slice.call(files || []);
      if (!files.length) { return; }
      var fields = fieldsOf();
      if (box.hasAttribute("data-need-case") && !fields.case_id) { toast("error", "Choose the case these documents belong to."); return; }
      var chain = Promise.resolve(), ok = 0;
      files.forEach(function (file) {
        var row = uploadRow(list, file);
        chain = chain.then(function () {
          return uploadFile(file, fields, row.progress).then(function () { row.done(); ok++; }).catch(function (err) { row.fail(err.message); });
        });
      });
      chain.then(function () {
        input.value = "";
        if (ok > 0) { toast("ok", ok + (ok === 1 ? " file" : " files") + " stored in the encrypted vault."); setTimeout(function () { window.location.reload(); }, 1100); }
      });
    };
    if (input) { input.addEventListener("change", function () { run(input.files); }); }
    if (zone) {
      ["dragenter", "dragover"].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add("drag"); }); });
      ["dragleave", "drop"].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove("drag"); }); });
      zone.addEventListener("drop", function (e) { run(e.dataTransfer.files); });
    }
  });

  /* ---------------------------------------------------------------- message composer with attachments */
  $$("form[data-compose]").forEach(function (form) {
    var input = $('input[type="file"]', form);
    var chips = $(".chips", form);
    if (input && chips) {
      input.addEventListener("change", function () {
        chips.innerHTML = "";
        Array.prototype.slice.call(input.files).forEach(function (f) {
          var c = document.createElement("span"); c.className = "file-chip"; c.textContent = f.name; chips.appendChild(c);
        });
      });
    }
    form.addEventListener("submit", function (e) {
      if (!input || !input.files.length) { return; }
      e.preventDefault();
      var btn = $('button[type="submit"]', form); if (btn) { btn.classList.add("is-busy"); }
      var fd = new FormData(form); fd.delete("files"); fd.append("has_files", "1");
      var files = Array.prototype.slice.call(input.files);
      var list = $(".up-list", form);
      api(form.getAttribute("data-compose"), { body: fd }).then(function (res) {
        var chain = Promise.resolve();
        files.forEach(function (file) {
          var row = uploadRow(list, file);
          chain = chain.then(function () {
            var vis = $('[name="internal"]', form);
            return uploadFile(file, { case_id: res.case_id, message_id: res.message_id, visibility: vis && vis.checked ? "internal" : "shared" }, row.progress)
              .then(row.done).catch(function (err) { row.fail(err.message); });
          });
        });
        return chain;
      }).then(function () {
        window.location.href = window.location.pathname + "?tab=messages";
      }).catch(function (err) { toast("error", err.message); if (btn) { btn.classList.remove("is-busy"); } });
    });
  });

  /* scroll message thread to newest */
  $$(".thread").forEach(function (t) { t.scrollTop = t.scrollHeight; });

  /* ---------------------------------------------------------------- fill edit forms from row data */
  $$("[data-fill]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var form = $(btn.getAttribute("data-fill"));
      var data; try { data = JSON.parse(btn.getAttribute("data-values") || "{}"); } catch (e) { return; }
      if (!form) { return; }
      Object.keys(data).forEach(function (k) {
        var el = form.elements[k]; if (!el) { return; }
        if (el.type === "checkbox") { el.checked = !!data[k] && data[k] !== "0"; } else { el.value = data[k] === null ? "" : data[k]; }
      });
      var d = form.closest("details"); if (d) { d.open = true; }
      var h = $("[data-form-title]", form.closest(".card") || form); if (h && btn.dataset.title) { h.textContent = btn.dataset.title; }
      form.scrollIntoView({ behavior: "smooth", block: "start" });
      var first = $("input:not([type=hidden]), select, textarea", form); if (first) { first.focus({ preventScroll: true }); }
    });
  });

  /* ---------------------------------------------------------------- invoice line items */
  $$("[data-items]").forEach(function (wrap) {
    var body = $("tbody", wrap), tpl = $("template", wrap), cur = wrap.getAttribute("data-currency") || "GBP";
    var sym = { GBP: "£", USD: "$", EUR: "€" }[cur] || "";
    var fmt = function (n) { var neg = n < 0; n = Math.abs(n); return (neg ? "-" : "") + sym + (Math.round(n * 100) / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","); };
    var recalc = function () {
      var sub = 0, vat = 0;
      $$("tr", body).forEach(function (tr) {
        var q = parseFloat($('[name="item_qty[]"]', tr).value) || 0, u = parseFloat($('[name="item_unit[]"]', tr).value) || 0, v = parseFloat($('[name="item_vat[]"]', tr).value) || 0;
        var net = Math.round(q * u * 100) / 100; sub += net; vat += net * v / 100;
        var cell = $(".line-total", tr); if (cell) { cell.textContent = fmt(net); }
      });
      var discEl = $('[name="discount"]', wrap.form || document);
      var disc = Math.min(sub, parseFloat(discEl ? discEl.value : 0) || 0);
      var ratio = sub > 0 ? (sub - disc) / sub : 0;
      vat = vat * ratio;
      var set = function (sel, v) { var e = $(sel, wrap); if (e) { e.textContent = fmt(v); } };
      set("[data-t=sub]", sub); set("[data-t=disc]", -disc); set("[data-t=vat]", vat); set("[data-t=total]", sub - disc + vat);
    };
    var addRow = function (vals) {
      var node = document.importNode(tpl.content, true);
      var tr = $("tr", node);
      if (vals) {
        $('[name="item_desc[]"]', tr).value = vals.d; $('[name="item_qty[]"]', tr).value = vals.q; $('[name="item_unit[]"]', tr).value = vals.u; $('[name="item_vat[]"]', tr).value = vals.v;
      }
      body.appendChild(tr);
      recalc();
    };
    wrap.addEventListener("input", recalc);
    document.addEventListener("input", function (e) { if (e.target && e.target.name === "discount") { recalc(); } });
    wrap.addEventListener("click", function (e) {
      var rm = e.target.closest("[data-remove-row]");
      if (rm) { var tr = rm.closest("tr"); if ($$("tr", body).length > 1) { tr.remove(); } else { $$("input", tr).forEach(function (i) { i.value = i.name === "item_qty[]" ? "1" : (i.name === "item_vat[]" ? "0" : ""); }); } recalc(); }
    });
    var add = $("[data-add-row]", wrap); if (add) { add.addEventListener("click", function () { addRow(); }); }
    var cat = $("[data-catalog]", wrap);
    if (cat) {
      cat.addEventListener("change", function () {
        var o = cat.options[cat.selectedIndex]; if (!o || !o.value) { return; }
        var rows = $$("tr", body), last = rows[rows.length - 1];
        var vals = { d: o.getAttribute("data-name"), q: "1", u: o.getAttribute("data-price"), v: o.getAttribute("data-vat") };
        if (last && !$('[name="item_desc[]"]', last).value) { last.remove(); }
        addRow(vals); cat.value = "";
      });
    }
    if (!$$("tr", body).length) { addRow(); } else { recalc(); }
  });

  /* ---------------------------------------------------------------- case select filters by client (invoice/new) */
  var clientSel = $("select[data-client-select]"), caseSel = $("select[data-case-select]");
  if (clientSel && caseSel) {
    var filter = function () {
      $$("option", caseSel).forEach(function (o) { if (o.value) { o.hidden = o.getAttribute("data-client") !== clientSel.value; } });
      if (caseSel.selectedOptions[0] && caseSel.selectedOptions[0].hidden) { caseSel.value = ""; }
    };
    clientSel.addEventListener("change", filter); filter();
  }

  /* ---------------------------------------------------------------- command palette */
  var pal = $("#palette");
  if (pal) {
    var q = $("input", pal), res = $(".palette-results", pal), sel = 0, items = [], timer = null, seq = 0;
    var render = function () {
      res.innerHTML = items.length ? items.map(function (it, i) {
        return '<li class="' + (i === sel ? "sel" : "") + '"><a href="' + esc(it.url) + '"><span class="type">' + esc(it.type) + '</span><span class="truncate">' + esc(it.label) + "</span>" + (it.meta ? '<span class="meta">' + esc(it.meta) + "</span>" : "") + "</a></li>";
      }).join("") : '<li class="empty">No matches</li>';
    };
    var search = function () {
      var my = ++seq;
      fetch(BASE + "/search?q=" + encodeURIComponent(q.value), { headers: { "Accept": "application/json", "X-Requested-With": "fetch" }, credentials: "same-origin" })
        .then(function (r) { return r.json(); }).then(function (d) { if (my !== seq) { return; } items = d.results || []; sel = 0; render(); }).catch(function () { /* ignore */ });
    };
    var open = function () { pal.showModal(); q.value = ""; search(); setTimeout(function () { q.focus(); }, 10); };
    document.addEventListener("keydown", function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === "k" || e.key === "K")) { e.preventDefault(); if (pal.open) { pal.close(); } else { open(); } }
      if (e.key === "/" && !pal.open && !/input|textarea|select/i.test((document.activeElement || {}).tagName || "")) { e.preventDefault(); open(); }
    });
    $$("[data-palette-open]").forEach(function (b) { b.addEventListener("click", open); });
    q.addEventListener("input", function () { clearTimeout(timer); timer = setTimeout(search, 140); });
    q.addEventListener("keydown", function (e) {
      if (e.key === "ArrowDown") { e.preventDefault(); sel = Math.min(items.length - 1, sel + 1); render(); }
      else if (e.key === "ArrowUp") { e.preventDefault(); sel = Math.max(0, sel - 1); render(); }
      else if (e.key === "Enter" && items[sel]) { e.preventDefault(); window.location.href = items[sel].url; }
    });
    pal.addEventListener("click", function (e) { if (e.target === pal) { pal.close(); } });
  }

  /* ---------------------------------------------------------------- idle sign-out warning */
  var idle = parseInt(meta("ascv-idle"), 10);
  var idleDlg = $("#idle-dialog");
  if (idle > 0 && idleDlg) {
    var warnAt = Math.max(60, idle * 60 - 120) * 1000, t1 = null, t2 = null;
    var arm = function () {
      clearTimeout(t1); clearTimeout(t2);
      t1 = setTimeout(function () { if (!idleDlg.open) { idleDlg.showModal(); } }, warnAt);
      t2 = setTimeout(function () { window.location.href = BASE + "/login"; }, idle * 60 * 1000 + 2000);
    };
    $("#idle-stay", idleDlg).addEventListener("click", function () {
      fetch(BASE + "/search?q=", { headers: { "Accept": "application/json", "X-Requested-With": "fetch" }, credentials: "same-origin" })
        .then(function () { idleDlg.close(); arm(); });
    });
    arm();
  }
})();
