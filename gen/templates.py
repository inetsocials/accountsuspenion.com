"""HTML shell, navigation, footer, icons and JSON-LD helpers."""
from __future__ import annotations

import json
from html import escape

import config as C
from content_platforms import CATEGORIES, PLATFORMS
from content_services import SERVICES


def e(s: str) -> str:
    return escape(str(s), quote=True)


# ------------------------------------------------------------------ context
class Ctx:
    """Resolves internal links relative to the current page.

    Relative links keep the site portable (subfolder staging, local preview).
    `absolute=True` is used for 404.html, which can be served at any URL.
    """

    def __init__(self, path: str, absolute: bool = False):
        self.path = path
        self.absolute = absolute
        depth = 0 if path in ("/", "/404.html") else path.strip("/").count("/") + 1
        self.pre = "../" * depth if depth else "./"

    def link(self, p: str) -> str:
        if p.startswith(("http:", "https:", "#", "mailto:")):
            return p
        if self.absolute:
            return p
        if p == "/":
            return self.pre
        return self.pre + p.lstrip("/")

    def asset(self, p: str) -> str:
        return self.link("/assets/" + p)


# ------------------------------------------------------------------ icons
_IC = {
    "shield": '<path d="M12 3l8 3v6c0 4.5-3.4 8.4-8 9-4.6-.6-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
    "store": '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9h16v2a3 3 0 01-5.3 1.9A3 3 0 0112 14a3 3 0 01-2.7-1.1A3 3 0 014 11V9z"/><path d="M5 14v6h14v-6"/>',
    "card": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
    "megaphone": '<path d="M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1z"/><path d="M15 9a4 4 0 010 6M18 6a8 8 0 010 12"/>',
    "play": '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9l5 3-5 3V9z"/>',
    "users": '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0112 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14a5 5 0 015 5"/>',
    "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    "briefcase": '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2M3 12h18"/>',
    "search": '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
    "file": '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
    "scale": '<path d="M12 4v16M5 20h14M6 8h12"/><path d="M6 8l-3 6a3 3 0 006 0L6 8zM18 8l-3 6a3 3 0 006 0l-3-6z"/>',
    "key": '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
    "chart": '<path d="M4 20V4M4 20h16"/><path d="M8 16l4-5 3 3 5-7"/>',
    "eye": '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    "clock": '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    "alert": '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/>',
    "lock": '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
    "book": '<path d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2V5z"/><path d="M4 19a2 2 0 012-2h13"/>',
    "tool": '<path d="M14 6a4 4 0 00-5 5L3 17l4 4 6-6a4 4 0 005-5l-3 3-3-1-1-3 3-3z"/>',
    "check": '<path d="M5 12l5 5 9-10"/>',
    "x": '<path d="M6 6l12 12M18 6L6 18"/>',
    "arrow": '<path d="M5 12h14M13 6l6 6-6 6"/>',
    "chev": '<path d="M6 9l6 6 6-6"/>',
    "globe": '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
    "compass": '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>',
}


def plogo(ctx: "Ctx", pid: str, cls: str = "pl") -> str:
    """Platform logo tile (decorative: the platform name is always next to it)."""
    return f'<img class="{cls}" src="{ctx.asset("img/logos/" + pid + ".svg")}" alt="" width="20" height="20" loading="lazy">'


def icon(name: str, cls: str = "ic") -> str:
    return (f'<svg class="{cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            f'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            f'{_IC[name]}</svg>')


# ------------------------------------------------------------------ menus
def _cat_platforms(cat: str) -> list[dict]:
    return [p for p in PLATFORMS if p["cat"] == cat]


def menus() -> list[dict]:
    plat_cols = []
    for group in (("social", "email"), ("commerce",), ("payments", "ads"), ("content", "gig")):
        col = []
        for cid in group:
            c = CATEGORIES[cid]
            col.append({"head": c["name"], "href": c["path"], "icon": c["icon"],
                        "items": [(p["name"], p["path"], p["id"]) for p in _cat_platforms(cid)]})
        plat_cols.append(col)
    return [
        {
            "label": "Platforms", "href": "/services/", "kind": "platforms",
            "intro": ("Platforms", "Suspended, deactivated or holding your funds?",
                      "Every case starts with the notice. Choose the platform to see common causes, the official route and what we prepare.",
                      "/services/", "All platforms"),
            "cols": plat_cols,
        },
        {
            "label": "Services", "href": "/services/#what-we-do", "kind": "services",
            "intro": ("Services", "Diagnose. Evidence. Appeal. Protect.",
                      "Independent case preparation for account holders, built on facts a reviewer can verify.",
                      "/how-it-works/", "How it works"),
            "items": [(s["name"], s["path"], s["short"], s["icon"]) for s in SERVICES],
        },
        {
            "label": "Resources", "href": "/blog/", "kind": "resources",
            "intro": ("Resources", "Know your route before you appeal.",
                      "Platform guides, free tools and straight answers.",
                      "/blog/", "All guides"),
            "items": [
                ("Guides and blog", "/blog/", "Suspension, appeal and held-funds guides by platform.", "book"),
                ("Reinstatement Readiness Score", "/tools/reinstatement-readiness-score/", "Ten questions. An honest view of your case strength.", "chart"),
                ("Suspension Notice Decoder", "/tools/suspension-notice-decoder/", "What your notice wording usually means.", "search"),
                ("How it works", "/how-it-works/", "Our four-stage method, step by step.", "compass"),
                ("FAQ", "/faq/", "Pricing, timing, confidentiality and odds.", "file"),
                ("What we will not do", "/ethics/", "The lines we do not cross, and why they protect you.", "shield"),
            ],
        },
    ]


def _mega(ctx: Ctx, m: dict) -> str:
    eyebrow, title, text, href, cta = m["intro"]
    intro = (f'<div class="mega-intro"><p class="eyebrow">{e(eyebrow)}</p>'
             f'<p class="mega-title">{e(title)}</p><p>{e(text)}</p>'
             f'<a class="more" href="{ctx.link(href)}">{e(cta)} {icon("arrow", "ic sm")}</a></div>')
    if m["kind"] == "platforms":
        cols = []
        for col in m["cols"]:
            blocks = []
            for g in col:
                lis = "".join(f'<li><a href="{ctx.link(h)}">{plogo(ctx, pid)}<span>{e(n)}</span></a></li>' for n, h, pid in g["items"])
                blocks.append(f'<p class="mega-head"><a href="{ctx.link(g["href"])}">{icon(g["icon"], "ic xs")}{e(g["head"])}</a></p><ul class="plist">{lis}</ul>')
            cols.append(f'<div class="mega-col">{"".join(blocks)}</div>')
        body = f'<div class="mega-cols c4">{"".join(cols)}</div>'
    else:
        items = "".join(
            f'<a class="mi" href="{ctx.link(h)}"><span class="mi-ic">{icon(ic)}</span>'
            f'<span><strong>{e(n)}</strong><small>{e(d)}</small></span></a>'
            for n, h, d, ic in m["items"])
        body = f'<div class="mega-cols c2 items">{items}</div>'
    return f'<div class="mega"><div class="mega-in wrap">{intro}{body}</div></div>'


def util_bar(ctx: Ctx) -> str:
    return (
        '<div class="util"><div class="wrap util-in">'
        f'<a class="util-alert" href="{ctx.link("/services/priority-case-review/")}"><span class="dot" aria-hidden="true"></span>'
        'Deadline on your notice? Request a priority review</a>'
        '<ul class="util-links">'
        '<li class="muted">Independent. Not affiliated with any platform.</li>'
        f'<li><a href="{ctx.link("/tools/reinstatement-readiness-score/")}">Free Readiness Score</a></li>'
        f'<li><a href="{ctx.link("/pricing/")}">Pricing</a></li>'
        f'<li><a href="{ctx.link("/blog/")}">Blog</a></li>'
        f'<li><a class="client-login" href="{ctx.link("/portal/")}">{icon("lock", "ic xs")}Client login</a></li>'
        '</ul></div></div>'
    )


def logo(ctx: Ctx, light: bool = False) -> str:
    f = "logo-light.svg" if light else "logo.svg"
    return (f'<img src="{ctx.asset("img/" + f)}" alt="{e(C.BRAND)}" width="328" height="36" '
            'class="logo-img">')


def nav_html(ctx: Ctx) -> str:
    ms = menus()
    desk = "".join(
        f'<li class="has-mega"><a class="menu-btn" href="{ctx.link(m["href"])}">{e(m["label"])}'
        f'{icon("chev", "ic xs")}</a>{_mega(ctx, m)}</li>' for m in ms)
    desk += f'<li><a class="menu-btn" href="{ctx.link("/services/held-funds-payouts/")}">{icon("card", "ic sm")}Held funds</a></li>'
    desk += f'<li><a class="menu-btn" href="{ctx.link("/about-us/")}">{icon("users", "ic sm")}About</a></li>'

    # mobile accordions
    mob = []
    for m in ms:
        if m["kind"] == "platforms":
            inner = "".join(
                f'<p class="m-head"><a href="{ctx.link(g["href"])}">{icon(g["icon"], "ic xs")}{e(g["head"])}</a></p><ul>'
                + "".join(f'<li><a href="{ctx.link(h)}">{plogo(ctx, pid)}<span>{e(n)}</span></a></li>' for n, h, pid in g["items"]) + "</ul>"
                for col in m["cols"] for g in col)
        else:
            inner = "<ul>" + "".join(f'<li><a href="{ctx.link(h)}"><span class="m-ic">{icon(ic, "ic sm")}</span><span>{e(n)}</span></a></li>' for n, h, _, ic in m["items"]) + "</ul>"
        mob.append(f'<details class="m-acc"><summary>{e(m["label"])}{icon("chev", "ic xs")}</summary><div class="m-body">{inner}</div></details>')
    mob_html = (
        '<div class="mnav" id="mnav"><div class="mnav-in">' + "".join(mob) +
        '<ul class="m-solo">'
        + "".join(
            f'<li><a{" class=" + chr(34) + cls + chr(34) if cls else ""} href="{ctx.link(h)}"><span class="m-ic">{icon(ic, "ic sm")}</span><span>{e(n)}</span></a></li>'
            for n, h, ic, cls in (
                ("All platforms and services", "/services/", "globe", ""),
                ("Held funds and payouts", "/services/held-funds-payouts/", "card", ""),
                ("Priority case review", "/services/priority-case-review/", "clock", "m-alert"),
                ("Pricing", "/pricing/", "file", ""),
                ("About us", "/about-us/", "users", ""),
                ("Contact us", "/contact-us/", "mail", ""),
                ("Client login", "/portal/", "lock", ""),
                ("Check a case", "/portal/status", "search", ""),
            )) +
        '</ul>'
        '<p class="m-note">Independent case preparation. Not affiliated with any platform.</p>'
        f'<a class="btn btn-light block" href="{ctx.link("/tools/reinstatement-readiness-score/")}">Free Readiness Score</a>'
        f'<a class="btn btn-primary block" href="{ctx.link("/contact-us/")}">Start a confidential case</a>'
        '</div></div>'
    )
    return (
        '<header class="hdr"><div class="wrap hdr-in">'
        f'<a class="brand" href="{ctx.link("/")}">{logo(ctx)}</a>'
        f'<nav class="nav" aria-label="Main"><ul class="menu">{desk}</ul></nav>'
        '<div class="nav-cta">'
        f'<a class="btn btn-light sm" href="{ctx.link("/tools/reinstatement-readiness-score/")}">Readiness Score</a>'
        f'<a class="btn btn-primary sm" href="{ctx.link("/contact-us/")}">Start a case</a>'
        '</div>'
        '<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Open menu">'
        '<label for="nav-toggle" class="burger" aria-hidden="true"><span></span><span></span><span></span></label>'
        f'{mob_html}</div></header>'
    )


def footer_html(ctx: Ctx) -> str:
    def col(title: str, links: list[tuple[str, str]]) -> str:
        lis = "".join(f'<li><a href="{ctx.link(h)}">{e(n)}</a></li>' for n, h in links)
        return f'<div class="f-col"><p class="f-h">{e(title)}</p><ul>{lis}</ul></div>'

    legacy = [p for p in PLATFORMS if p["legacy"]]
    top = sorted(legacy, key=lambda p: p["name"])[:11]
    plat_links = [(p["name"], p["path"]) for p in top] + [("All platforms", "/services/")]
    cat_links = [(c["name"], c["path"]) for c in CATEGORIES.values()]
    svc_links = [(s["name"], s["path"]) for s in SERVICES[:6]]
    co_links = [("About us", "/about-us/"), ("How it works", "/how-it-works/"), ("Pricing", "/pricing/"),
                ("FAQ", "/faq/"), ("What we will not do", "/ethics/"), ("Blog", "/blog/"), ("Contact us", "/contact-us/"),
                ("Client login", "/portal/"), ("Check a case", "/portal/status")]

    contact_bits = []
    if C.email():
        contact_bits.append(f'<li>{e(C.email())}</li>')
    if C.ADDRESS:
        a = C.ADDRESS
        contact_bits.append(f'<li>{e(", ".join(v for v in (a.get("street"), a.get("city"), a.get("region"), a.get("postcode")) if v))}</li>')
    contact_html = f'<ul class="f-contact">{"".join(contact_bits)}</ul>' if contact_bits else ""

    reg = f'<span>{e(C.REGISTRATION_LINE)}</span>' if C.REGISTRATION_LINE else ""
    holder = C.LEGAL_NAME or C.BRAND
    return (
        '<footer class="ftr"><div class="wrap">'
        '<div class="f-notice"><div><strong>Independent, by design.</strong> '
        f'{e(C.BRAND)} is not affiliated with, endorsed by or acting for any platform named on this site. '
        'All trademarks belong to their owners. Nobody outside a platform can reinstate an account for a fee, and we never claim to.</div>'
        f'<a class="btn btn-primary" href="{ctx.link("/contact-us/")}">Start a confidential case</a></div>'
        '<div class="f-grid">'
        f'<div class="f-brand">{logo(ctx, light=True)}<p>{e(C.TAGLINE)}</p>'
        '<p class="f-small">Independent case diagnosis, evidence preparation and appeal support for suspended, deactivated and restricted accounts.</p>'
        f'{contact_html}</div>'
        + col("Top platforms", plat_links)
        + col("Categories", cat_links)
        + col("Services", svc_links)
        + col("Company", co_links)
        + '</div>'
        '<div class="f-tm"><p class="f-h">Trademark and endorsement disclaimer</p>'
        '<p>Platform names, logos and trademarks shown on this site, including Amazon, eBay, Walmart, Etsy, Shopify, PayPal, Stripe, Meta, Facebook, Instagram, Google, YouTube, TikTok, X, Reddit, Discord, Microsoft, Uber, DoorDash, Airbnb and Booking.com, are the property of their respective owners. '
        f'They are used for identification and information purposes only. {e(C.BRAND)} is an independent service and is not a partner of, affiliated with, sponsored by or endorsed by any of these companies. '
        f'Use of a name or logo does not imply any relationship. <a href="{ctx.link("/disclaimer/")}">Read the full disclaimer</a>.</p></div>'
        f'<div class="f-legal"><p>&copy; <span data-year>{C.TODAY.year}</span> {e(holder)}. {reg}</p>'
        '<ul>'
        f'<li><a href="{ctx.link("/portal/")}">Client login</a></li>'
        f'<li><a href="{ctx.link("/privacy-policy/")}">Privacy</a></li>'
        f'<li><a href="{ctx.link("/terms-and-conditions/")}">Terms</a></li>'
        f'<li><a href="{ctx.link("/disclaimer/")}">Disclaimer</a></li>'
        f'<li><a href="{ctx.link("/cookie-policy/")}">Cookies</a></li>'
        f'<li><a href="{ctx.link("/sitemap.xml")}">Sitemap</a></li>'
        '</ul></div></div></footer>'
    )


# ------------------------------------------------------------------ JSON-LD
ORG_ID = C.BASE_URL + "/#organization"
SITE_ID = C.BASE_URL + "/#website"


def org_node() -> dict:
    n: dict = {
        "@type": "Organization", "@id": ORG_ID, "name": C.BRAND, "url": C.BASE_URL + "/",
        "logo": {"@type": "ImageObject", "url": C.BASE_URL + "/assets/img/logo-512.png"},
        "description": "Independent account suspension case preparation: diagnosis, evidence, appeal and held-funds support across marketplaces, payment platforms and social networks.",
        "areaServed": {"@type": "Country", "name": "United States"},
        "knowsAbout": ["Account suspension appeals", "Plan of Action writing", "Held funds release", "Platform policy compliance"],
    }
    if C.LEGAL_NAME:
        n["legalName"] = C.LEGAL_NAME
    if C.email():
        n["email"] = C.email()
    if C.PHONE:
        n["telephone"] = C.PHONE
    if C.ADDRESS:
        a = C.ADDRESS
        n["address"] = {"@type": "PostalAddress", "streetAddress": a.get("street", ""), "addressLocality": a.get("city", ""),
                        "addressRegion": a.get("region", ""), "postalCode": a.get("postcode", ""), "addressCountry": a.get("country", "US")}
    same = [v for v in C.SOCIAL.values() if v]
    if same:
        n["sameAs"] = same
    return n


def site_node() -> dict:
    return {"@type": "WebSite", "@id": SITE_ID, "url": C.BASE_URL + "/", "name": C.BRAND,
            "publisher": {"@id": ORG_ID}, "inLanguage": C.LANG}


def crumbs_node(trail: list[tuple[str, str]]) -> dict:
    return {"@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": i + 1, "name": n, "item": C.BASE_URL + p}
        for i, (n, p) in enumerate(trail)]}


def webpage_node(path: str, title: str, desc: str, typ: str = "WebPage") -> dict:
    return {"@type": typ, "@id": C.BASE_URL + path + "#webpage", "url": C.BASE_URL + path, "name": title,
            "description": desc, "isPartOf": {"@id": SITE_ID}, "about": {"@id": ORG_ID},
            "inLanguage": C.LANG, "dateModified": C.TODAY_ISO}


def faq_node(faqs: list[tuple[str, str]]) -> dict:
    return {"@type": "FAQPage", "mainEntity": [
        {"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in faqs]}


def ld(nodes: list[dict]) -> str:
    graph = {"@context": "https://schema.org", "@graph": [org_node(), site_node()] + nodes}
    return '<script type="application/ld+json">' + json.dumps(graph, ensure_ascii=False).replace("</", "<\\/") + "</script>"


# ------------------------------------------------------------------ breadcrumbs
def breadcrumbs(ctx: Ctx, trail: list[tuple[str, str]]) -> str:
    items = []
    for i, (n, p) in enumerate(trail):
        if i == len(trail) - 1:
            items.append(f'<li aria-current="page">{e(n)}</li>')
        else:
            items.append(f'<li><a href="{ctx.link(p)}">{e(n)}</a></li>')
    return f'<nav class="crumbs" aria-label="Breadcrumb"><ol>{"".join(items)}</ol></nav>'


# ------------------------------------------------------------------ page shell
def page(ctx: Ctx, *, title: str, desc: str, body: str, nodes: list[dict] | None = None,
         noindex: bool = False, body_class: str = "", bare: bool = False) -> str:
    canonical = C.BASE_URL + (ctx.path if ctx.path != "/404.html" else "/")
    robots = "noindex, follow" if noindex else "index, follow, max-image-preview:large"
    og_img = C.BASE_URL + "/assets/img/og.png"
    head = f"""<!doctype html>
<html lang="{C.LANG}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{e(title)}</title>
<meta name="description" content="{e(desc)}">
<meta name="robots" content="{robots}">
<link rel="canonical" href="{canonical}">
<meta name="theme-color" content="#00183F">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{e(C.BRAND)}">
<meta property="og:title" content="{e(title)}">
<meta property="og:description" content="{e(desc)}">
<meta property="og:url" content="{canonical}">
<meta property="og:image" content="{og_img}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{e(title)}">
<meta name="twitter:description" content="{e(desc)}">
<meta name="twitter:image" content="{og_img}">
<link rel="icon" href="{ctx.asset('img/favicon.svg')}" type="image/svg+xml">
<link rel="icon" href="{ctx.asset('img/favicon.png')}" type="image/png" sizes="48x48">
<link rel="apple-touch-icon" href="{ctx.asset('img/apple-touch-icon.png')}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@600;700;800&amp;display=swap">
<link rel="stylesheet" href="{ctx.asset('css/site.css')}">
{ld(nodes or [])}
</head>
"""
    chrome_top = "" if bare else util_bar(ctx) + nav_html(ctx)
    chrome_bottom = "" if bare else footer_html(ctx)
    cls = f' class="{body_class}"' if body_class else ""
    return (head + f'<body{cls}>\n<a class="skip" href="#main">Skip to content</a>\n' + chrome_top
            + f'<main id="main">{body}</main>' + chrome_bottom
            + f'\n<script src="{ctx.asset("js/site.js")}" defer></script>\n</body>\n</html>\n')
