"""AS Case Vault end-to-end tests (Playwright, Python).

Environment (see qa/run_crm_e2e.sh):
  ENV/public_html  built website + portal/ + api/contact.php + cms.php
  ENV/ascrm        the CRM application (outside the web root)
  php -S 127.0.0.1:8090 -t ENV/public_html qa/router.php

Usage: python3 qa/crm_e2e.py ENV_DIR [BASE_URL] [SHOT_DIR]
"""
from __future__ import annotations

import base64
import glob
import hashlib
import hmac
import io
import json
import os
import re
import sqlite3
import struct
import subprocess
import sys
import time
import zipfile
from pathlib import Path

from playwright.sync_api import Page, sync_playwright

ENV = Path(sys.argv[1]).resolve()
BASE = (sys.argv[2] if len(sys.argv) > 2 else "http://127.0.0.1:8090").rstrip("/")
SHOTS = Path(sys.argv[3] if len(sys.argv) > 3 else ENV / "shots")
SHOTS.mkdir(parents=True, exist_ok=True)
APP = ENV / "ascrm"
DB = APP / "storage" / "db" / "ascrm.sqlite"
PW = "Vault-Test-Passphrase-9431"

passes = 0
fails: list[str] = []
console_errors: list[str] = []
used_steps: dict[str, int] = {}


def ok(cond: bool, label: str) -> None:
    global passes
    if cond:
        passes += 1
        print("  ok  ", label)
    else:
        fails.append(label)
        print("  FAIL", label)


# ------------------------------------------------------------------ TOTP
def totp(secret: str, step: int | None = None) -> str:
    key = base64.b32decode(secret.replace(" ", "").upper() + "=" * ((8 - len(secret.replace(" ", "")) % 8) % 8))
    step = int(time.time() // 30) if step is None else step
    h = hmac.new(key, struct.pack(">Q", step), hashlib.sha1).digest()
    o = h[-1] & 0x0F
    return str((struct.unpack(">I", h[o:o + 4])[0] & 0x7FFFFFFF) % 1000000).zfill(6)


def fresh_code(who: str, secret: str) -> str:
    """Never reuse a TOTP step per user (the server rejects replays)."""
    while True:
        step = int(time.time() // 30)
        if used_steps.get(who, -1) < step:
            used_steps[who] = step
            return totp(secret, step)
        time.sleep(1)


# AS_DB=mysql runs the whole suite on MySQL/MariaDB (AS_DB_NAME, AS_DB_USER, AS_DB_PASS; default astest/as/aspw).
MYSQL = os.environ.get("AS_DB") == "mysql"
MY = {"name": os.environ.get("AS_DB_NAME", "astest"), "user": os.environ.get("AS_DB_USER", "as"), "pass": os.environ.get("AS_DB_PASS", "aspw")}


def _pdo(dbname: str | None = None) -> str:
    if MYSQL:
        return "new PDO(%s, %s, %s)" % (json.dumps("mysql:host=localhost;dbname=%s;charset=utf8mb4" % (dbname or MY["name"])), json.dumps(MY["user"]), json.dumps(MY["pass"]))
    return "new PDO(%s)" % json.dumps("sqlite:%s" % (dbname or DB))


def php_sql(sql: str) -> None:
    """Writes go through PHP/PDO so they share SQLite's WAL correctly with the server."""
    subprocess.run(["php", "-r", "$p = %s; $p->exec(%s);" % (_pdo(), json.dumps(sql))], check=True)


class _Row(dict):
    def __getitem__(self, k):
        return list(self.values())[k] if isinstance(k, int) else dict.__getitem__(self, k)


class _Result:
    def __init__(self, rows: list) -> None:
        self.rows = [_Row(r) for r in rows]

    def fetchone(self):
        return self.rows[0] if self.rows else None

    def fetchall(self) -> list:
        return self.rows


class _PdoConn:
    """sqlite3-like read helper backed by PHP PDO (used for MySQL runs)."""

    def __init__(self, dbname: str | None = None) -> None:
        self.dbname = dbname

    def execute(self, sql: str, params: tuple = ()) -> _Result:
        code = ("$p = %s; $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $s = $p->prepare(%s); $s->execute(json_decode(%s, true)); "
                "echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));") % (_pdo(self.dbname), json.dumps(sql), json.dumps(json.dumps(list(params))))
        out = subprocess.run(["php", "-r", code], capture_output=True, text=True, check=True).stdout
        rows = json.loads(out)
        for r in rows:  # PDO MySQL returns numbers as strings
            for k, v in r.items():
                if isinstance(v, str) and v.lstrip("-").isdigit() and len(v) < 19 and not (len(v) > 1 and v.startswith("0")):
                    r[k] = int(v)
        return _Result(rows)

    def close(self) -> None:
        pass


def at_rest() -> bytes:
    if MYSQL:
        return subprocess.run(["mysqldump", "--skip-comments", MY["name"]], capture_output=True, check=True).stdout
    data = b""
    for suffix in ("", "-wal", "-shm"):
        f = Path(str(DB) + suffix)
        if f.exists():
            data += f.read_bytes()
    return data


class db:
    """Short-lived read-only connection, always closed (a lingering reader sees stale pages)."""

    def __enter__(self) -> sqlite3.Connection:
        if MYSQL:
            self.c = _PdoConn()
            return self.c
        self.c = sqlite3.connect(f"file:{DB}?mode=ro", uri=True)
        self.c.row_factory = sqlite3.Row
        return self.c

    def __exit__(self, *exc) -> None:
        self.c.close()


def mails() -> list[str]:
    return sorted(glob.glob(str(APP / "storage" / "mail" / "*.eml")))


def last_mail_to(addr: str) -> str:
    for f in reversed(mails()):
        t = Path(f).read_text(errors="replace")
        if addr.lower() in t.lower():
            return t
    return ""


def link_in(mail: str, pattern: str) -> str:
    m = re.search(r"(https?://[^\s\"<>]*" + pattern + r"[^\s\"<>]*)", mail.replace("=\n", ""))
    return m.group(1).replace("=3D", "=") if m else ""


def nav(page: Page, action) -> None:
    with page.expect_navigation():
        action()


def watch(page: Page) -> Page:
    def on_console(m):
        if m.type != "error" or "status of 4" in m.text:
            return
        # The static site loads Google Fonts; the sandbox proxy breaks that TLS. Portal pages load nothing external.
        external_font = "/portal/" not in page.url and any(s in m.text for s in ("fonts.g", "ERR_TUNNEL", "ERR_CERT"))
        if not external_font:
            console_errors.append(f"{page.url}: {m.text}")
    page.on("console", on_console)
    page.on("pageerror", lambda ex: console_errors.append(f"{page.url}: {ex}"))
    return page


def sign_in(page: Page, email: str, password: str, who: str, secret: str) -> None:
    page.goto(BASE + "/portal/login")
    page.fill("input[name=email]", email)
    page.fill("input[name=password]", password)
    nav(page, lambda: page.click("form button[type=submit]"))
    page.fill("input[name=code]", fresh_code(who, secret))
    nav(page, lambda: page.click("form button[type=submit]"))


def enrol(page: Page, who: str) -> str:
    """On /login/setup: read the secret, confirm a code, land on recovery codes."""
    secret = page.inner_text("#totp-secret").replace(" ", "")
    page.fill("input[name=code]", fresh_code(who, secret))
    nav(page, lambda: page.click("form button[type=submit]"))
    return secret


def set_config(extra: dict) -> None:
    code = ("$f = '%s/config/config.php'; $c = require $f; $c = array_replace_recursive($c, json_decode('%s', true)); "
            "file_put_contents($f, \"<?php\\nreturn \" . var_export($c, true) . \";\\n\");") % (APP, json.dumps(extra))
    subprocess.run(["php", "-r", code], check=True)


def main() -> int:
    with sync_playwright() as pw:
        exe = os.environ.get("PW_CHROMIUM")
        browser = pw.chromium.launch(executable_path=exe) if exe else pw.chromium.launch()
        ctx = browser.new_context(viewport={"width": 1440, "height": 900}, accept_downloads=True)
        page = watch(ctx.new_page())

        # ---------------------------------------------------------- 1. install
        print("1. Install")
        page.goto(BASE + "/portal/")
        ok("/portal/install" in page.url, "unauthenticated portal redirects to install")
        setup_key = (APP / "config" / "setup.key").read_text().strip()
        page.fill("input[name=setup_key]", setup_key)
        page.fill("input[name=app_url]", BASE + "/portal")
        if MYSQL:
            page.select_option("select[name=driver]", "mysql")
            page.fill("input[name=db_name]", MY["name"])
            page.fill("input[name=db_user]", MY["user"])
            page.fill("input[name=db_pass]", MY["pass"])
        else:
            page.select_option("select[name=driver]", "sqlite")
        page.fill("input[name=name]", "Morgan Master")
        page.fill("input[name=email]", "master@accountsuspension.test")
        page.fill("input[name=password]", PW)
        nav(page, lambda: page.click("form button[type=submit]"))
        ok("installed" in page.content().lower() or "cron" in page.content().lower(), "install completes")
        ok(not (APP / "config" / "setup.key").exists(), "setup.key deleted after install")
        ok((APP / "keys" / "master.key").exists(), "master key created")
        set_config({"mail_driver_override": "log", "intake": {"allowed_origins": [BASE]}})
        r = page.goto(BASE + "/portal/install")
        ok(r.status == 404, "installer returns 404 after install")

        # ---------------------------------------------------------- 2. master 2FA
        print("2. Master sign-in and compulsory 2FA")
        page.goto(BASE + "/portal/login")
        page.fill("input[name=email]", "master@accountsuspension.test")
        page.fill("input[name=password]", PW)
        nav(page, lambda: page.click("form button[type=submit]"))
        ok("/login/setup" in page.url, "first sign-in forces authenticator setup")
        master_secret = enrol(page, "master")
        codes = re.findall(r"\b[0-9A-F]{5}-[0-9A-F]{5}\b", page.content())
        ok(len(set(codes)) == 10, "10 recovery codes shown")
        page.goto(BASE + "/portal/")
        ok("Dashboard" in page.content(), "staff dashboard renders")
        ok("AccountSuspension" in page.content() or "AS Case Vault" in page.content(), "AccountSuspension branding shown")
        page.screenshot(path=str(SHOTS / "crm-dashboard-empty.png"), full_page=True)

        # ---------------------------------------------------------- 3. website intake
        print("3. Website intake")
        site = watch(ctx.new_page())
        site.goto(BASE + "/contact-us/?source=platform&platform=amazon")
        ok(site.input_value("#platform") == "amazon", "contact form pre-fills platform")
        site.click("[data-step='1'] [data-next]")
        site.click("input[name=issue][value=funds] + span")
        site.click("[data-step='2'] [data-next]")
        site.click("input[name=history][value=one] + span")
        site.click("[data-step='3'] [data-next]")
        site.click("input[name=urgency][value=deadline] + span")
        site.click("[data-step='4'] [data-next]")
        site.fill("#name", "Riley Seller")
        site.fill("#email", "riley.seller@example.org")
        site.fill("#details", "MARKER-INTAKE-7781 Amazon deactivated my account for related accounts and holds my balance.")
        site.check("input[name=consent]")
        site.wait_for_timeout(3500)
        site.click("#intake button[type=submit]")
        site.wait_for_url("**/thank-you/**", timeout=15000)
        ref1 = site.inner_text("#case-ref")
        ok(re.fullmatch(r"AS-[0-9A-F]{8}", ref1) is not None, f"website intake returns AS- reference ({ref1})")

        with db() as c:
            row = c.execute("SELECT * FROM cases WHERE ref = ?", (ref1,)).fetchone()
        ok(row is not None and row["priority"] == "emergency" and row["source"] == "emergency", "deadline intake is priority lane")
        ok(row is not None and row["platform"] == "amazon" and row["issue"] == "funds" and row["appeal_history"] == "one", "platform, issue and history stored")
        raw = at_rest()
        ok(b"MARKER-INTAKE-7781" not in raw and b"riley.seller@example.org" not in raw, "intake sealed at rest (no plaintext in DB)")
        alert = "".join(Path(f).read_text(errors="replace") for f in mails())
        ok("MARKER-INTAKE-7781" not in alert and ref1 in alert, "lead alert email has reference but no content")

        foreign = site.request.post(BASE + "/api/contact.php", headers={"Origin": "https://evil.example", "Accept": "application/json"},
                                    form={"platform": "ebay", "issue": "suspended", "history": "none", "urgency": "standard", "name": "X", "email": "x@example.org", "details": "foreign origin test", "consent": "1"})
        ok(foreign.status == 403, "foreign Origin rejected with 403")

        globals().update(ctx=ctx, page=page, site=site, master_secret=master_secret, ref1=ref1, browser=browser, codes=codes)
        extra_tests()

        page.goto(BASE + "/portal/")
        browser.close()

    print()
    for e in console_errors:
        print("CONSOLE", e)
    ok(not console_errors, "no CSP or JavaScript errors")
    print(f"\n{passes} passed, {len(fails)} failed")
    for f in fails:
        print("FAILED:", f)
    return 1 if fails else 0


def until(fn, seconds: float) -> None:
    end = time.time() + seconds
    while time.time() < end:
        try:
            if fn():
                return
        except Exception:
            pass
        time.sleep(0.4)


def confirm(page: Page) -> None:
    page.wait_for_selector("#confirm-dialog[open]")
    nav(page, lambda: page.click("#confirm-ok"))


def csrf(page: Page) -> str:
    return page.get_attribute("meta[name=csrf-token]", "content") or ""


def make_png(path: Path, size_mb: float) -> bytes:
    import zlib
    w = 1024
    h = int(size_mb * 1024 * 1024 / (w * 3)) + 1
    raw = b"".join(b"\x00" + os.urandom(w * 3) for _ in range(h))
    def chunk(tag, data):
        return struct.pack(">I", len(data)) + tag + data + struct.pack(">I", zlib.crc32(tag + data) & 0xFFFFFFFF)
    png = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw, 0)) + chunk(b"IEND", b"")
    path.write_bytes(png)
    return png


def extra_tests() -> None:
    g = globals()
    ctx, page, site, master_secret, ref1, browser = g["ctx"], g["page"], g["site"], g["master_secret"], g["ref1"], g["browser"]
    tmp = ENV / "files"
    tmp.mkdir(exist_ok=True)

    # second, standard-priority lead for sorting
    site.goto(BASE + "/contact-us/")
    r = site.request.post(BASE + "/api/contact.php", headers={"Origin": BASE, "Accept": "application/json"}, form={
        "platform": "paypal", "issue": "limited", "history": "none", "urgency": "standard", "name": "Sam Standard",
        "email": "sam.standard@example.org", "details": "PayPal limited my account last week, standard timing.", "consent": "1",
        "form_ts": str(int(time.time()) - 30), "source": "platform"})
    ref2 = r.json().get("ref", "")
    ok(r.ok and ref2.startswith("AS-"), "second intake via API accepted")

    # ---------------------------------------------------------- 4. status check
    print("4. Public status check")
    before = len(mails())
    site.goto(BASE + "/portal/status")
    site.fill("input[name=ref]", ref1)
    site.fill("input[name=email]", "riley.seller@example.org")
    nav(site, lambda: site.click("form button[type=submit]"))
    ok(len(mails()) == before + 1, "status request with correct email sends a link")
    link = link_in(last_mail_to("riley.seller@example.org"), "/portal/status/")
    site.goto(link)
    ok("Received" in site.content(), "status link shows Received")
    before = len(mails())
    site.goto(BASE + "/portal/status")
    site.fill("input[name=ref]", ref1)
    site.fill("input[name=email]", "wrong@example.org")
    nav(site, lambda: site.click("form button[type=submit]"))
    ok(len(mails()) == before, "wrong email sends nothing (same response page)")

    # ---------------------------------------------------------- 5. leads
    print("5. Leads: screening and accept")
    page.goto(BASE + "/portal/leads")
    body = page.inner_text("main")
    ok(body.find(ref1) != -1 and body.find(ref1) < body.find(ref2), "priority lead sorted first")
    with db() as c:
        lead_id = c.execute("SELECT id FROM cases WHERE ref = ?", (ref1,)).fetchone()["id"]
    page.goto(f"{BASE}/portal/leads/{lead_id}")
    ok("MARKER-INTAKE-7781" in page.content() and "Amazon" in page.content(), "intake decrypts for staff with platform")
    ok(page.is_disabled("form[action$='/accept'] button[type=submit]"), "accept disabled until screening complete")
    for cb in page.query_selector_all("input[name='checks[]']"):
        cb.check()
    nav(page, lambda: page.click("form[action$='/screening'] button[type=submit]"))
    ok(page.is_enabled("form[action$='/accept'] button[type=submit]"), "accept enabled after screening")
    page.click("form[action$='/accept'] button[type=submit]")
    confirm(page)
    ok(f"/portal/cases/{lead_id}" in page.url, "accept opens the case")
    with db() as c:
        cl = c.execute("SELECT cl.* FROM clients cl JOIN cases ca ON ca.client_id = cl.id WHERE ca.id = ?", (lead_id,)).fetchone()
    ok(cl is not None and re.fullmatch(r"[a-f0-9]{64}", cl["vault_dir"]) is not None, "client vault folder is 64 hex")
    ok(cl is not None and str(cl["dek_wrapped"]).startswith("v1."), "client data key stored wrapped")
    invite_mail = last_mail_to("riley.seller@example.org")
    invite = link_in(invite_mail, "/portal/invite/")
    ok(bool(invite), "client invitation emailed")
    client_id = cl["id"]

    # ---------------------------------------------------------- 6. trackers and work
    print("6. Appeal tracker, held funds, messages, tasks")
    page.goto(f"{BASE}/portal/cases/{lead_id}?tab=appeals")
    page.fill("#target-form input[name=url]", "SELLER-ID-MARKER-5521")
    page.select_option("#target-form select[name=route]", "plan_of_action")
    page.select_option("#target-form select[name=status]", "submitted")
    nav(page, lambda: page.click("#target-form button[type=submit]"))
    ok("SELLER-ID-MARKER-5521" in page.content() and "Plan of Action" in page.content(), "appeal submission saved and listed")
    page.goto(f"{BASE}/portal/cases/{lead_id}?tab=funds")
    page.fill("#funds-form input[name=amount]", "12500.00")
    page.fill("#funds-form input[name=released]", "2500")
    page.fill("#funds-form input[name=reference]", "FUNDREF-MARKER-3310")
    page.fill("#funds-form input[name=expected_on]", "2026-12-15")
    nav(page, lambda: page.click("#funds-form button[type=submit]"))
    content = page.content()
    ok("$12,500.00" in content and "$2,500.00" in content and "$10,000.00" in content, "held funds totals computed")
    ok("Partly released" in content, "funds status auto-set to partly released")
    ok("12/15/2026" in content, "US date format MM/DD/YYYY")
    page.goto(f"{BASE}/portal/cases/{lead_id}?tab=messages")
    page.fill("#msg-body", "MARKER-MSG-4402 We have started your case review.")
    nav(page, lambda: page.click("form.composer button[type=submit]"))
    page.fill("#msg-body", "MARKER-NOTE-9913 internal only")
    page.check("form.composer input[name=internal]")
    nav(page, lambda: page.click("form.composer button[type=submit]"))
    ok("MARKER-MSG-4402" in page.content() and "MARKER-NOTE-9913" in page.content(), "message and internal note posted")
    page.goto(f"{BASE}/portal/cases/{lead_id}?tab=work")
    page.fill("form[action$='/tasks'] input[name=title]", "Draft Plan of Action")
    nav(page, lambda: page.click("form[action$='/tasks'] button[type=submit]"))
    page.fill("form[action$='/deadlines'] input[name=title]", "Amazon reply due")
    page.fill("form[action$='/deadlines'] input[name=due_at]", time.strftime("%Y-%m-%dT%H:%M", time.localtime(time.time() + 86400)))
    page.check("form[action$='/deadlines'] input[name=client_visible]")
    nav(page, lambda: page.click("form[action$='/deadlines'] button[type=submit]"))
    ok("Draft Plan of Action" in page.content() and "Amazon reply due" in page.content(), "task and deadline added")
    raw = at_rest()
    ok(all(m not in raw for m in [b"SELLER-ID-MARKER-5521", b"FUNDREF-MARKER-3310", b"MARKER-MSG-4402", b"MARKER-NOTE-9913"]), "tracker, funds and messages encrypted at rest")

    # ---------------------------------------------------------- 7. client onboarding (mobile)
    print("7. Client onboarding at 390 px")
    cctx = browser.new_context(viewport={"width": 390, "height": 844}, accept_downloads=True)
    cp = watch(cctx.new_page())
    cp.goto(invite)
    cp.fill("input[name=name]", "Riley Seller")
    cp.fill("input[name=password]", "Client-Strong-Passphrase-77")
    cp.fill("input[name=password2]", "Client-Strong-Passphrase-77")
    cp.check("input[name=accept]")
    nav(cp, lambda: cp.click("form button[type=submit]"))
    ok("/login/setup" in cp.url, "invite leads to authenticator setup")
    client_secret = cp.inner_text("#totp-secret").replace(" ", "")
    cp.fill("input[name=code]", "000000")
    nav(cp, lambda: cp.click("form button[type=submit]"))
    ok("did not match" in cp.content(), "wrong TOTP code rejected")
    cp.fill("input[name=code]", fresh_code("client", client_secret))
    nav(cp, lambda: cp.click("form button[type=submit]"))
    ok(len(set(re.findall(r"\b[0-9A-F]{5}-[0-9A-F]{5}\b", cp.content()))) == 10, "client sees 10 recovery codes")
    cp.goto(BASE + "/portal/client")
    ok("agreement" in cp.content().lower(), "agreement prompt shown on portal home")
    cp.goto(f"{BASE}/portal/client/cases/{lead_id}?tab=messages")
    ok("Sign agreement" in cp.content() and "MARKER-MSG-4402" not in cp.content(), "messages locked before signing")
    token = csrf(cp)
    r = cp.request.post(BASE + "/portal/uploads/start", headers={"X-CSRF-Token": token, "Accept": "application/json"},
                        multipart={"case_id": str(lead_id), "name": "early.pdf", "size": "100"})
    ok(r.status == 403, "upload before agreement returns 403")
    cp.fill("input[name=signed_name]", "Riley Seller")
    cp.check("input[name=agree]")
    nav(cp, lambda: cp.click("form[action$='/nda'] button[type=submit]"))
    ok("MARKER-MSG-4402" in cp.content(), "signing opens messages with staff message")
    ok("MARKER-NOTE-9913" not in cp.content(), "internal note hidden from client")
    cp.screenshot(path=str(SHOTS / "portal-messages-390.png"), full_page=True)

    # ---------------------------------------------------------- 8. uploads
    print("8. Encrypted chunked uploads")
    pdf = tmp / "evidence.pdf"
    pdf.write_bytes(b"%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" + b"PDFMARKER-2231" * 10)
    png_bytes = make_png(tmp / "screenshot.png", 9)
    spoof = tmp / "invoice.pdf"
    spoof.write_bytes(b"<html><body>not a pdf SPOOFMARK</body></html>")
    cp.goto(f"{BASE}/portal/client/cases/{lead_id}?tab=documents")
    cp.set_input_files("[data-uploader] input[type=file]", [str(pdf), str(tmp / "screenshot.png")])
    until(lambda: cp.locator("[data-uploader] .up-list li.done").count() >= 2, 120)
    ok(cp.locator("[data-uploader] .up-list li.done").count() == 2, "PDF and 9 MB PNG (3 chunks) uploaded")
    cp.set_input_files("[data-uploader] input[type=file]", [str(spoof)])
    until(lambda: "does not match" in cp.inner_text("[data-uploader]"), 30)
    ok("does not match" in cp.inner_text("[data-uploader]"), "spoofed .pdf rejected (does not match)")
    cp.reload()
    ok("evidence.pdf" in cp.content() and "screenshot.png" in cp.content(), "both uploads listed")
    with db() as c:
        vault = c.execute("SELECT vault_dir FROM clients WHERE id = ?", (client_id,)).fetchone()["vault_dir"]
        doc_rows = c.execute("SELECT * FROM documents WHERE client_id = ? AND deleted_at IS NULL", (client_id,)).fetchall()
    files = list((APP / "storage" / "vault" / vault).glob("*"))
    ok(len(files) == 2 and all(re.fullmatch(r"[a-f0-9]{48}", f.name) for f in files), "exactly 2 vault files with 48-hex names")
    ok(all(f.read_bytes()[:4] == b"DRV1" and b"%PDF" not in f.read_bytes() and b"PDFMARKER" not in f.read_bytes() for f in files), "vault files are ciphertext (DRV1, no plaintext)")
    ok(all(str(d["name_enc"]).startswith("v1.") for d in doc_rows), "document names encrypted")

    # ---------------------------------------------------------- 9. downloads
    print("9. Downloads")
    png_doc = next(d for d in doc_rows if d["size"] == len(png_bytes))
    page.goto(BASE + "/portal/")
    r = page.request.get(f"{BASE}/portal/documents/{png_doc['id']}/download")
    ok(r.ok and r.body() == png_bytes, "staff download returns exact bytes")
    r = page.request.get(f"{BASE}/portal/documents/{png_doc['id']}/view")
    ok("sandbox" in (r.headers.get("content-security-policy") or ""), "inline view is sandboxed")

    # ---------------------------------------------------------- 10. isolation
    print("10. Isolation")
    page.goto(BASE + "/portal/clients/new")
    page.fill("input[name=display_name]", "Other Client LLC")
    nav(page, lambda: page.click("form[action$='/clients'] button[type=submit]"))
    with db() as c:
        other = c.execute("SELECT * FROM clients WHERE id != ? ORDER BY id DESC", (client_id,)).fetchone()
    ok(other["vault_dir"] != vault and other["dek_wrapped"] != cl["dek_wrapped"], "second client has its own vault and key")
    page.goto(BASE + f"/portal/cases/new?client={other['id']}")
    page.select_option("select[name=client_id]", str(other["id"]))
    page.fill("input[name=title]", "Other case")
    page.select_option("select[name=platform]", "ebay")
    nav(page, lambda: page.click("form[action$='/cases'] button[type=submit]"))
    other_case = int(re.search(r"/cases/(\d+)", page.url).group(1))
    for path, label in [(f"/portal/client/cases/{other_case}", "another client's case"), (f"/portal/cases/{lead_id}", "staff case URL"),
                        (f"/portal/clients/{client_id}", "client page"), ("/portal/leads", "leads"), ("/portal/admin/users", "admin users")]:
        r = cp.request.get(BASE + path)
        ok(r.status in (403, 404), f"client blocked from {label} ({r.status})")
    r = cp.request.post(f"{BASE}/portal/client/cases/{lead_id}/messages", form={"body": "no csrf"})
    ok(r.status == 419, "POST without CSRF refused (419)")
    with db() as c:
        denied = c.execute("SELECT COUNT(*) n FROM audit_log WHERE action = 'access_denied'").fetchone()["n"]
    ok(denied >= 3, f"access_denied audited ({denied})")

    # ---------------------------------------------------------- 11. invoicing
    print("11. Invoicing")
    page.goto(BASE + "/portal/admin/catalog")
    page.fill("input[name=name]", "Plan of Action package")
    page.fill("input[name=unit_price]", "450")
    page.fill("input[name=vat]", "10")
    nav(page, lambda: page.click("form[action$='/admin/catalog'] button[type=submit]"))
    page.goto(f"{BASE}/portal/invoices/new?client={client_id}&case={lead_id}")
    rows = page.query_selector_all("[data-items] tbody tr")
    page.fill("[data-items] tbody tr >> nth=0 >> input[name='item_desc[]']", "Plan of Action package")
    page.fill("[data-items] tbody tr >> nth=0 >> input[name='item_qty[]']", "2")
    page.fill("[data-items] tbody tr >> nth=0 >> input[name='item_unit[]']", "450")
    page.fill("[data-items] tbody tr >> nth=0 >> input[name='item_vat[]']", "10")
    page.click("[data-add-row]")
    page.fill("[data-items] tbody tr >> nth=1 >> input[name='item_desc[]']", "Held funds release support")
    page.fill("[data-items] tbody tr >> nth=1 >> input[name='item_qty[]']", "1")
    page.fill("[data-items] tbody tr >> nth=1 >> input[name='item_unit[]']", "1200")
    page.fill("[data-items] tbody tr >> nth=1 >> input[name='item_vat[]']", "10")
    page.fill("input[name=discount]", "100")
    nav(page, lambda: page.click("form[action$='/invoices'] button[type=submit]"))
    inv_id = int(re.search(r"/invoices/(\d+)", page.url).group(1))
    with db() as c:
        inv = c.execute("SELECT * FROM invoices WHERE id = ?", (inv_id,)).fetchone()
    ok((inv["subtotal"], inv["discount"], inv["vat"], inv["total"]) == (210000, 10000, 20000, 220000), f"invoice maths (tax after discount) {tuple(inv[k] for k in ('subtotal','discount','vat','total'))}")
    page.click("form[action$='/send'] button[type=submit]")
    confirm(page)
    def pay():
        page.fill("input[name=amount]", "1000")
        page.fill("input[name=reference]", "ACH-5501")
        nav(page, lambda: page.click("form[action$='/payments'] button[type=submit]"))
    pay()
    pay()
    ok("already" in page.content().lower() or "duplicate" in page.content().lower(), "duplicate payment rejected")
    with db() as c:
        inv = c.execute("SELECT * FROM invoices WHERE id = ?", (inv_id,)).fetchone()
    ok(inv["status"] == "partial" and inv["paid"] == 100000, "invoice part-paid once")
    cp.goto(BASE + "/portal/client/invoices")
    ok(inv["number"] in cp.content(), "client sees issued invoice")
    r = cp.request.get(f"{BASE}/portal/invoices/{inv_id}/print")
    ok(r.ok and "$2,200.00" in r.text(), "client can open printable invoice")

    # ---------------------------------------------------------- 12. client portal
    print("12. Client portal and export")
    cp.goto(BASE + "/portal/client")
    ok("Appeal progress" in cp.content() and "stepper" in cp.content(), "portal home shows stepper and appeal progress")
    cp.goto(f"{BASE}/portal/client/cases/{lead_id}?tab=tracker")
    ok("Submitted" in cp.content() and "$12,500.00" in cp.content(), "client tracker shows submission and held funds")
    cp.screenshot(path=str(SHOTS / "portal-tracker-390.png"), full_page=True)
    with cp.expect_download() as dl:
        cp.goto(BASE + "/portal/client/export") if False else cp.evaluate("window.location.href = '/portal/client/export'")
    zdata = Path(dl.value.path()).read_bytes()
    z = zipfile.ZipFile(io.BytesIO(zdata))
    names = z.namelist()
    case_json = next((n for n in names if n.endswith("case.json")), "")
    record = json.loads(z.read(case_json)) if case_json else {}
    ok(bool(case_json) and "held_funds" in record and "appeal_tracker" in record, "export ZIP has case.json with appeals and funds")
    ok(any(z.read(n) == png_bytes for n in names if n.endswith(".png")), "export contains byte-identical PNG")
    ok(b"MARKER-NOTE-9913" not in zdata, "export excludes internal notes")

    # ---------------------------------------------------------- 13. staff pages
    print("13. Staff pages")
    for path in ["/", "/leads", "/clients", "/cases", "/tasks", "/calendar", "/invoices", "/reports", "/admin/users",
                 "/admin/catalog", "/admin/audit", "/admin/settings", "/admin/security", "/notifications", "/account"]:
        r = page.goto(BASE + "/portal" + path)
        txt = page.content()
        ok(r.status == 200 and "Something went wrong" not in txt and "Warning:" not in txt, f"staff page {path} renders")
    r = page.request.get(BASE + "/portal/search?q=" + ref1, headers={"Accept": "application/json"})
    ok(r.ok and ref1 in r.text(), "command palette search finds the case")
    page.goto(f"{BASE}/portal/cases/{lead_id}")
    page.screenshot(path=str(SHOTS / "crm-case-1440.png"), full_page=True)
    page.goto(BASE + "/portal/")
    page.screenshot(path=str(SHOTS / "crm-dashboard-1440.png"), full_page=True)
    page.emulate_media(color_scheme="dark")
    page.reload()
    page.wait_for_timeout(600)
    page.screenshot(path=str(SHOTS / "crm-dashboard-dark.png"), full_page=True)
    page.emulate_media(color_scheme="light")
    mctx = browser.new_context(viewport={"width": 390, "height": 844})
    mp = watch(mctx.new_page())
    sign_in(mp, "master@accountsuspension.test", PW, "master", master_secret)
    sw = mp.evaluate("document.documentElement.scrollWidth")
    ok(sw <= 390, f"mobile staff dashboard has no horizontal overflow ({sw})")
    mctx.close()

    # ---------------------------------------------------------- 14. key rotation
    print("14. Key rotation")
    page.goto(BASE + "/portal/admin/security")
    page.click("form[action$='/rotate'] button[type=submit]")
    page.wait_for_selector("#confirm-dialog[open]")
    nav(page, lambda: page.click("#confirm-ok"))
    ok("/sudo" in page.url, "rotation requires sudo")
    page.fill("input[name=password]", PW)
    page.fill("input[name=code]", fresh_code("master", master_secret))
    nav(page, lambda: page.click("form button[type=submit]"))
    if "/admin/security" in page.url and "rotat" not in page.content().lower():
        pass
    page.goto(BASE + "/portal/admin/security")
    page.click("form[action$='/rotate'] button[type=submit]")
    confirm(page)
    prev = list((APP / "keys").glob("master.key.prev-*"))
    ok(len(prev) == 1, "exactly one previous key archived")
    page.goto(f"{BASE}/portal/cases/{lead_id}?tab=messages")
    ok("MARKER-MSG-4402" in page.content(), "messages still decrypt after rotation")
    r = page.request.get(f"{BASE}/portal/documents/{png_doc['id']}/download")
    ok(r.body() == png_bytes, "files still decrypt after rotation")

    # ---------------------------------------------------------- 15. backup
    print("15. Backup")
    page.goto(BASE + "/portal/admin/security")
    nav(page, lambda: page.click("form[action$='/backup'] button[type=submit]"))
    backups = list((APP / "storage" / "backups").glob("*.drbak"))
    ok(len(backups) >= 1, "backup created")
    ok(all(b"riley.seller@example.org" not in b.read_bytes() and b"MARKER" not in b.read_bytes() for b in backups), "backup is ciphertext")

    # ---------------------------------------------------------- 16. recovery code
    print("16. Recovery codes and lockout")
    rc = g["codes"][0]
    rctx = browser.new_context()
    rp = watch(rctx.new_page())
    rp.goto(BASE + "/portal/login")
    rp.fill("input[name=email]", "master@accountsuspension.test")
    rp.fill("input[name=password]", PW)
    nav(rp, lambda: rp.click("form button[type=submit]"))
    rp.fill("input[name=code]", rc)
    nav(rp, lambda: rp.click("form button[type=submit]"))
    ok("Dashboard" in rp.content(), "recovery code signs in once")
    rctx.close()
    rctx = browser.new_context()
    rp = watch(rctx.new_page())
    rp.goto(BASE + "/portal/login")
    rp.fill("input[name=email]", "master@accountsuspension.test")
    rp.fill("input[name=password]", PW)
    nav(rp, lambda: rp.click("form button[type=submit]"))
    rp.fill("input[name=code]", rc)
    nav(rp, lambda: rp.click("form button[type=submit]"))
    ok("Dashboard" not in rp.content(), "reused recovery code fails")
    rctx.close()
    lctx = browser.new_context()
    lp = watch(lctx.new_page())
    for _ in range(9):
        lp.goto(BASE + "/portal/login")
        lp.fill("input[name=email]", "riley.seller@example.org")
        lp.fill("input[name=password]", "wrong-password-xx")
        nav(lp, lambda: lp.click("form button[type=submit]"))
    ok("not recognized" in lp.content() or "locked" in lp.content().lower(), "repeated bad passwords give a generic error")
    lctx.close()

    # ---------------------------------------------------------- 17. case lead role
    print("17. Case lead role")
    page.goto(BASE + "/portal/admin/users")
    page.fill("#invite-form input[name=name]", "Casey Lead")
    page.fill("#invite-form input[name=email]", "casey.lead@accountsuspension.test")
    page.select_option("#invite-form select[name=role]", "lead")
    nav(page, lambda: page.click("#invite-form button[type=submit]"))
    lead_invite = link_in(last_mail_to("casey.lead@accountsuspension.test"), "/portal/invite/")
    ok(bool(lead_invite), "staff invite emailed")
    lctx = browser.new_context()
    lp = watch(lctx.new_page())
    lp.goto(lead_invite)
    lp.fill("input[name=name]", "Casey Lead")
    lp.fill("input[name=password]", "Lead-Strong-Passphrase-31")
    lp.fill("input[name=password2]", "Lead-Strong-Passphrase-31")
    lp.check("input[name=accept]")
    nav(lp, lambda: lp.click("form button[type=submit]"))
    enrol(lp, "lead")
    lp.goto(BASE + "/portal/cases")
    ok(ref1 not in lp.content(), "case lead does not see unassigned cases")
    ok(lp.request.get(f"{BASE}/portal/cases/{lead_id}").status == 404, "unassigned case is 404 for case lead")
    ok(lp.request.get(BASE + "/portal/admin/users").status == 403, "admin pages 403 for case lead")
    ok(lp.request.get(BASE + "/portal/admin/security").status == 403, "master pages 403 for case lead")
    lp.goto(BASE + "/portal/leads")
    ok(ref2 in lp.content(), "case lead sees unclaimed leads")
    lctx.close()

    # ---------------------------------------------------------- 18. CLI
    print("18. CLI tools")
    php_sql("UPDATE deadlines SET due_at = '%s', reminded_at = NULL" % time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(time.time() + 3600)))
    out = subprocess.run(["php", str(APP / "cli" / "cron.php")], capture_output=True, text=True)
    ok(out.returncode == 0 and "reminders" in out.stdout, "cron runs and reports reminders")
    with db() as c:
        n = c.execute("SELECT COUNT(*) n FROM notifications WHERE kind LIKE '%deadline%'").fetchone()["n"]
    ok(n >= 1, "deadline reminder notification created")
    out = subprocess.run(["php", str(APP / "cli" / "tool.php"), "selftest"], capture_output=True, text=True)
    ok(out.returncode == 0 and "OK" in out.stdout, "selftest passes")
    import shutil
    out = subprocess.run(["php", str(APP / "cli" / "tool.php"), "backup:create"], capture_output=True, text=True)
    ok(out.returncode == 0, "CLI backup:create works")
    backup = max((APP / "storage" / "backups").glob("*.drbak"), key=lambda f: f.stat().st_mtime)
    fresh = ENV / "ascrm-restore"
    shutil.rmtree(fresh, ignore_errors=True)
    shutil.copytree(APP, fresh, ignore=shutil.ignore_patterns("*.sqlite*", "vault", "backups", "mail", "logs"))
    if MYSQL:
        subprocess.run(["mysql", "-e", "DROP DATABASE IF EXISTS %s_restore; CREATE DATABASE %s_restore; GRANT ALL ON %s_restore.* TO '%s'@'localhost';" % (MY["name"], MY["name"], MY["name"], MY["user"])], check=True)
    code = ("$f = '%s/config/config.php'; $c = require $f; $c['db']['path'] = '%s/storage/db/restore.sqlite'; "
            + ("$c['db']['name'] .= '_restore'; " if MYSQL else "")
            + "$c['master_key_path'] = '%s/keys/master.key'; $c['vault_path'] = '%s/storage/vault'; "
            "file_put_contents($f, \"<?php\\nreturn \" . var_export($c, true) . \";\\n\");") % (fresh, fresh, fresh, fresh)
    subprocess.run(["php", "-r", code], check=True)
    out = subprocess.run(["php", str(fresh / "cli" / "tool.php"), "backup:restore", str(backup)], capture_output=True, text=True)
    ok(out.returncode == 0, "backup restores into a fresh install" + ("" if out.returncode == 0 else ": " + out.stdout + out.stderr))
    def counts(path) -> dict:
        con = _PdoConn(path) if MYSQL else sqlite3.connect(f"file:{path}?mode=ro", uri=True)
        try:
            return {t: con.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0] for t in ("users", "clients", "cases", "messages", "documents", "targets", "funds", "invoices", "payments")}
        finally:
            con.close()
    if MYSQL:
        ok(counts(MY["name"] + "_restore") == counts(MY["name"]), "restored row counts match")
    else:
        restored = fresh / "storage" / "db" / "restore.sqlite"
        ok(restored.exists() and counts(restored) == counts(DB), "restored row counts match")
    cctx.close()
    g.update(lead_id=lead_id, client_id=client_id)
    cms_tests(page, browser, ref1, ref2)


def cms_tests(page: Page, browser, ref1: str, ref2: str) -> None:
    print("19. CMS: blog posts")
    page.goto(BASE + "/portal/cms/posts/new")
    body = ("A Walmart Marketplace suspension usually follows seller performance standards. Read the notice first.\n\n"
            "## What the notice means\n\nWalmart cites a **performance standard** or a policy. <script>alert('xss')</script>\n\n"
            "- Pull the order data\n- Fix the process\n- Write the action plan\n\n"
            "Read our [Walmart page](/walmart-reinstatement/) or [a bad link](javascript:alert(1)). CMSMARKER-6612 " + "More detail. " * 20)
    page.fill("input[name=title]", "How to appeal a Walmart seller suspension in 2026")
    page.fill("textarea[name=body]", body)
    page.select_option("select[name=status]", "published")
    page.select_option("select[name=category]", "commerce")
    page.select_option("select[name='platforms[]']", ["walmart", "amazon"])
    nav(page, lambda: page.click("#post-form button[type=submit]"))
    ok("Post published" in page.content(), "post published from the CRM")
    slug = "how-to-appeal-a-walmart-seller-suspension-in-2026"
    pub = watch(browser.new_page())
    r = pub.goto(f"{BASE}/blog/{slug}/")
    html = pub.content()
    ok(r.status == 200 and "CMSMARKER-6612" in html and "How to appeal a Walmart seller suspension" in html, "post renders on the website at /blog/slug/")
    ok("<script>alert" not in html and "javascript:alert" not in html and "&lt;script&gt;" in html, "post body is escaped (no script, no javascript: link)")
    ok(f'rel="canonical" href="https://accountsuspension.com/blog/{slug}/"' in html, "post has canonical URL")
    ok('"@type": "Article"' in html or '"@type":"Article"' in html, "post has Article JSON-LD")
    ok("/walmart-reinstatement/" in html and "Start a confidential case" in html, "post links to platform pages and case intake")
    r = pub.request.get(f"{BASE}/blog/{slug}", max_redirects=0)
    ok(r.status == 301 and r.headers.get("location", "").endswith(f"/blog/{slug}/"), "missing trailing slash redirects 301")
    pub.goto(BASE + "/blog/")
    until(lambda: slug in pub.content(), 10)
    ok(f"/blog/{slug}/" in pub.content(), "blog listing shows the CMS post")
    sm = pub.request.get(BASE + "/blog-sitemap.xml")
    ok(sm.ok and f"/blog/{slug}/" in sm.text(), "blog sitemap lists the post")
    ok(pub.request.get(BASE + "/_theme/post.html").status == 403, "theme shell is not publicly served")

    print("20. CMS: verified testimonials")
    page.goto(BASE + "/portal/cms/testimonials")
    page.fill("#t-form input[name=name]", "Jordan P.")
    page.fill("#t-form input[name=role]", "Amazon seller")
    page.select_option("#t-form select[name=platform]", "amazon")
    page.fill("#t-form input[name=given_on]", "2026-08-01")
    page.fill("#t-form textarea[name=body]", "TESTIMARKER-4410 Clear, honest advice and a Plan of Action that answered exactly what Amazon asked.")
    r = page.request.post(BASE + "/portal/cms/testimonials", headers={"X-CSRF-Token": csrf(page)}, form={
        "name": "Fake Person", "rating": "5", "given_on": "2026-08-01", "body": "This testimonial has no consent record at all.", "consent_ref": "x"}, max_redirects=0)
    page.goto(BASE + "/portal/cms/testimonials")
    ok("Fake Person" not in page.content(), "testimonial without a real consent record is rejected by the server")
    page.fill("#t-form input[name=name]", "Jordan P.")
    page.select_option("#t-form select[name=platform]", "amazon")
    page.fill("#t-form input[name=given_on]", "2026-08-01")
    page.fill("#t-form textarea[name=body]", "TESTIMARKER-4410 Clear, honest advice and a Plan of Action that answered exactly what Amazon asked.")
    page.fill("#t-form input[name=consent_ref]", "Signed consent form in vault, 08/01/2026")
    nav(page, lambda: page.click("#t-form button[type=submit]"))
    ok("Awaiting approval" in page.content(), "testimonial saved as awaiting approval")
    pub.goto(BASE + "/amazon-reinstatement/")
    pub.wait_for_timeout(1500)
    ok("TESTIMARKER-4410" not in pub.content(), "unapproved testimonial not shown on the website")
    page.click("form[action*='/approve'] button[type=submit]")
    confirm(page)
    ok("Published" in page.content(), "admin approval publishes the testimonial")
    pub.goto(BASE + "/amazon-reinstatement/")
    until(lambda: "TESTIMARKER-4410" in pub.content(), 10)
    ok("TESTIMARKER-4410" in pub.content() and pub.is_visible("[data-testimonials]"), "approved testimonial shown on the matching platform page")
    pub.goto(BASE + "/ebay-reinstatement/")
    pub.wait_for_timeout(1500)
    ok(not pub.is_visible("[data-testimonials]"), "testimonial not shown on unrelated platform page")
    feed = pub.request.get(BASE + "/cms.php?feed=testimonials").json()
    ok(feed.get("count") == 1 and "consent" not in json.dumps(feed).lower(), "testimonial feed exposes no consent records")

    print("21. CMS: redirects and 404")
    page.goto(BASE + "/portal/cms/redirects")
    page.fill("input[name=from_path]", "/old-wordpress-post/")
    page.fill("input[name=to_path]", f"/blog/{slug}/")
    nav(page, lambda: page.click("form[action$='/cms/redirects'] button[type=submit]"))
    r = pub.request.get(BASE + "/old-wordpress-post/", max_redirects=0)
    ok(r.status == 301 and r.headers.get("location") == f"/blog/{slug}/", "redirect from old WordPress URL (301)")
    r = pub.request.get(BASE + "/Old-WordPress-Post", max_redirects=0)
    ok(r.status == 301, "redirect matching ignores case and trailing slash")
    page.goto(BASE + "/portal/cms/redirects")
    ok(">2<" in page.content().replace(" ", ""), "redirect hits counted")
    r = pub.request.get(BASE + "/this-page-does-not-exist/")
    ok(r.status == 404 and "That page is not here" in r.text(), "unknown URL returns the 404 page")
    r = pub.request.get(BASE + "/amazon-reinstatement/")
    ok(r.status == 200, "static pages are unaffected by the CMS fallback")

    print("22. Website links into the CRM")
    pub.goto(BASE + "/")
    ok(pub.locator("a.client-login").count() == 1 and pub.locator("footer a:has-text('Check a case')").count() == 1, "Client login and Check a case links on the website")
    pub.goto(f"{BASE}/thank-you/?ref={ref1}")
    ok((pub.get_attribute("#status-link", "href") or "").endswith("?ref=" + ref1), "thank-you page links to status check with the reference")
    pub.close()

    print("23. Accessibility of CRM screens (axe-core, serious and critical)")
    axe = (Path(__file__).parent / "vendor" / "axe.min.js").read_text()
    actx = browser.new_context(viewport={"width": 1440, "height": 900}, bypass_csp=True)
    ap = actx.new_page()
    for path in ["/portal/login", "/portal/status"]:
        ap.goto(BASE + path)
        ap.add_script_tag(content=axe)
        v = ap.evaluate("async () => (await axe.run(document, {resultTypes:['violations']})).violations.filter(v => ['serious','critical'].includes(v.impact)).map(v => v.id + ' ' + v.nodes.slice(0,2).map(n => n.target.join(' ')).join(' | '))")
        ok(not v, f"axe clean on {path} {v}")
    sign_in(ap, "master@accountsuspension.test", PW, "master", globals()["master_secret"])
    with db() as c:
        cid = c.execute("SELECT id FROM cases WHERE client_id IS NOT NULL ORDER BY id LIMIT 1").fetchone()["id"]
        pid = c.execute("SELECT id FROM posts ORDER BY id LIMIT 1").fetchone()["id"]
    for path in ["/portal/", "/portal/leads", "/portal/cases", f"/portal/cases/{cid}", f"/portal/cases/{cid}?tab=appeals", f"/portal/cases/{cid}?tab=funds",
                 f"/portal/cases/{cid}?tab=messages", "/portal/invoices", "/portal/reports", "/portal/calendar", "/portal/cms/posts", f"/portal/cms/posts/{pid}",
                 "/portal/cms/testimonials", "/portal/cms/redirects", "/portal/admin/settings", "/portal/admin/security"]:
        ap.goto(BASE + path)
        ap.add_script_tag(content=axe)
        v = ap.evaluate("async () => (await axe.run(document, {resultTypes:['violations']})).violations.filter(v => ['serious','critical'].includes(v.impact)).map(v => v.id + ' ' + v.nodes.slice(0,2).map(n => n.target.join(' ')).join(' | '))")
        ok(not v, f"axe clean on {path} {v}")
    ap.emulate_media(color_scheme="dark")
    ap.goto(BASE + "/portal/")
    ap.add_script_tag(content=axe)
    v = ap.evaluate("async () => (await axe.run(document, {resultTypes:['violations']})).violations.filter(v => ['serious','critical'].includes(v.impact)).map(v => v.id + ' ' + v.nodes.slice(0,2).map(n => n.target.join(' ')).join(' | '))")
    ok(not v, f"axe clean on dashboard in dark mode {v}")
    actx.close()


if __name__ == "__main__":
    sys.exit(main())
