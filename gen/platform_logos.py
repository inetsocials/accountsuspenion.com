"""Platform logo tiles for static/assets/img/logos/{platform_id}.svg.

Marks come from Simple Icons (CC0 SVG data, vendored in gen/brand/simple-icons/).
Trademarks remain the property of their owners and are used only to identify
the platform a page is about (nominative use), with a site-wide disclaimer.

Brands that asked Simple Icons to remove their marks (Amazon, Walmart,
Wayfair, Lowe's, LinkedIn, Xbox, Yahoo) get a neutral letter tile instead.
We do not reproduce marks their owners have objected to.

Usage: python3 gen/platform_logos.py
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))

from brand_assets import text_paths  # noqa: E402
from content_platforms import PLATFORMS  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
SI = ROOT / "gen" / "brand" / "simple-icons"
OUT = ROOT / "static" / "assets" / "img" / "logos"

SLUG = {
    "ebay": "ebay", "etsy": "etsy", "stockx": "stockx", "shopify": "shopify", "tiktok-shop": "tiktok",
    "paypal": "paypal", "stripe": "stripe", "wise": "wise", "payoneer": "payoneer", "coinbase": "coinbase",
    "facebook": "facebook", "instagram": "instagram", "twitter": "x", "tiktok": "tiktok", "reddit": "reddit",
    "discord": "discord", "snapchat": "snapchat", "pinterest": "pinterest", "google-adsense": "googleadsense",
    "google-ads": "googleads", "google-merchant-center": "google", "meta-ads": "meta", "youtube": "youtube",
    "vimeo": "vimeo", "twitch": "twitch", "booking-com": "bookingdotcom", "airbnb": "airbnb", "uber": "uber",
    "lyft": "lyft", "doordash": "doordash", "instacart": "instacart", "upwork": "upwork", "fiverr": "fiverr",
    "gmail": "gmail",
}


def luminance(hexcol: str) -> float:
    r, g, b = (int(hexcol[i:i + 2], 16) / 255 for i in (0, 2, 4))
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def brand_tile(slug: str, hexcol: str) -> str:
    svg = (SI / f"{slug}.svg").read_text(encoding="utf-8")
    d = re.search(r'<path d="([^"]+)"', svg).group(1)
    if luminance(hexcol) > 0.72:
        bg, fg = f"#{hexcol}", "#111111"
    else:
        bg, fg = "#FFFFFF", f"#{hexcol}"
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            f'<rect width="40" height="40" rx="9" fill="{bg}"/>'
            f'<g transform="translate(9 9) scale(.9167)"><path fill="{fg}" d="{d}"/></g></svg>\n')


def letter_tile(name: str) -> str:
    letter = name.strip()[0].upper()
    probe, width = text_paths(letter, 20, 0, 0)
    x0 = (40 - width) / 2
    d, _ = text_paths(letter, 20, x0, 27)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            f'<rect width="40" height="40" rx="9" fill="#EEF3FB"/><path fill="#00183F" d="{d}"/></svg>\n')


def main() -> None:
    colors = json.loads((SI / "colors.json").read_text())
    OUT.mkdir(parents=True, exist_ok=True)
    marks = letters = 0
    for p in PLATFORMS:
        slug = SLUG.get(p["id"])
        if slug and slug in colors:
            (OUT / f'{p["id"]}.svg').write_text(brand_tile(slug, colors[slug]), encoding="utf-8")
            marks += 1
        else:
            (OUT / f'{p["id"]}.svg').write_text(letter_tile(p["name"]), encoding="utf-8")
            letters += 1
    print(f"{marks} brand marks, {letters} letter tiles written to {OUT}")


if __name__ == "__main__":
    main()
