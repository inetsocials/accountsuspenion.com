"""Playwright QA matrix for the built site.

Serve dist/ with PHP so the intake handler runs:
    php -S 127.0.0.1:8080 -t dist
Then:
    python3 qa/qa_matrix.py http://127.0.0.1:8080 [screenshot_dir]
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
SHOTS = Path(sys.argv[2]) if len(sys.argv) > 2 else Path("qa/screens")
WIDTHS = [360, 390, 768, 1024, 1280, 1440]
PAGES = ["/", "/amazon-reinstatement/", "/services/", "/contact-us/", "/tools/reinstatement-readiness-score/",
         "/tools/suspension-notice-decoder/", "/blog/how-to-write-an-amazon-plan-of-action/", "/services/held-funds-payouts/", "/faq/"]
IGNORED = ("fonts.googleapis.com", "fonts.gstatic.com", "ERR_TUNNEL_CONNECTION_FAILED", "ERR_NAME_NOT_RESOLVED", "net::ERR")

failures: list[str] = []
passes = 0


def ok(cond: bool, label: str) -> None:
    global passes
    if cond:
        passes += 1
    else:
        failures.append(label)
        print("FAIL", label)


def main() -> int:
    SHOTS.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as pw:
        exe = os.environ.get("PW_CHROMIUM")  # e.g. /opt/pw-browsers/chromium-1194/chrome-linux/chrome
        browser = pw.chromium.launch(executable_path=exe) if exe else pw.chromium.launch()
        for w in WIDTHS:
            ctx = browser.new_context(viewport={"width": w, "height": 900})
            page = ctx.new_page()
            errors: list[str] = []
            page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
            page.on("pageerror", lambda ex: errors.append(str(ex)))
            for path in PAGES:
                errors.clear()
                page.goto(BASE + path, wait_until="load")
                sw = page.evaluate("document.documentElement.scrollWidth")
                ok(sw <= w, f"{w}px {path}: horizontal overflow ({sw}px)")
                real = [e for e in errors if not any(s in e for s in IGNORED)]
                ok(not real, f"{w}px {path}: console errors {real}")

            page.goto(BASE + "/")
            h = page.evaluate("document.body.scrollHeight")
            for y in range(0, h, 400):
                page.mouse.wheel(0, 400)
                page.wait_for_timeout(40)
            page.wait_for_timeout(700)
            hidden = page.evaluate("document.querySelectorAll('.reveal:not(.in)').length")
            ok(hidden == 0, f"{w}px all reveal blocks shown after scrolling ({hidden} hidden)")
            page.goto(BASE + "/")
            if w < 1080:
                page.click("label.burger")
                ok(page.is_visible("#mnav"), f"{w}px mobile menu opens")
                page.click("#mnav details.m-acc >> nth=0 >> summary")
                ok(page.is_visible("#mnav a[href$='amazon-reinstatement/']"), f"{w}px Platforms accordion expands")
                if w == 390:
                    page.screenshot(path=str(SHOTS / "mobile-menu-390.png"))
            else:
                page.hover(".menu > li.has-mega >> nth=0")
                page.wait_for_timeout(300)
                ok(page.is_visible(".menu > li.has-mega >> nth=0 >> .mega"), f"{w}px mega menu shows on hover")
                if w == 1440:
                    page.screenshot(path=str(SHOTS / "mega-1440.png"))
                    page.mouse.move(700, 890)
                    page.evaluate("document.querySelectorAll('.reveal').forEach(e=>e.classList.add('in'))")
                    page.wait_for_timeout(800)
                    page.screenshot(path=str(SHOTS / "home-1440.png"), full_page=True)
            if w == 390:
                page.goto(BASE + "/")
                page.evaluate("document.querySelectorAll('.reveal').forEach(e=>e.classList.add('in'))")
                page.wait_for_timeout(800)
                page.screenshot(path=str(SHOTS / "home-390.png"), full_page=True)
                page.goto(BASE + "/amazon-reinstatement/")
                page.screenshot(path=str(SHOTS / "platform-390.png"), full_page=True)
            if w == 1280:
                page.goto(BASE + "/amazon-reinstatement/")
                page.mouse.move(640, 880)
                page.evaluate("document.querySelectorAll('.reveal').forEach(e=>e.classList.add('in'))")
                page.wait_for_timeout(800)
                page.screenshot(path=str(SHOTS / "platform-1280.png"), full_page=True)

            # services filter
            page.goto(BASE + "/services/")
            page.fill("#pf", "paypal")
            visible = page.locator("#plat-list [data-filter-item]:visible").count()
            ok(visible == 1, f"{w}px services filter shows 1 result for paypal (got {visible})")

            # readiness score
            page.goto(BASE + "/tools/reinstatement-readiness-score/")
            page.click("#rs-form button[type=submit]")
            ok(page.is_visible("#rs-error"), f"{w}px readiness validates empty form")
            for i in range(1, 11):
                page.click(f"input[name=q{i}][value='{'1' if i % 3 else '0.5'}'] + span")
            page.click("#rs-form button[type=submit]")
            page.wait_for_timeout(1500)
            score = page.inner_text("#rs-score")
            ok(page.is_visible("#rs-result") and score.isdigit() and int(score) > 0, f"{w}px readiness produces a score ({score})")
            ok("score=" in (page.get_attribute("#rs-cta", "href") or ""), f"{w}px readiness CTA carries score")
            if w == 1280:
                page.screenshot(path=str(SHOTS / "readiness-1280.png"), full_page=True)

            # intake form end to end (once per two widths to respect the rate limit)
            if w in (390, 1440):
                page.goto(BASE + "/contact-us/?source=platform&platform=paypal")
                ok(page.input_value("#platform") == "paypal", f"{w}px intake pre-fills platform")
                page.click("[data-step='1'] [data-next]")
                page.click("[data-step='2'] [data-next]")
                ok(page.is_visible("[data-step='2']"), f"{w}px intake blocks empty step 2")
                page.click("input[name=issue][value=funds] + span")
                page.click("[data-step='2'] [data-next]")
                page.click("input[name=history][value=none] + span")
                page.click("[data-step='3'] [data-next]")
                page.click("input[name=urgency][value=revenue] + span")
                page.click("[data-step='4'] [data-next]")
                page.fill("#name", "QA Tester")
                page.fill("#email", "qa.tester@example.org")
                page.fill("#details", "PayPal permanently limited my account and is holding my balance. Testing intake.")
                page.check("input[name=consent]")
                page.wait_for_timeout(4200)  # minimum fill time
                page.click("#intake button[type=submit]")
                page.wait_for_url("**/thank-you/**", timeout=10000)
                ref = page.inner_text("#case-ref")
                ok(ref.startswith("AS-") and len(ref) == 11, f"{w}px intake returns a case reference ({ref})")

            ctx.close()
        browser.close()
    print(f"{passes} passed, {len(failures)} failed")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
