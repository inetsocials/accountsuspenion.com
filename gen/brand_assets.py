"""Generate brand assets into static/assets/img/.

Wordmark glyphs are outlined from Plus Jakarta Sans ExtraBold (OFL) with
fontTools, so the SVG logo renders identically without web fonts.
PNG variants are rendered with Pillow from the same font.

Usage: python3 gen/brand_assets.py   (requires: pip install fonttools pillow brotli)
"""
from __future__ import annotations

from pathlib import Path

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent.parent
FONT = ROOT / "gen" / "brand" / "plus-jakarta-sans-latin-800-normal.woff"
OUT = ROOT / "static" / "assets" / "img"

NAVY = "#00183F"
BLUE = "#0054E4"
LIGHT_BLUE = "#8FB4FF"
INK = "#051733"

# Mark: rounded square with a shield and an unlocked check.
MARK = ('<rect x="0" y="0" width="40" height="40" rx="10" fill="{bg}"/>'
        '<path d="M20 7.5l10 3.6v7.6c0 6.2-4.2 11.6-10 13.3-5.8-1.7-10-7.1-10-13.3v-7.6z" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round"/>'
        '<path d="M15.2 19.6l3.4 3.4 6.4-6.8" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>')


def text_paths(text: str, size: float, x0: float, baseline: float) -> tuple[str, float]:
    font = TTFont(str(FONT))
    gs = font.getGlyphSet()
    cmap = font.getBestCmap()
    upm = font["head"].unitsPerEm
    hmtx = font["hmtx"]
    scale = size / upm
    x = x0
    ds = []
    for ch in text:
        gname = cmap.get(ord(ch))
        if not gname:
            continue
        pen = SVGPathPen(gs)
        tpen = TransformPen(pen, (scale, 0, 0, -scale, x, baseline))
        gs[gname].draw(tpen)
        d = pen.getCommands()
        if d:
            ds.append(d)
        x += hmtx[gname][0] * scale
    return " ".join(ds), x


def logo_svg(word_color: str, tld_color: str, bg: str) -> str:
    size = 25.0
    d1, x_end = text_paths("AccountSuspension", size, 50, 29)
    d2, x_end2 = text_paths(".com", size, x_end, 29)
    w = int(x_end2 + 2)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} 40" width="{w}" height="40" role="img" aria-label="AccountSuspension.com">'
            f'{MARK.format(bg=bg)}<path fill="{word_color}" d="{d1}"/><path fill="{tld_color}" d="{d2}"/></svg>\n')


def favicon_svg() -> str:
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">{MARK.format(bg=BLUE)}</svg>\n'


def draw_mark(size: int, bg: str = BLUE) -> Image.Image:
    s = size / 40
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    d.rounded_rectangle([0, 0, size - 1, size - 1], radius=int(10 * s), fill=bg)
    shield = [(20, 7.5), (30, 11.1), (30, 18.7), (29, 23.5), (26.5, 27.5), (23.5, 30.5), (20, 32), (16.5, 30.5), (13.5, 27.5), (11, 23.5), (10, 18.7), (10, 11.1), (20, 7.5)]
    d.line([(x * s, y * s) for x, y in shield], fill="white", width=max(2, int(2.6 * s)), joint="curve")
    d.line([(15.2 * s, 19.6 * s), (18.6 * s, 23 * s), (25 * s, 16.2 * s)], fill="white", width=max(2, int(2.8 * s)), joint="curve")
    return img


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "logo.svg").write_text(logo_svg(NAVY, BLUE, BLUE), encoding="utf-8")
    (OUT / "logo-light.svg").write_text(logo_svg("#FFFFFF", LIGHT_BLUE, BLUE), encoding="utf-8")
    (OUT / "favicon.svg").write_text(favicon_svg(), encoding="utf-8")

    draw_mark(48).save(OUT / "favicon.png")
    apple = Image.new("RGBA", (180, 180), BLUE)
    apple.alpha_composite(draw_mark(140), (20, 20))
    apple.convert("RGB").save(OUT / "apple-touch-icon.png")
    draw_mark(512).save(OUT / "logo-512.png")

    # Open Graph image 1200x630
    og = Image.new("RGB", (1200, 630), INK)
    d = ImageDraw.Draw(og)
    for i in range(630):  # subtle vertical gradient
        t = i / 630
        c = (int(5 + 5 * t), int(23 + 10 * t), int(51 + 25 * t))
        d.line([(0, i), (1200, i)], fill=c)
    og.paste(draw_mark(96), (80, 90), draw_mark(96))
    f_big = ImageFont.truetype(str(FONT), 58)
    f_mid = ImageFont.truetype(str(FONT), 34)
    d.text((196, 104), "AccountSuspension", font=f_mid, fill="white")
    w = d.textlength("AccountSuspension", font=f_mid)
    d.text((196 + w, 104), ".com", font=f_mid, fill=LIGHT_BLUE)
    d.text((80, 260), "Suspended, deactivated", font=f_big, fill="white")
    d.text((80, 332), "or holding your funds?", font=f_big, fill="white")
    d.text((80, 440), "Diagnose the notice. Evidence the fix. Appeal once, properly.", font=ImageFont.truetype(str(FONT), 28), fill=LIGHT_BLUE)
    d.rectangle([80, 520, 180, 526], fill=BLUE)
    og.save(OUT / "og.png", optimize=True)

    # CRM portal logos (PNG, 2x for a 26 to 30 px display height)
    crm = ROOT / "crm" / "portal" / "assets" / "img"
    crm.mkdir(parents=True, exist_ok=True)
    for name, word, tld in (("logo.png", NAVY, BLUE), ("logo-light.png", "#FFFFFF", LIGHT_BLUE)):
        h = 96
        font = ImageFont.truetype(str(FONT), 60)
        w1 = ImageDraw.Draw(Image.new("RGBA", (1, 1))).textlength("AccountSuspension", font=font)
        w2 = ImageDraw.Draw(Image.new("RGBA", (1, 1))).textlength(".com", font=font)
        width = int(96 + 24 + w1 + w2 + 4)
        img = Image.new("RGBA", (width, h), (0, 0, 0, 0))
        img.alpha_composite(draw_mark(96), (0, 0))
        d = ImageDraw.Draw(img)
        d.text((120, 14), "AccountSuspension", font=font, fill=word)
        d.text((120 + w1, 14), ".com", font=font, fill=tld)
        img.save(crm / name, optimize=True)
    draw_mark(64).save(crm / "favicon.png")
    print("brand assets written to", OUT, "and", crm)


if __name__ == "__main__":
    main()
