"""Website element audit: every page, every link, every button.

Checks at 1440 px and 390 px:
  - every internal link and asset resolves (HTTP < 400)
  - every button and link has an accessible name
  - tap targets are at least 24 x 24 CSS px (WCAG 2.2 AA, 2.5.8) unless inline in text
  - no horizontal overflow
  - axe-core: no serious or critical accessibility violations (contrast, names, labels, ARIA)

Usage: python3 qa/site_audit.py BASE_URL [REPORT_JSON]
"""
from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path
from urllib.parse import urljoin, urlsplit

from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip("/")
REPORT = Path(sys.argv[2]) if len(sys.argv) > 2 else None
AXE = (Path(__file__).parent / "vendor" / "axe.min.js").read_text()
ROOT = Path(__file__).resolve().parent.parent

TARGETS_JS = """() => {
  const out = [];
  const inText = el => { const p = el.parentElement; if (!p) return false;
    const cs = getComputedStyle(el); if (cs.display !== 'inline') return false;
    return ['P','LI','SPAN','SMALL','DD','TD','FIGCAPTION','LABEL'].includes(p.tagName) && p.textContent.trim().length > el.textContent.trim().length + 2; };
  document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary, label.burger').forEach(el => {
    const r = el.getBoundingClientRect(); const cs = getComputedStyle(el);
    if (r.width === 0 || r.height === 0 || cs.visibility === 'hidden' || el.closest('[hidden]')) return;
    if (el.closest('.mega') && getComputedStyle(el.closest('.mega')).visibility === 'hidden') return;
    if (el.closest('.mnav') && getComputedStyle(el.closest('.mnav')).display === 'none') return;
    if (el.closest('.opts') && el.tagName === 'INPUT') return;   // custom radio: the label card is the target
    if (el.closest('.hp') || el.classList.contains('nav-toggle') || el.closest('.skip')) return;
    const hiddenFromAT = !!el.closest('[aria-hidden="true"]');
    const name = (el.getAttribute('aria-label') || el.innerText || el.value || el.getAttribute('title') || (el.labels && el.labels[0] && el.labels[0].innerText) || '').trim();
    out.push({tag: el.tagName, text: name.slice(0, 50), w: Math.round(r.width), h: Math.round(r.height), inline: inText(el), noname: !hiddenFromAT && !name && !el.querySelector('img[alt]:not([alt=""])')});
  });
  return out;
}"""


def main() -> int:
    sitemap = (ROOT / "dist" / "sitemap.xml").read_text()
    pages = [urlsplit(u).path for u in re.findall(r"<loc>([^<]+)</loc>", sitemap)] + ["/thank-you/?ref=AS-1A2B3C4D", "/404.html"]
    problems: list[str] = []
    checked_links: dict[str, int] = {}
    with sync_playwright() as pw:
        exe = os.environ.get("PW_CHROMIUM")
        browser = pw.chromium.launch(executable_path=exe) if exe else pw.chromium.launch()
        for width in (1440, 390):
            ctx = browser.new_context(viewport={"width": width, "height": 900})
            page = ctx.new_page()
            for path in pages:
                page.goto(BASE + path, wait_until="load")
                page.add_style_tag(content="*,*::before,*::after{transition:none!important;animation:none!important}")
                page.evaluate("document.querySelectorAll('.reveal').forEach(e => e.classList.add('in'))")
                sw = page.evaluate("document.documentElement.scrollWidth")
                if sw > width:
                    problems.append(f"{width}px {path}: horizontal overflow {sw}px")
                for t in page.evaluate(TARGETS_JS):
                    if t["noname"]:
                        problems.append(f"{width}px {path}: {t['tag']} without accessible name")
                    if not t["inline"] and (t["w"] < 24 or t["h"] < 24):
                        problems.append(f"{width}px {path}: small tap target {t['tag']} '{t['text']}' {t['w']}x{t['h']}")
                if width == 1440:
                    for href in page.eval_on_selector_all("a[href], link[href], img[src], script[src]",
                                                          "els => els.map(e => e.getAttribute('href') || e.getAttribute('src'))"):
                        url = urljoin(BASE + path, href)
                        if not url.startswith(BASE) or url in checked_links:
                            continue
                        r = page.request.get(url, max_redirects=5)
                        checked_links[url] = r.status
                        if r.status >= 400:
                            problems.append(f"{path}: broken link {href} ({r.status})")
                page.add_script_tag(content=AXE)
                res = page.evaluate("""async () => { const r = await axe.run(document, {resultTypes: ['violations']});
                    return r.violations.filter(v => ['serious','critical'].includes(v.impact)).map(v => ({id: v.id, impact: v.impact, n: v.nodes.length, target: v.nodes.slice(0,3).map(n => n.target.join(' ')), summary: v.nodes[0] && v.nodes[0].failureSummary}));}""")
                for v in res:
                    problems.append(f"{width}px {path}: axe {v['impact']} {v['id']} x{v['n']} {v['target']} :: {(v['summary'] or '')[:160]}")
            ctx.close()
        browser.close()
    uniq = sorted(set(problems))
    for p in uniq:
        print("ISSUE", p)
    print(f"{len(pages)} pages x 2 widths, {len(checked_links)} unique URLs checked, {len(uniq)} issues")
    if REPORT:
        REPORT.write_text(json.dumps(uniq, indent=1))
    return 1 if uniq else 0


if __name__ == "__main__":
    sys.exit(main())
