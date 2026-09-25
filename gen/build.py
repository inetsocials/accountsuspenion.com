"""Build accountsuspension.com into dist/ (upload dist/* to Hostinger public_html).

Usage: python3 gen/build.py
"""
from __future__ import annotations

import csv
import json
import os
import re
import shutil
import sys
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit, unquote

sys.path.insert(0, str(Path(__file__).parent))

import config as C  # noqa: E402
from components import (btn, card, cta_band, faq_html, fmt, page_hero, prose, section,  # noqa: E402
                        sources_html, steps, ticks, trust_line)
from content_guides import GUIDES  # noqa: E402
from content_platforms import BY_ID, CATEGORIES, CATEGORY_FAQ, COMMON_FAQ, PLATFORMS  # noqa: E402
from content_services import ETHICS, METHOD, SERVICE_COMMON_FAQ, SERVICES, SVC_BY_ID  # noqa: E402
from templates import (Ctx, crumbs_node, e, faq_node, icon, page, plogo, webpage_node,  # noqa: E402
                       ORG_ID)

ROOT = Path(__file__).resolve().parent.parent
DIST = ROOT / "dist"
STATIC = ROOT / "static"

PAGES: list[dict] = []  # {"path", "priority", "noindex"}


def write(path: str, html: str, priority: float = 0.6, noindex: bool = False, sitemap: bool = True) -> None:
    if path.endswith(".html"):
        out = DIST / path.lstrip("/")
    else:
        out = DIST / path.lstrip("/") / "index.html"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(html, encoding="utf-8")
    if sitemap and not noindex:
        PAGES.append({"path": path, "priority": priority})


HOME = ("Home", "/")


# ====================================================================== testimonials
REQUIRED_T = ("name", "text", "rating", "date", "consent_ref")


def verified_testimonials(platform: str = "", service: str = "") -> list[dict]:
    out = []
    for t in C.TESTIMONIALS:
        if platform and t.get("platform") != platform:
            continue
        if service and t.get("service") != service:
            continue
        out.append(t)
    return out


def testimonials_html(items: list[dict], title: str = "What clients say") -> str:
    """Renders only verified testimonials from config.TESTIMONIALS. Empty list renders nothing."""
    if not items:
        return ""
    avg = sum(float(t["rating"]) for t in C.TESTIMONIALS) / len(C.TESTIMONIALS)
    summary = (f'<p class="sec-intro">Average rating {avg:.1f} out of 5 from {len(C.TESTIMONIALS)} verified client reviews. '
               'Individual results vary and are not a guarantee of any platform decision.</p>') if len(C.TESTIMONIALS) >= 5 else ""
    cards = "".join(
        f'<figure class="tcard"><p class="stars" aria-label="{e(t["rating"])} out of 5">{"&#9733;" * int(round(float(t["rating"])))}</p>'
        f'<blockquote>{e(t["text"])}</blockquote><figcaption><strong>{e(t["name"])}</strong>'
        f'{e(", ".join(x for x in (t.get("role", ""), BY_ID[t["platform"]]["name"] if t.get("platform") in BY_ID else "") if x))}</figcaption></figure>'
        for t in items[:6])
    return section(summary + f'<div class="tgrid">{cards}</div>', title=title, eyebrow="Verified reviews", sid="reviews")


def testimonial_slot(platform: str = "", service: str = "", title: str = "What clients say") -> str:
    """Filled by site.js from /cms.php?feed=testimonials (published, consent-backed entries only). Hidden when empty."""
    attrs = f' data-platform="{e(platform)}"' if platform else (f' data-service="{e(service)}"' if service else ' data-home="1"')
    return (f'<section class="sec alt" data-testimonials{attrs} hidden><div class="wrap"><div class="sec-head"><p class="eyebrow">Verified reviews</p>'
            f'<h2>{e(title)}</h2></div><p class="t-avg" data-t-avg></p><div class="tgrid" data-t-list></div></div></section>')


# ====================================================================== home
def build_home() -> None:
    path = "/"
    ctx = Ctx(path)
    title = "Account Suspension & Reinstatement Services | Independent"
    desc = "Independent account suspension case review, Plan of Action writing, appeal preparation and held-funds support for Amazon, PayPal, Facebook, YouTube and more."

    hero = f"""
<section class="hero"><div class="wrap hero-in">
  <div class="hero-copy">
    <p class="eyebrow light">Account suspension and reinstatement</p>
    <h1>Suspended, deactivated or holding your funds? Start with the notice.</h1>
    <p class="lede">We diagnose why the platform acted, build evidence it can verify and prepare one precise appeal through the official route. Independent, confidential and honest about the odds.</p>
    <div class="ctas">{btn(ctx, "Get your free Readiness Score", "/tools/reinstatement-readiness-score/", "primary")}{btn(ctx, "Start a confidential case", "/contact-us/", "ghost")}</div>
    {trust_line()}
  </div>
  <div class="hero-art" aria-hidden="true">
    <div class="case-card">
      <div class="case-top"><span class="case-label">Your case</span><span class="status"><span class="s1">Deactivated</span><span class="s2">Under review</span></span></div>
      <p class="case-notice">&ldquo;Your selling privileges have been removed&hellip;&rdquo;</p>
      <ol class="case-steps">
        <li><span class="tick">{icon("check", "ic xs")}</span>Notice mapped to the policy cited</li>
        <li><span class="tick">{icon("check", "ic xs")}</span>Root cause identified in the data</li>
        <li><span class="tick">{icon("check", "ic xs")}</span>Evidence pack checked for consistency</li>
        <li><span class="tick">{icon("check", "ic xs")}</span>Appeal submitted through the official route</li>
      </ol>
      <div class="case-bar"><span></span></div>
    </div>
    <div class="funds-card"><span class="f-l">Held balance</span><span class="f-v">Release route identified</span></div>
  </div>
</div></section>"""

    router = [
        ("Seller account deactivated", "Amazon, Walmart, eBay, Etsy and marketplace sellers.", "/ecommerce-reinstatement/", "store"),
        ("Funds or payouts on hold", "PayPal, Stripe, Amazon, Wise, Payoneer and more.", "/services/held-funds-payouts/", "card"),
        ("Social account suspended", "Facebook, Instagram, X, TikTok, Reddit, LinkedIn.", "/social-media-reinstatement/", "users"),
        ("Channel terminated or strikes", "YouTube, Twitch, Vimeo and gaming accounts.", "/content-sharing-reinstatement/", "play"),
        ("Ad or merchant account suspended", "Google Ads, AdSense, Merchant Center, Meta ads.", "/advertising-account-reinstatement/", "megaphone"),
        ("Driver, host or freelancer deactivated", "Uber, Lyft, DoorDash, Airbnb, Upwork, Fiverr.", "/gig-and-freelance-reinstatement/", "briefcase"),
        ("Verification keeps failing", "Documents that do not match are the usual cause.", "/services/identity-verification-support/", "key"),
        ("Appeal already rejected", "Find out what was missing before you try again.", "/services/suspension-case-review/", "alert"),
    ]
    router_html = '<div class="g4 router">' + "".join(
        f'<a class="tile reveal" href="{ctx.link(h)}"><span class="tile-ic">{icon(ic)}</span><strong>{e(t)}</strong><small>{e(d)}</small></a>'
        for t, d, h, ic in router) + "</div>"

    method_html = '<div class="g4 method">' + "".join(
        f'<div class="m-col reveal"><span class="m-n">{m["n"]}</span><h3>{e(m["name"])}</h3><p>{e(m["line"])}</p>'
        + "<ul>" + "".join(f'<li><a href="{ctx.link(s["path"])}">{e(s["name"])}</a></li>' for s in SERVICES if s["stage"] == m["id"]) + "</ul></div>"
        for m in METHOD) + "</div>"

    chips = []
    for cid, c in CATEGORIES.items():
        for p in PLATFORMS:
            if p["cat"] == cid:
                chips.append(f'<li><a href="{ctx.link(p["path"])}">{plogo(ctx, p["id"])}{e(p["name"])}</a></li>')
    chips_html = f'<ul class="chips">{"".join(chips)}</ul>'

    feat = ["case-review", "plan-of-action", "appeal-prep", "held-funds", "ip-complaints", "compliance-audit"]
    svc_html = '<div class="g3">' + "".join(
        card(ctx, SVC_BY_ID[i]["path"], SVC_BY_ID[i]["name"], SVC_BY_ID[i]["short"], ic=SVC_BY_ID[i]["icon"]) for i in feat) + "</div>"

    ethics_html = ('<div class="split"><div><p class="eyebrow">Why independent matters</p><h2>What we will not do protects your case.</h2>'
                   '<p>Most permanent bans we see were made permanent by a shortcut: a second account, an edited invoice, a paid &ldquo;insider&rdquo;. '
                   'We refuse all of them, because they end recoverable cases.</p>'
                   f'{btn(ctx, "Read what we will not do", "/ethics/", "light")}</div>'
                   + ticks([t for t, _ in ETHICS[:5]], "crosses") + "</div>")

    tools_html = ('<div class="g2">'
                  + card(ctx, "/tools/reinstatement-readiness-score/", "Reinstatement Readiness Score",
                         "Ten questions about your notice, evidence and history. A score out of 100 and the gaps to close before you appeal.", "Free tool", "chart")
                  + card(ctx, "/tools/suspension-notice-decoder/", "Suspension Notice Decoder",
                         "Search the wording in your notice to see what it usually means and which route applies.", "Free tool", "search")
                  + "</div>")

    guides_html = '<div class="g3">' + "".join(
        card(ctx, f'/blog/{g["slug"]}/', g["title"], g["answer"][:150].rsplit(" ", 1)[0] + "...", "Guide") for g in GUIDES[:3]) + "</div>"

    home_faq = [
        ("What does AccountSuspension.com do?", "We prepare suspension cases for account holders: diagnosis of the notice, root cause analysis, evidence packs, Plans of Action and appeals, and held-funds release requests. Submissions go through the platform's official route, from your own account."),
        ("Which platforms do you cover?", f"{len(PLATFORMS)} platforms across marketplaces, payments, social media, advertising, content, gig work and email. See the full list on our services page."),
        ("Can you guarantee my account will be reinstated?", "No. Platforms make the decision. We control the quality of the diagnosis and the submission, and we tell you honestly when a case is weak."),
        ("How much does it cost?", "Scope and a fixed fee are confirmed in writing after a preliminary evidence review, before any paid work starts. No hidden fees and no open-ended hourly billing."),
        ("Is what I send you confidential?", "Yes. Case details are used only to prepare your case. We do not publish client names or outcomes without written consent."),
        ("How quickly can you start?", f"We reply in writing {C.REPLY_WINDOW}. Initial analysis is completed {C.REVIEW_WINDOW} once we have your notice and documents. Priority review is available when a deadline is close."),
        ("Can you help get my held funds released?", "Often, yes. Fund release follows its own rules on most platforms, even when the account is not reinstated. We identify the route and prepare the request."),
        ("Do you need my password?", "No, and nobody legitimate will ask for it. You submit through your own account while we guide each step."),
    ]

    body = (hero
            + section(router_html, title="What happened to your account?", eyebrow="Start here", sid="router")
            + section(method_html, title="Four stages. One precise submission.", eyebrow="How we work",
                      intro="Most rejected appeals fail at diagnosis, not writing. Our method puts the root cause first.", cls="alt", sid="method")
            + section(svc_html, title="What we prepare", eyebrow="Services", sid="services")
            + section(chips_html + f'<p class="center">{btn(ctx, "See all platforms", "/services/", "light")}</p>',
                      title="Every platform that pays you or speaks for you", eyebrow="Platforms", cls="alt", sid="platforms")
            + section(ethics_html, sid="ethics")
            + section(tools_html, title="Check your case before you appeal", eyebrow="Free tools", cls="alt", sid="tools")
            + section(guides_html, title="Guides by platform", eyebrow="Blog", sid="guides")
            + (testimonials_html(verified_testimonials()) or testimonial_slot())
            + section(faq_html(home_faq), title="Questions people ask first", eyebrow="FAQ", cls="alt", sid="faq")
            + cta_band(ctx))

    offer = {"@type": "OfferCatalog", "name": "Account reinstatement case preparation", "itemListElement": [
        {"@type": "Offer", "itemOffered": {"@type": "Service", "name": s["name"], "url": C.BASE_URL + s["path"]}} for s in SERVICES]}
    nodes = [webpage_node(path, title, desc), faq_node(home_faq),
             {"@type": "Service", "name": "Account suspension and reinstatement case preparation", "provider": {"@id": ORG_ID},
              "areaServed": "US", "serviceType": "Account reinstatement support", "hasOfferCatalog": offer}]
    write(path, page(ctx, title=title, desc=desc, body=body, nodes=nodes, body_class="home"), 1.0)


# ====================================================================== services hub
def build_services_hub() -> None:
    path = "/services/"
    ctx = Ctx(path)
    title = "Account Reinstatement & Held-Funds Services | All Platforms"
    desc = "All platforms we cover for account suspension, reinstatement and held-funds support: marketplaces, payments, social media, ads, content, gig work and email."
    trail = [HOME, ("Services", path)]

    groups = []
    for cid, c in CATEGORIES.items():
        items = "".join(
            f'<a class="pcard" href="{ctx.link(p["path"])}" data-filter-item data-text="{e((p["name"] + " " + c["name"]).lower())}">'
            f'{plogo(ctx, p["id"])}<strong>{e(p["name"])}</strong><small>{e(p["h1"])}</small></a>'
            for p in PLATFORMS if p["cat"] == cid)
        groups.append(f'<div class="fgroup" data-filter-group><h3><a href="{ctx.link(c["path"])}">{e(c["name"])}</a></h3><div class="pgrid">{items}</div></div>')

    filt = ('<div class="filter"><label for="pf" class="sr">Filter platforms</label>'
            '<input id="pf" type="search" placeholder="Type a platform, for example PayPal" data-filter-target="#plat-list" autocomplete="off">'
            '<p class="count" data-filter-count aria-live="polite"></p></div>')
    svc_cards = '<div class="g3">' + "".join(card(ctx, s["path"], s["name"], s["short"], ic=s["icon"]) for s in SERVICES) + "</div>"

    body = (page_hero(ctx, trail, "All platforms", "Account reinstatement and held-funds services",
                      "Choose your platform for common causes, the official appeal route, the evidence that matters and what we prepare. Or start with a service below.",
                      [("Start a confidential case", "/contact-us/", "primary"), ("Get your Readiness Score", "/tools/reinstatement-readiness-score/", "light")])
            + section(filt + f'<div id="plat-list">{"".join(groups)}</div>', title="Platforms we cover", sid="platforms")
            + section(svc_cards, title="What we do", eyebrow="Services", cls="alt", sid="what-we-do")
            + cta_band(ctx))
    item_list = {"@type": "ItemList", "itemListElement": [
        {"@type": "ListItem", "position": i + 1, "url": C.BASE_URL + p["path"], "name": p["h1"]} for i, p in enumerate(PLATFORMS)]}
    write(path, page(ctx, title=title, desc=desc, body=body,
                     nodes=[webpage_node(path, title, desc, "CollectionPage"), crumbs_node(trail), item_list]), 0.9)


# ====================================================================== category hubs
def build_categories() -> None:
    for cid, c in CATEGORIES.items():
        path = c["path"]
        ctx = Ctx(path)
        trail = [HOME, ("Services", "/services/"), (c["name"], path)]
        plats = [p for p in PLATFORMS if p["cat"] == cid]
        cards = '<div class="g3">' + "".join(card(ctx, p["path"], p["h1"], p["lede"], p["name"], pid=p["id"]) for p in plats) + "</div>"
        guides = [g for g in GUIDES if g["cat"] == cid]
        g_html = ""
        if guides:
            g_html = section('<div class="g3">' + "".join(card(ctx, f'/blog/{g["slug"]}/', g["title"], g["meta_desc"], "Guide") for g in guides) + "</div>",
                             title="Guides", eyebrow="Blog", cls="alt", sid="guides")
        body = (page_hero(ctx, trail, c["name"], c["h1"], c["lede"],
                          [("Start a confidential case", f"/contact-us/?source={cid}", "primary"), ("All platforms", "/services/", "light")])
                + section(cards, title=f"{c['name']}: platforms we cover", sid="platforms")
                + g_html + cta_band(ctx))
        write(path, page(ctx, title=c["meta_title"], desc=c["meta_desc"], body=body,
                         nodes=[webpage_node(path, c["meta_title"], c["meta_desc"], "CollectionPage"), crumbs_node(trail)]), 0.8)


# ====================================================================== platform pages
def build_platform(p: dict) -> None:
    path = p["path"]
    ctx = Ctx(path)
    cat = CATEGORIES[p["cat"]]
    trail = [HOME, ("Services", "/services/"), (cat["name"], cat["path"]), (p["name"], path)]
    name = p["name"]
    faqs = (p["faqs"]
            + [(q.format(name=name), a.format(name=name)) for q, a in CATEGORY_FAQ.get(p["cat"], [])]
            + [(q.format(name=name), fmt(a).replace("{name}", name)) for q, a in COMMON_FAQ])

    notices = "".join(f'<div class="notice-row"><p class="n-q">&ldquo;{e(q)}&rdquo;</p><p class="n-a">{e(a)}</p></div>' for q, a in p["notices"])
    we_do = [
        f"Written diagnosis of your {name} notice and the policy it cites",
        "Root cause statement drawn from your own account data",
        "Evidence gap list and document consistency check",
        f"Appeal or Plan of Action text written for the {name} reviewer",
        "Submission guidance and follow-up reply drafts",
    ]
    if p["funds"]:
        we_do.append("Held-funds classification and release request planning")
    cannot = [
        f"Reinstate the account ourselves: only {name} can make that decision",
        "Supply, edit or source documents, or describe fixes that have not happened",
        "Help you open a new account or get around enforcement",
    ]
    rel = [BY_ID[r] for r in p["related"] if r in BY_ID]
    rel_html = '<div class="g3">' + "".join(card(ctx, r["path"], r["h1"], r["lede"], r["name"], pid=r["id"]) for r in rel) + "</div>"
    guides = [g for g in GUIDES if p["id"] in g["platforms"]]
    guide_html = ""
    if guides:
        guide_html = '<div class="guide-links"><p class="f-h">Related guides</p><ul>' + "".join(
            f'<li><a href="{ctx.link("/blog/" + g["slug"] + "/")}">{e(g["title"])}</a></li>' for g in guides) + "</ul></div>"

    aside = (f'<aside class="side"><div class="side-card"><p class="f-h">At a glance</p><dl>'
             f'<dt>Category</dt><dd><a href="{ctx.link(cat["path"])}">{e(cat["name"])}</a></dd>'
             f'<dt>Accounts covered</dt><dd>{e(p["accounts"])}</dd>'
             f'<dt>Route</dt><dd>Official {e(name)} appeal or review channel, submitted from your account</dd>'
             f'<dt>First response</dt><dd>Case review {e(C.REVIEW_WINDOW)}</dd>'
             f'<dt>Fees</dt><dd>Fixed scope confirmed in writing before work starts</dd></dl>'
             f'{btn(ctx, "Start a " + name + " case", "/contact-us/?source=platform&platform=" + p["id"], "primary", "btn-block")}'
             f'<p class="side-alert">{icon("clock", "ic sm")}<span>Deadline in your notice? <a href="{ctx.link("/services/priority-case-review/")}">Request priority review</a>.</span></p>'
             '</div></aside>')

    blocks = (
        f'<div class="block"><h2 id="causes">Why {e(name)} suspends accounts</h2><p>Common triggers we see in {e(name)} cases:</p>{ticks(p["triggers"])}</div>'
        f'<div class="block"><h2 id="notices">What your {e(name)} notice usually means</h2><div class="notices">{notices}</div>'
        f'<p class="small">Wording varies. Search more phrases in the <a href="{ctx.link("/tools/suspension-notice-decoder/")}">Suspension Notice Decoder</a>.</p></div>'
        f'<div class="block"><h2 id="route">The official route</h2>{steps(p["route"])}</div>'
        f'<div class="block"><h2 id="evidence">Evidence that carries weight</h2>{ticks(p["evidence"])}</div>'
        + (f'<div class="block callout"><h2 id="funds">Held funds</h2><p>{e(p["funds"])}</p>'
           f'<p><a href="{ctx.link("/services/held-funds-payouts/")}">How held-funds support works</a></p></div>' if p["funds"] else "")
        + f'<div class="block"><h2 id="help">What we prepare for your {e(name)} case</h2><div class="cc"><div><h3>You receive</h3>{ticks(we_do)}</div>'
        f'<div><h3>What we cannot do</h3>{ticks(cannot, "crosses")}</div></div></div>'
        f'<div class="block"><h2 id="method">How the case runs</h2><div class="g4 method small-method">'
        + "".join(f'<div class="m-col"><span class="m-n">{m["n"]}</span><h3>{e(m["name"])}</h3><p>{e(m["line"])}</p></div>' for m in METHOD)
        + '</div></div>'
        f'<div class="block"><h2 id="faq">{e(name)} reinstatement FAQs</h2>{faq_html(faqs)}</div>'
        f'<div class="block">{sources_html(p["sources"])}{guide_html}</div>'
    )

    body = (page_hero(ctx, trail, cat["name"], p["h1"], p["lede"],
                      [(f"Start a {name} case", f"/contact-us/?source=platform&platform={p['id']}", "primary"),
                       ("Check your readiness", "/tools/reinstatement-readiness-score/", "light")], pid=p["id"])
            + f'<section class="sec"><div class="wrap layout"><div class="main">{blocks}</div>{aside}</div></section>'
            + (testimonials_html(verified_testimonials(platform=p["id"]), f"{name} clients") or testimonial_slot(platform=p["id"], title=f"{name} clients"))
            + section(rel_html, title="Related platforms", cls="alt", sid="related")
            + cta_band(ctx, title=f"Your {name} case starts with the notice.", source="platform"))

    svc = {"@type": "Service", "@id": C.BASE_URL + path + "#service", "name": p["h1"],
           "serviceType": "Account reinstatement case preparation", "provider": {"@id": ORG_ID},
           "areaServed": "US", "description": p["meta_desc"], "url": C.BASE_URL + path}
    nodes = [webpage_node(path, p["meta_title"], p["meta_desc"]), svc, faq_node(faqs), crumbs_node(trail)]
    write(path, page(ctx, title=p["meta_title"], desc=p["meta_desc"], body=body, nodes=nodes), 0.9 if p["legacy"] else 0.8)


# ====================================================================== service pages
def build_service(s: dict) -> None:
    path = s["path"]
    ctx = Ctx(path)
    stage = next(m for m in METHOD if m["id"] == s["stage"])
    trail = [HOME, ("Services", "/services/"), (s["name"], path)]
    faqs = [(q, fmt(a)) for q, a in s["faqs"]] + [
        (q.replace("{svc}", s["name"].lower() if q.startswith("How is") else s["name"]), a.replace("{svc}", s["name"].lower())) for q, a in SERVICE_COMMON_FAQ]
    rel = [x for x in SERVICES if x["id"] != s["id"]][:3] if s["id"] not in ("held-funds",) else [SVC_BY_ID["case-review"], SVC_BY_ID["verification"], SVC_BY_ID["monitoring"]]
    extra = ""
    if s["id"] == "held-funds":
        fund_plats = [p for p in PLATFORMS if p["funds"]]
        extra = ('<div class="block"><h2 id="platforms">Platforms where we plan fund releases</h2><ul class="chips">'
                 + "".join(f'<li><a href="{ctx.link(p["path"] + "#funds")}">{e(p["name"])}</a></li>' for p in fund_plats) + "</ul></div>")
    aside = (f'<aside class="side"><div class="side-card"><p class="f-h">At a glance</p><dl>'
             f'<dt>Stage</dt><dd>{e(stage["name"])}</dd>'
             f'<dt>First response</dt><dd>{e(C.REVIEW_WINDOW if s["id"] != "priority" else C.PRIORITY_WINDOW)}</dd>'
             f'<dt>Fees</dt><dd>Fixed scope confirmed in writing before work starts</dd></dl>'
             f'{btn(ctx, "Start a confidential case", "/contact-us/?source=service&service=" + s["id"], "primary", "btn-block")}</div></aside>')
    blocks = (
        f'<div class="block"><h2 id="fit">Who it is for</h2><div class="cc"><div><h3>A good fit</h3>{ticks(s["for"])}</div>'
        f'<div><h3>Not a fit</h3>{ticks(s["not_for"], "crosses")}</div></div></div>'
        f'<div class="block"><h2 id="deliver">What you receive</h2>{ticks(s["deliverables"])}</div>'
        f'<div class="block"><h2 id="limits">What we cannot do</h2>{ticks(s["cannot"], "crosses")}</div>'
        f'<div class="block"><h2 id="process">Process</h2>{steps(stage["steps"])}</div>'
        + extra
        + f'<div class="block"><h2 id="faq">FAQs</h2>{faq_html(faqs)}</div>'
    )
    rel_html = '<div class="g3">' + "".join(card(ctx, r["path"], r["name"], r["short"], ic=r["icon"]) for r in rel) + "</div>"
    body = (page_hero(ctx, trail, stage["name"], s["h1"], s["lede"],
                      [("Start a confidential case", f"/contact-us/?source=service&service={s['id']}", "primary"),
                       ("How it works", "/how-it-works/", "light")])
            + f'<section class="sec"><div class="wrap layout"><div class="main">{blocks}</div>{aside}</div></section>'
            + (testimonials_html(verified_testimonials(service=s["id"])) or testimonial_slot(service=s["id"]))
            + section(rel_html, title="Related services", cls="alt", sid="related")
            + cta_band(ctx, source="service"))
    svc = {"@type": "Service", "name": s["name"], "serviceType": s["name"], "provider": {"@id": ORG_ID},
           "areaServed": "US", "description": s["meta_desc"], "url": C.BASE_URL + path}
    write(path, page(ctx, title=s["meta_title"], desc=s["meta_desc"], body=body,
                     nodes=[webpage_node(path, s["meta_title"], s["meta_desc"]), svc, faq_node(faqs), crumbs_node(trail)]), 0.8)


# ====================================================================== blog
def build_blog() -> None:
    path = "/blog/"
    ctx = Ctx(path)
    title = "Account Suspension, Reinstatement & Held-Funds Guides"
    desc = "Platform-specific guides to account suspensions, appeals, Plans of Action and held funds: what the notice means, the official route and the evidence that matters."
    trail = [HOME, ("Blog", path)]
    cards = "".join(
        f'<a class="card reveal" href="{ctx.link("/blog/" + g["slug"] + "/")}" data-filter-item data-text="{e((g["title"] + " " + " ".join(g["platforms"])).lower())}">'
        f'<span class="tag">{e(CATEGORIES[g["cat"]]["name"] if g["cat"] in CATEGORIES else "All platforms")}</span>'
        f'<h3>{e(g["title"])}</h3><p>{e(g["meta_desc"])}</p><span class="more">Read the guide {icon("arrow", "ic sm")}</span></a>'
        for g in GUIDES)
    filt = ('<div class="filter"><label for="gf" class="sr">Filter guides</label>'
            '<input id="gf" type="search" placeholder="Filter guides, for example YouTube" data-filter-target="#guide-list" autocomplete="off">'
            '<p class="count" data-filter-count aria-live="polite"></p></div>')
    body = (page_hero(ctx, trail, "Guides", "Account suspension and reinstatement guides",
                      "Straight answers on what platform notices mean, which route applies and what evidence carries weight. Written for account holders, not for search engines.", trust=False)
            + section(filt + f'<div class="g3" id="guide-list"><div class="fgroup cont" data-filter-group>{cards}</div></div>', sid="guides"))
    blog_node = {"@type": "Blog", "@id": C.BASE_URL + path + "#blog", "name": title, "publisher": {"@id": ORG_ID},
                 "blogPost": [{"@type": "BlogPosting", "headline": g["title"], "url": C.BASE_URL + f'/blog/{g["slug"]}/'} for g in GUIDES]}
    write(path, page(ctx, title=title, desc=desc, body=body, nodes=[webpage_node(path, title, desc, "CollectionPage"), blog_node, crumbs_node(trail)]), 0.8)

    for g in GUIDES:
        gp = f'/blog/{g["slug"]}/'
        gctx = Ctx(gp)
        gtrail = [HOME, ("Blog", path), (g["title"], gp)]
        secs = "".join(f'<h2>{e(h)}</h2>{prose(b)}' for h, b in g["sections"])
        how = ""
        if g.get("steps"):
            how = "<h2>Step by step</h2>" + steps(g["steps"])
        plats = [BY_ID[x] for x in g["platforms"] if x in BY_ID]
        plat_links = "".join(f'<li><a href="{gctx.link(p["path"])}">{e(p["h1"])}</a></li>' for p in plats)
        aside = (f'<aside class="side"><div class="side-card"><p class="f-h">Platforms in this guide</p><ul class="side-list">{plat_links}</ul>'
                 f'{btn(gctx, "Start a confidential case", "/contact-us/?source=guide", "primary", "btn-block")}'
                 f'<p class="small">Updated {C.TODAY_US}</p></div></aside>')
        article = (f'<article class="main prose-wrap"><p class="answer"><strong>Short answer.</strong> {e(g["answer"])}</p>{secs}{how}'
                   f'<h2>FAQs</h2>{faq_html(g["faqs"])}{sources_html(g["sources"])}</article>')
        body = (page_hero(gctx, gtrail, "Guide", g["title"], g["meta_desc"], trust=False)
                + f'<section class="sec"><div class="wrap layout">{article}{aside}</div></section>'
                + cta_band(gctx, source="guide"))
        art = {"@type": "Article", "headline": g["title"], "description": g["meta_desc"], "author": {"@id": ORG_ID},
               "publisher": {"@id": ORG_ID}, "dateModified": C.TODAY_ISO, "mainEntityOfPage": C.BASE_URL + gp,
               "image": C.BASE_URL + "/assets/img/og.png", "inLanguage": C.LANG}
        nodes = [webpage_node(gp, g["meta_title"], g["meta_desc"]), art, faq_node(g["faqs"]), crumbs_node(gtrail)]
        if g.get("steps"):
            nodes.append({"@type": "HowTo", "name": g["title"], "step": [
                {"@type": "HowToStep", "position": i + 1, "name": n, "text": t} for i, (n, t) in enumerate(g["steps"])]})
        write(gp, page(gctx, title=g["meta_title"], desc=g["meta_desc"], body=body, nodes=nodes), 0.7)


# ====================================================================== tools
READINESS_Q = [
    ("q1", 10, "Do you have the full text of the notice, including any linked policy?", "Get the complete notice and every linked policy page.", "/tools/suspension-notice-decoder/"),
    ("q2", 10, "Do you know which specific policy or rule the platform cited?", "Map the notice to the exact policy before writing anything.", "/services/suspension-case-review/"),
    ("q3", 15, "Can you name the root cause in your own operation or activity?", "Identify the root cause from your own data; this is where most appeals fail.", "/services/suspension-case-review/"),
    ("q4", 10, "Have the corrective actions already been completed?", "Complete corrective actions before you describe them.", "/services/plan-of-action-writing/"),
    ("q5", 15, "Do your documents match the account details exactly (names, addresses, dates)?", "Check every document for consistency with the account.", "/services/identity-verification-support/"),
    ("q6", 10, "Is this your first appeal on this decision?", "After a rejection, find what was missing before resubmitting.", "/services/suspension-case-review/"),
    ("q7", 10, "Have you avoided opening any new or secondary accounts since the suspension?", "New accounts are treated as evasion; stop and disclose relationships honestly.", "/ethics/"),
    ("q8", 8, "Is the account free of previous serious violations?", "Address prior history directly in the appeal.", "/services/appeal-preparation/"),
    ("q9", 7, "Is the response window in the notice still open?", "A closing window needs priority handling.", "/services/priority-case-review/"),
    ("q10", 5, "Can you evidence the activity in question (orders, traffic, content context)?", "Gather order, traffic or content records that support your account.", "/services/appeal-preparation/"),
]


def build_tools() -> None:
    # hub
    path = "/tools/"
    ctx = Ctx(path)
    title = "Free Account Suspension Tools | Readiness Score & Decoder"
    desc = "Free tools for suspended account holders: score how ready your appeal is and decode what the wording in your suspension notice usually means."
    trail = [HOME, ("Tools", path)]
    body = (page_hero(ctx, trail, "Free tools", "Free tools for suspended accounts",
                      "Both tools run in your browser. Nothing is stored or sent unless you choose to start a case.", trust=False)
            + section('<div class="g2">'
                      + card(ctx, "/tools/reinstatement-readiness-score/", "Reinstatement Readiness Score", "Ten questions, a score out of 100 and the gaps to close before you appeal.", "Tool", "chart")
                      + card(ctx, "/tools/suspension-notice-decoder/", "Suspension Notice Decoder", "Search the phrases in your notice to see what they usually mean.", "Tool", "search")
                      + "</div>"))
    write(path, page(ctx, title=title, desc=desc, body=body, nodes=[webpage_node(path, title, desc, "CollectionPage"), crumbs_node(trail)]), 0.7)

    # readiness score
    path = "/tools/reinstatement-readiness-score/"
    ctx = Ctx(path)
    title = "Reinstatement Readiness Score | Free Appeal Check"
    desc = "Ten questions about your suspension notice, evidence and account history. Get a readiness score out of 100 and the gaps to close before you appeal."
    trail = [HOME, ("Tools", "/tools/"), ("Readiness Score", path)]
    qs = "".join(
        f'<fieldset class="q" data-w="{w}" data-gap="{e(gap)}" data-link="{ctx.link(link)}"><legend><span class="q-n">{i + 1}</span>{e(q)}</legend>'
        f'<div class="opts three">'
        f'<label><input type="radio" name="{qid}" value="1" required><span>Yes</span></label>'
        f'<label><input type="radio" name="{qid}" value="0.5"><span>Partly or unsure</span></label>'
        f'<label><input type="radio" name="{qid}" value="0"><span>No</span></label></div></fieldset>'
        for i, (qid, w, q, gap, link) in enumerate(READINESS_Q))
    tool = (f'<form id="rs-form" class="tool" novalidate>{qs}<button class="btn btn-primary" type="submit">Calculate my score</button>'
            '<p class="form-status bad" id="rs-error" hidden>Please answer every question.</p></form>'
            '<div id="rs-result" class="result" hidden aria-live="polite">'
            '<div class="gauge-wrap"><svg class="gauge" viewBox="0 0 120 120" aria-hidden="true"><circle class="g-bg" cx="60" cy="60" r="52"/>'
            '<circle class="g-fg" id="rs-arc" cx="60" cy="60" r="52" stroke-dasharray="326.7" stroke-dashoffset="326.7"/></svg>'
            '<p class="g-val"><span id="rs-score">0</span><small>/100</small></p></div>'
            '<div><h2 id="rs-verdict">Your result</h2><p id="rs-text"></p><h3>Gaps to close first</h3><ul class="gaps" id="rs-gaps"></ul>'
            f'<a class="btn btn-primary" id="rs-cta" href="{ctx.link("/contact-us/?source=readiness")}">Get a case review</a></div></div>')
    note = ('<p class="small">This score measures how prepared your case is, not the odds of a platform decision. '
            'Only the platform decides. Nothing you enter here is stored or sent.</p>')
    body = (page_hero(ctx, trail, "Free tool", "Reinstatement Readiness Score",
                      "Answer ten questions honestly. You will get a score out of 100 and the specific gaps that most often sink appeals.", trust=False)
            + section(tool + note, sid="tool"))
    app = {"@type": "WebApplication", "name": "Reinstatement Readiness Score", "applicationCategory": "BusinessApplication",
           "operatingSystem": "Any", "offers": {"@type": "Offer", "price": "0", "priceCurrency": "USD"}, "url": C.BASE_URL + path,
           "provider": {"@id": ORG_ID}}
    write(path, page(ctx, title=title, desc=desc, body=body, nodes=[webpage_node(path, title, desc), app, crumbs_node(trail)]), 0.8)

    # notice decoder
    path = "/tools/suspension-notice-decoder/"
    ctx = Ctx(path)
    title = "Suspension Notice Decoder | What Your Notice Means"
    desc = "Search the wording in your account suspension notice to see what it usually means on Amazon, PayPal, Facebook, YouTube and other platforms, and which route applies."
    trail = [HOME, ("Tools", "/tools/"), ("Notice Decoder", path)]
    groups = []
    for cid, c in CATEGORIES.items():
        rows = []
        for p in PLATFORMS:
            if p["cat"] != cid:
                continue
            for q, a in p["notices"]:
                rows.append(f'<div class="notice-row" data-filter-item data-text="{e((p["name"] + " " + q + " " + a).lower())}">'
                            f'<p class="n-p"><a href="{ctx.link(p["path"])}">{plogo(ctx, p["id"])}{e(p["name"])}</a></p>'
                            f'<p class="n-q">&ldquo;{e(q)}&rdquo;</p><p class="n-a">{e(a)}</p></div>')
        groups.append(f'<div class="fgroup" data-filter-group><h3>{e(c["name"])}</h3><div class="notices">{"".join(rows)}</div></div>')
    filt = ('<div class="filter"><label for="nf" class="sr">Search notice wording</label>'
            '<input id="nf" type="search" placeholder="Type words from your notice, for example limited or deactivated" data-filter-target="#notice-list" autocomplete="off">'
            '<p class="count" data-filter-count aria-live="polite"></p></div>')
    body = (page_hero(ctx, trail, "Free tool", "Suspension Notice Decoder",
                      "Platforms use similar words for very different decisions. Search the wording in your notice to see what it usually means and where to go next.", trust=False)
            + section(filt + f'<div id="notice-list">{"".join(groups)}</div>'
                      + '<p class="small">General guidance only. Your notice, account history and the platform&rsquo;s current policies decide the right route.</p>', sid="decoder")
            + cta_band(ctx, source="decoder"))
    app = {"@type": "WebApplication", "name": "Suspension Notice Decoder", "applicationCategory": "ReferenceApplication",
           "operatingSystem": "Any", "offers": {"@type": "Offer", "price": "0", "priceCurrency": "USD"}, "url": C.BASE_URL + path,
           "provider": {"@id": ORG_ID}}
    write(path, page(ctx, title=title, desc=desc, body=body, nodes=[webpage_node(path, title, desc), app, crumbs_node(trail)]), 0.7)


# ====================================================================== contact and thanks
ISSUES = [("suspended", "Suspended or deactivated"), ("funds", "Funds or payouts held"), ("limited", "Limited or restricted"),
          ("verification", "Verification failing"), ("ip", "IP or authenticity complaint"), ("strikes", "Strikes or warnings"),
          ("rejected", "Appeal already rejected"), ("prevent", "Prevention or audit"), ("other", "Something else")]
HISTORY = [("none", "No appeal yet"), ("one", "One appeal submitted"), ("many", "Several appeals"), ("final", "Told the decision is final")]
URGENCY = [("deadline", "Response deadline within 72 hours"), ("revenue", "Revenue or income has stopped"), ("standard", "Standard timing")]


def radios(name: str, opts, cls: str = "") -> str:
    return f'<div class="opts {cls}">' + "".join(
        f'<label><input type="radio" name="{name}" value="{v}"{" required" if i == 0 else ""}><span>{e(l)}</span></label>'
        for i, (v, l) in enumerate(opts)) + "</div>"


def build_contact() -> None:
    path = "/contact-us/"
    ctx = Ctx(path)
    title = "Contact Us | Start a Confidential Suspension Case"
    desc = "Start a confidential account suspension case. Tell us the platform, what the notice says and your deadline. We reply in writing with a view on your options."
    trail = [HOME, ("Contact us", path)]
    plat_opts = '<option value="">Choose a platform</option>' + "".join(
        f'<optgroup label="{e(c["name"])}">' + "".join(f'<option value="{p["id"]}">{e(p["name"])}</option>' for p in PLATFORMS if p["cat"] == cid) + "</optgroup>"
        for cid, c in CATEGORIES.items()) + '<option value="other">Another platform</option>'
    form = f"""
<form id="intake" class="intake" action="{ctx.link('/api/contact.php')}" method="post" data-thanks="{ctx.link('/thank-you/')}" novalidate>
  <ol class="stepper" aria-hidden="true"><li class="on">Platform</li><li>Issue</li><li>History</li><li>Timing</li><li>Details</li></ol>
  <input type="hidden" name="form_type" value="intake">
  <input type="hidden" name="source" value="contact">
  <input type="hidden" name="score" value="">
  <input type="hidden" name="form_ts" value="">
  <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>

  <fieldset class="step-panel" data-step="1"><legend>Which platform?</legend>
    <label for="platform" class="lbl">Platform</label>
    <select id="platform" name="platform" required>{plat_opts}</select>
    <label for="platform_other" class="lbl">If another platform, which one? <span class="opt">(optional)</span></label>
    <input id="platform_other" name="platform_other" type="text" maxlength="80">
    <div class="step-nav"><button type="button" class="btn btn-primary" data-next>Continue</button></div>
  </fieldset>

  <fieldset class="step-panel" data-step="2"><legend>What happened?</legend>
    {radios("issue", ISSUES)}
    <div class="step-nav"><button type="button" class="btn btn-light" data-prev>Back</button><button type="button" class="btn btn-primary" data-next>Continue</button></div>
  </fieldset>

  <fieldset class="step-panel" data-step="3"><legend>Appeals so far</legend>
    {radios("history", HISTORY, "two")}
    <div class="step-nav"><button type="button" class="btn btn-light" data-prev>Back</button><button type="button" class="btn btn-primary" data-next>Continue</button></div>
  </fieldset>

  <fieldset class="step-panel" data-step="4"><legend>How urgent is it?</legend>
    {radios("urgency", URGENCY)}
    <div class="step-nav"><button type="button" class="btn btn-light" data-prev>Back</button><button type="button" class="btn btn-primary" data-next>Continue</button></div>
  </fieldset>

  <fieldset class="step-panel" data-step="5"><legend>Your details</legend>
    <label for="name" class="lbl">Name</label>
    <input id="name" name="name" type="text" maxlength="100" required autocomplete="name">
    <label for="email" class="lbl">Email for our written reply</label>
    <input id="email" name="email" type="email" maxlength="190" required autocomplete="email">
    <label for="details" class="lbl">What does the notice say, and what has happened since?</label>
    <textarea id="details" name="details" rows="7" maxlength="4000" required placeholder="Paste the notice wording if you can. Do not include passwords or full card numbers."></textarea>
    <label class="consent"><input type="checkbox" name="consent" value="1" required><span>I agree that {e(C.BRAND)} may use these details to review my case, as described in the <a href="{ctx.link('/privacy-policy/')}">privacy policy</a>. I understand {e(C.BRAND)} is independent and cannot guarantee a platform decision.</span></label>
    <div class="step-nav"><button type="button" class="btn btn-light" data-prev>Back</button><button type="submit" class="btn btn-primary">Send my case securely</button></div>
    <p class="form-status" id="intake-status" role="status" aria-live="polite" hidden></p>
  </fieldset>
</form>"""
    aside = (f'<aside class="side"><div class="side-card"><p class="f-h">What happens next</p>{steps(["We read your notice and details", "We reply in writing " + C.REPLY_WINDOW, "If the case is worth pursuing, we confirm scope and a fixed fee in writing", "Nothing is submitted without your approval"])}'
             '<p class="small">Never send passwords, one-time codes or full card numbers. We will never ask for them.</p></div></aside>')
    body = (page_hero(ctx, trail, "Confidential", "Start a confidential case",
                      "Five short steps. Tell us the platform, what the notice says and your deadline. You get a written reply with our honest view.", trust=True)
            + f'<section class="sec"><div class="wrap layout"><div class="main">{form}</div>{aside}</div></section>')
    write(path, page(ctx, title=title, desc=desc, body=body,
                     nodes=[webpage_node(path, title, desc, "ContactPage"), crumbs_node(trail)]), 0.8)

    # thank you
    path = "/thank-you/"
    ctx = Ctx(path)
    title = "Case Received | AccountSuspension.com"
    desc = "Your case has been received. We will review your notice and reply in writing. Keep your case reference for any follow-up messages about this case."
    body = (f'<section class="phero"><div class="wrap narrow"><span class="pill">Received</span><h1>Your case has been received</h1>'
            f'<p class="lede">Your reference is <strong id="case-ref">being generated</strong>. We will reply in writing {e(C.REPLY_WINDOW)}. '
            'Keep this reference for any follow-up.</p>'
            f'<p>You can <a id="status-link" href="{ctx.link("/portal/status")}">check its status at any time</a> with this reference and your email.</p>'
            f'<div class="ctas">{btn(ctx, "Read our guides meanwhile", "/blog/", "light")}{btn(ctx, "Back to home", "/", "ghost-dark")}</div>'
            '<p class="small">Do not submit a new appeal to the platform while we review, unless a deadline in the notice requires it.</p></div></section>')
    write(path, page(ctx, title=title, desc=desc, body=body, noindex=True), noindex=True)


# ====================================================================== about, method, faq, pricing, ethics
def simple_page(path: str, title: str, desc: str, pill: str, h1: str, lede: str, inner: str,
                nodes_extra: list | None = None, priority: float = 0.6, typ: str = "WebPage", cta: bool = True) -> None:
    ctx = Ctx(path)
    trail = [HOME, (h1 if len(h1) < 40 else pill, path)]
    body = page_hero(ctx, trail, pill, h1, lede, trust=False) + inner.replace("{L:", "{L:") + (cta_band(ctx) if cta else "")
    body = re.sub(r"\{L:([^}]+)\}", lambda m: ctx.link(m.group(1)), body)
    write(path, page(ctx, title=title, desc=desc, body=body,
                     nodes=[webpage_node(path, title, desc, typ), crumbs_node(trail)] + (nodes_extra or [])), priority)


def build_company_pages() -> None:
    # about
    about = section(
        '<div class="prose-wrap wide">'
        f'<p>{e(C.BRAND)} is an independent case preparation service for people and businesses whose online accounts have been suspended, deactivated, limited or restricted, or whose funds are held.</p>'
        '<p>We are not a platform, an agency of one, or a law firm. We do not have inside contacts and we do not sell shortcuts. What we offer is disciplined preparation: reading the notice properly, finding the real cause in your data, fixing what can be fixed, and writing one precise submission that a reviewer can verify.</p>'
        '<h2>Why we work this way</h2>'
        '<p>Most appeals fail for predictable reasons: the root cause is wrong, the documents do not match the account, the fixes described have not happened, or a panicked second account turns a recoverable case into evasion. Our method exists to prevent exactly those failures.</p>'
        '<h2>How we protect your information</h2>'
        '<p>Case details are used only to prepare your case. We ask you to submit through your own account, so we never need your password. We do not publish client names, cases or outcomes without written consent.</p>'
        f'<h2>What we will not do</h2><p>We will not help anyone evade a ban, create or alter documents, or claim inside access. Read <a href="{{L:/ethics/}}">the full list</a>.</p>'
        '</div>')
    simple_page("/about-us/", "About Us | Independent Account Suspension Case Preparation",
                "An independent case preparation service for suspended, deactivated and restricted accounts. No inside access claims, no shortcuts, no guaranteed outcomes.",
                "About", "About us", "Independent case preparation for account holders facing suspension, deactivation and held funds.", about, priority=0.6, typ="AboutPage")

    # how it works
    rows = "".join(
        f'<div class="how-row reveal"><div class="how-n">{m["n"]}</div><div><h2>{e(m["name"])}</h2><p>{e(m["line"])}</p>{steps(m["steps"])}</div></div>'
        for m in METHOD)
    need = ticks(["The full notice, including any linked policy pages", "Screenshots of the account status or dashboard",
                  "Relevant records: orders, invoices, traffic data, content, messages", "Any appeals already sent and the replies",
                  "Your deadline, if the notice states one"])
    how = section(f'<div class="how">{rows}</div>') + section(need, title="What we need from you", cls="alt", sid="need")
    simple_page("/how-it-works/", "How It Works | Diagnose, Evidence, Appeal, Protect",
                "Our four-stage method for account suspension cases: diagnose the real cause, build verifiable evidence, submit one precise appeal and protect the account after.",
                "Method", "How it works", "Four stages that put the root cause first, because that is where most appeals fail.", how,
                [{"@type": "HowTo", "name": "How an account suspension case is prepared", "step": [
                    {"@type": "HowToStep", "position": i + 1, "name": m["name"], "text": m["line"]} for i, m in enumerate(METHOD)]}], 0.7)

    # faq
    faq_groups = [
        ("About us", [
            ("Are you affiliated with Amazon, PayPal, Meta, Google or any other platform?", "No. We are independent and have no special access. All submissions go through official routes available to every account holder."),
            ("Are you lawyers?", "No. Where a case needs legal advice, such as litigation, arbitration or a counter-notice with legal risk, we tell you to speak to an attorney."),
            ("Who do you work with?", "Marketplace sellers, merchants, creators, advertisers, drivers, hosts, freelancers and individuals whose accounts have been suspended, restricted or deactivated, or whose funds are held."),
            ("Do you work outside the United States?", "Our content is written for US account holders. We can review cases elsewhere, but platform processes and consumer laws vary by country."),
        ]),
        ("Outcomes and odds", [
            ("Can you guarantee reinstatement?", "No one can honestly guarantee a platform decision. We tell you our honest view of the odds at the review stage."),
            ("What makes a case strong?", "A clear root cause, corrective actions that have already happened, documents that match the account exactly, and a first appeal that has not yet been used."),
            ("Can you help if my appeal was already rejected?", "Often, yes. We find what was missing. We will not resubmit the same content, because repeated identical appeals rarely help."),
            ("What if the decision is final?", "Some platforms mark decisions as final. We check whether any route remains, such as a separate fund release, a verification path or a legal option, and say so plainly if none does."),
        ]),
        ("Fees and timing", [
            ("How much do you charge?", "Scope and a fixed fee are confirmed in writing after a preliminary evidence review and before any paid work starts. No hidden fees and no open-ended hourly billing."),
            ("Do you charge a success fee?", "No. Fees are for the work performed. A platform decision is outside our control, so we do not tie fees to it."),
            ("How fast will I hear back?", f"We reply in writing {C.REPLY_WINDOW}. Initial analysis and a Plan of Action draft are typically completed {C.REVIEW_WINDOW} once we have your documents."),
            ("How long does the platform take to decide?", "Each platform sets its own review times, and they vary with the case type and volume. We tell you what to expect for your platform and how to follow up."),
        ]),
        ("Held funds", [
            ("Can I get my held funds even if the account is not reinstated?", "Frequently. Fund release follows its own rules on most platforms. We identify the route and prepare the request."),
            ("How long can a platform hold my money?", "It depends on the platform agreement and your notice. PayPal, for example, commonly holds funds for up to 180 days after a permanent limitation. We find the date and conditions that apply to you."),
            ("Someone offered to unlock my funds for a fee. Should I pay?", "No. Only the platform can release funds. Fee-first recovery offers and claims of inside contacts are common scams."),
        ]),
        ("Security and confidentiality", [
            ("Do you need my password?", "No. We guide you to submit through your own account. Never share passwords or one-time codes with anyone."),
            ("Do you log in to my account?", "We prefer not to. New logins from unfamiliar locations can trigger further security flags. You stay in control."),
            ("Is my information confidential?", "Yes. It is used only for your case, stored encrypted and not published without written consent. See our privacy policy."),
            ("How do you store what I send?", "Case submissions are encrypted on arrival and stored outside the public website. Staff alert emails contain only a case reference, never your details."),
        ]),
        ("What we will not do", [
            ("Will you help me open a new account?", "No. New accounts during a suspension are treated as evasion on almost every platform and usually end the original case."),
            ("Can you create or fix documents for my appeal?", "No. Every document must be genuine and yours. We check documents for consistency but never create or alter them."),
            ("Do you take every case?", "No. We decline cases involving fraud, child safety, sanctions, violent extremism or scams against consumers, and cases that would require misrepresenting facts."),
        ]),
    ]
    gen_faq = [qa for _, items in faq_groups for qa in items]
    faq_body = "".join(section(faq_html(items), title=g, cls="alt" if i % 2 else "", sid=f"faq-{i + 1}") for i, (g, items) in enumerate(faq_groups))
    simple_page("/faq/", "FAQ | Account Suspension & Reinstatement Questions",
                "Answers on fees, timing, confidentiality, held funds, rejected appeals and what we will and will not do for suspended accounts.",
                "FAQ", "Frequently asked questions", "Straight answers before you start, grouped by topic.", faq_body, [faq_node(gen_faq)], 0.7)

    # pricing
    models = [
        ("Case review", "A fixed fee for a written diagnosis of your notice, root cause and options. Credited toward the next stage if you continue with us.", "search"),
        ("Appeal or Plan of Action package", "A fixed fee for evidence preparation, the written submission and follow-up drafts on the same decision.", "file"),
        ("Held funds support", "A fixed fee for hold classification, evidence and release request drafting.", "card"),
        ("Priority handling", "A fixed supplement where a notice deadline or stopped revenue needs same-day work.", "clock"),
        ("Monitoring and audits", "A monthly or per-audit fee for accounts that need an experienced eye after reinstatement.", "eye"),
    ]
    mh = '<div class="g3">' + "".join(f'<div class="card static reveal"><span class="card-ic">{icon(ic)}</span><h3>{e(n)}</h3><p>{e(d)}</p></div>' for n, d, ic in models) + "</div>"
    principles = ticks(["Full scope and a fixed fee confirmed in writing before any paid work starts",
                        "No hidden fees and no open-ended hourly billing",
                        "No success fees contingent on a platform decision we do not control",
                        "If we think the case is weak, we tell you before you pay for an appeal"])
    simple_page("/pricing/", "Pricing | Fixed-Fee Account Suspension Case Support",
                "How our pricing works: fixed fees confirmed in writing after a preliminary evidence review, no hidden fees, no open-ended hourly billing and no guaranteed outcomes.",
                "Pricing", "Pricing", "Every case is different, so we do not publish a single headline price. Here is exactly how fees are set.",
                section(mh, title="Engagement models") + section(principles, title="Pricing principles", cls="alt", sid="principles"), priority=0.7)

    # ethics
    eh = '<div class="ethics">' + "".join(f'<div class="eth reveal">{icon("x", "ic")}<div><h2>{e(t)}</h2><p>{e(d)}</p></div></div>' for t, d in ETHICS) + "</div>"
    simple_page("/ethics/", "What We Will Not Do | Account Reinstatement Ethics",
                "The lines we do not cross: no ban evasion, no fake or altered documents, no inside-access claims, no guaranteed outcomes and no cases that hide real harm.",
                "Ethics", "What we will not do", "These rules protect your case as much as they protect anyone else. Every one of them exists because the shortcut ends recoverable cases.",
                section(eh), priority=0.6)


# ====================================================================== legal
def build_legal() -> None:
    holder = C.LEGAL_NAME or C.BRAND
    privacy = f"""
<div class="prose-wrap wide">
<p class="small">Last updated {C.TODAY_US}</p>
<h2>Who we are</h2><p>This policy explains how {e(holder)} (&ldquo;we&rdquo;) handles personal information collected through this website.</p>
<h2>What we collect</h2>
<ul class="plain"><li>Information you submit in the case intake form: name, email address, platform, issue, appeal history, urgency and the details you write.</li>
<li>Technical information needed to protect the form from abuse: a one-way hash of your IP address for rate limiting, and the time the form was submitted.</li></ul>
<p>The free tools run entirely in your browser. Nothing you enter into them is sent to us unless you choose to submit the intake form.</p>
<h2>How we use it</h2><p>To review your case, reply to you, prepare your case if you engage us, keep records required for accounting and legal purposes, and protect the site from abuse. We do not sell or share personal information for advertising.</p>
<h2>Where it is stored</h2><p>Intake submissions are stored encrypted on our hosting provider&rsquo;s servers, outside the public website directory. Staff notifications contain only a case reference, not your case details.</p>
<h2>Third parties</h2>
<ul class="plain"><li>Hosting: our website and intake handler are hosted by our web hosting provider.</li>
<li>Fonts: pages load fonts from Google Fonts, which means your browser connects to Google servers and shares your IP address with Google.</li>
<li>Spam protection: if enabled, Cloudflare Turnstile checks that form submissions come from a person.</li></ul>
<h2>Retention</h2><p>Enquiries that do not become cases are deleted after 12 months. Case records are kept for as long as needed for the engagement and our legal obligations.</p>
<h2>Your choices and rights</h2><p>You can ask to access, correct or delete your information by sending a request through the <a href="{{L:/contact-us/}}">case intake form</a> with the subject &ldquo;Privacy request&rdquo;. Depending on where you live, including California and other US states with consumer privacy laws, you may have additional rights. We will not discriminate against you for exercising them.</p>
<h2>Children</h2><p>This site is not directed at children under 13, and we do not knowingly collect their information.</p>
<h2>Changes</h2><p>We will update this page when our practices change and revise the date above.</p>
</div>"""
    simple_page("/privacy-policy/", "Privacy Policy | AccountSuspension.com",
                "How AccountSuspension.com collects, uses, stores and protects the information you send through the case intake form, and the choices you have about it.",
                "Legal", "Privacy policy", "Plain language, no surprises.", section(privacy), priority=0.3, cta=False)

    terms = f"""
<div class="prose-wrap wide">
<p class="small">Last updated {C.TODAY_US}</p>
<h2>Our service</h2><p>{e(holder)} provides independent case preparation services: diagnosis of platform notices, evidence preparation, drafting of appeals and Plans of Action, held-funds release requests and related guidance. We are not affiliated with any platform, and we are not a law firm. Nothing on this website is legal advice.</p>
<h2>No guaranteed outcome</h2><p>Platforms make their own decisions. We do not and cannot guarantee reinstatement, release of funds or any other platform outcome.</p>
<h2>Your responsibilities</h2><ul class="plain"><li>Provide accurate, complete and genuine information and documents.</li><li>Submit appeals through your own account and approve every submission.</li><li>Do not ask us to help evade enforcement, alter documents or misrepresent facts. We will end the engagement if you do.</li></ul>
<h2>Fees</h2><p>Scope and fees are confirmed in writing before paid work starts. Fees are for work performed, not for a platform outcome.</p>
<h2>Website content</h2><p>Guides and tools on this site are general information. Platform policies change and your notice and history decide the right route. Trademarks belong to their owners and are used only to identify the platforms concerned.</p>
<h2>Limitation of liability</h2><p>To the extent permitted by law, we are not liable for platform decisions, indirect or consequential losses, or losses arising from information you did not provide. Our total liability for any engagement is limited to the fees paid for that engagement.</p>
<h2>Governing terms</h2><p>Your written engagement letter sets out the specific terms, including governing law, for any paid work.</p>
</div>"""
    simple_page("/terms-and-conditions/", "Terms and Conditions | AccountSuspension.com",
                "Terms for using AccountSuspension.com and our independent case preparation services: no guaranteed outcomes, your responsibilities, fees and limitations.",
                "Legal", "Terms and conditions", "The basis on which we work.", section(terms), priority=0.3, cta=False)

    disclaimer = f"""
<div class="prose-wrap wide">
<p>{e(C.BRAND)} is an independent service. We are not affiliated with, endorsed by, sponsored by or acting on behalf of Amazon, eBay, Walmart, Etsy, Shopify, PayPal, Stripe, Meta, Google, YouTube, TikTok, X, Reddit, Microsoft, Uber, DoorDash or any other company named on this site. All product names, logos and brands are the property of their respective owners and are used only for identification.</p>
<p>We have no special access to any platform. Any person or company claiming they can reinstate an account or release funds through inside contacts, for a fee, should be treated as a likely scam.</p>
<p>Content on this site is general information, not legal advice. Platform policies change frequently. Always read your notice and the platform&rsquo;s current policies.</p>
</div>"""
    simple_page("/disclaimer/", "Disclaimer | Independent, Not Affiliated with Any Platform",
                "AccountSuspension.com is independent and not affiliated with any platform named on this site. Trademarks belong to their owners. General information only.",
                "Legal", "Disclaimer", "Independent, and clear about it.", section(disclaimer), priority=0.3, cta=False)

    cookies = f"""
<div class="prose-wrap wide">
<p class="small">Last updated {C.TODAY_US}</p>
<p>This website does not set advertising or analytics cookies. Pages load fonts from Google Fonts, which may process your IP address as described in Google&rsquo;s own policies. If spam protection is enabled on the intake form, Cloudflare Turnstile may use technologies necessary to confirm you are a person.</p>
<p>If we add analytics in future, we will update this page and, where required, ask for your consent first.</p>
</div>"""
    simple_page("/cookie-policy/", "Cookie Policy | AccountSuspension.com",
                "AccountSuspension.com does not set advertising or analytics cookies. Details of the third-party services that pages rely on, and what they may process.",
                "Legal", "Cookie policy", "Short, because we set so little.", section(cookies), priority=0.2, cta=False)


def build_theme() -> None:
    """Page shell for CMS posts rendered by public_html/cms.php (absolute links, placeholders)."""
    ctx = Ctx("/blog/%%SLUG%%/", absolute=True)
    html = page(ctx, title="%%TITLE%%", desc="%%DESC%%", body="%%BODY%%", nodes=[])
    out = DIST / "_theme" / "post.html"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(html, encoding="utf-8")


def build_404() -> None:
    ctx = Ctx("/404.html", absolute=True)
    body = (f'<section class="phero"><div class="wrap narrow"><span class="pill">404</span><h1>That page is not here</h1>'
            '<p class="lede">The link may be old or mistyped. Start from the platform list or send us your case directly.</p>'
            f'<div class="ctas">{btn(ctx, "All platforms", "/services/", "primary")}{btn(ctx, "Start a case", "/contact-us/", "light")}</div></div></section>')
    write("/404.html", page(ctx, title="Page Not Found | AccountSuspension.com",
                            desc="The page you requested could not be found. Browse all platforms we cover or start a confidential account suspension case.",
                            body=body, noindex=True), noindex=True, sitemap=False)


# ====================================================================== SEO files
def build_seo_files() -> None:
    urls = "".join(
        f"<url><loc>{C.BASE_URL}{p['path']}</loc><lastmod>{C.TODAY_ISO}</lastmod><priority>{p['priority']:.1f}</priority></url>\n"
        for p in PAGES)
    (DIST / "sitemap.xml").write_text(
        '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + urls + "</urlset>\n", encoding="utf-8")

    bots = ["OAI-SearchBot", "ChatGPT-User", "GPTBot", "PerplexityBot", "Perplexity-User", "ClaudeBot", "Claude-SearchBot",
            "Claude-User", "Google-Extended", "Applebot-Extended", "Bingbot", "DuckAssistBot"]
    robots = "User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /portal/\nDisallow: /cms.php\nDisallow: /_theme/\nDisallow: /thank-you/\n\n"
    robots += "".join(f"User-agent: {b}\nAllow: /\nDisallow: /api/\n\n" for b in bots)
    robots += f"Sitemap: {C.BASE_URL}/sitemap.xml\nSitemap: {C.BASE_URL}/blog-sitemap.xml\n"
    (DIST / "robots.txt").write_text(robots, encoding="utf-8")

    llms = [f"# {C.BRAND}", "",
            f"> Independent account suspension case preparation for US account holders: diagnosis of platform notices, root cause analysis, Plans of Action, appeal preparation and held-funds release requests. Not affiliated with any platform. No guaranteed outcomes. Submissions go through each platform's official route from the client's own account.",
            "", "## Key pages",
            f"- [All platforms and services]({C.BASE_URL}/services/)",
            f"- [How it works]({C.BASE_URL}/how-it-works/)",
            f"- [Pricing]({C.BASE_URL}/pricing/): fixed fees confirmed in writing after preliminary review; no headline prices",
            f"- [What we will not do]({C.BASE_URL}/ethics/)",
            f"- [Start a case]({C.BASE_URL}/contact-us/)", "", "## Platforms"]
    llms += [f"- [{p['h1']}]({C.BASE_URL}{p['path']}): {p['meta_desc']}" for p in PLATFORMS]
    llms += ["", "## Services"] + [f"- [{s['name']}]({C.BASE_URL}{s['path']}): {s['short']}" for s in SERVICES]
    llms += ["", "## Guides"] + [f"- [{g['title']}]({C.BASE_URL}/blog/{g['slug']}/): {g['answer']}" for g in GUIDES]
    (DIST / "llms.txt").write_text("\n".join(llms) + "\n", encoding="utf-8")


def build_htaccess() -> None:
    host = urlsplit(C.BASE_URL).netloc
    host_re = host.replace(".", r"\.")
    redirects = []
    red_file = ROOT / "gen" / "redirects.csv"
    if red_file.exists():
        with red_file.open(newline="", encoding="utf-8") as fh:
            for row in csv.reader(fh):
                if not row or row[0].startswith("#") or len(row) < 2:
                    continue
                old, new = row[0].strip(), row[1].strip()
                if old and new and old != new:
                    redirects.append(f"RewriteRule ^{re.escape(old.strip('/'))}/?$ {new} [R=301,L]")
    csp = ("default-src 'self'; script-src 'self' https://challenges.cloudflare.com; "
           "style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
           "img-src 'self' data:; connect-src 'self' https://challenges.cloudflare.com; "
           "frame-src https://challenges.cloudflare.com; form-action 'self'; base-uri 'self'; "
           "frame-ancestors 'none'; object-src 'none'; upgrade-insecure-requests")
    ht = f"""# accountsuspension.com: generated by gen/build.py on {C.TODAY_ISO}. Edit gen/build.py, not this file.
Options -Indexes -MultiViews
DirectoryIndex index.html index.php
ErrorDocument 404 /404.html

<IfModule mod_rewrite.c>
RewriteEngine On

# 1. HTTPS and canonical host ({host}) in a single hop
RewriteCond %{{HTTPS}} !=on [OR]
RewriteCond %{{HTTP_HOST}} !^{host_re}$ [NC]
RewriteCond %{{HTTP_HOST}} (^|\\.){host_re}$ [NC]
RewriteRule ^(.*)$ https://{host}/$1 [R=301,L]

# 2. Old CMS paths (legacy WordPress)
# /page.html, /page.htm, /page.php and /page/index.html -> /page/ when that page exists
# (index rules only fire on what the visitor typed, never on DirectoryIndex sub-requests, so no loop)
RewriteCond %{{THE_REQUEST}} \\s/+index\\.(html?|php)[\\s?] [NC]
RewriteRule ^index\\.(html?|php)$ / [R=301,L,NC]
RewriteCond %{{THE_REQUEST}} \\s/+(.+/)index\\.(html?|php)[\\s?] [NC]
RewriteRule ^(.+)/index\\.(html?|php)$ /$1/ [R=301,L,NC]
RewriteCond %{{REQUEST_FILENAME}} !-f
RewriteCond %{{DOCUMENT_ROOT}}/$1/index.html -f
RewriteRule ^(.+?)\\.(html?|php)$ /$1/ [R=301,L,NC]
RewriteRule ^home/?$ / [R=301,L]
RewriteRule ^feed/?$ /blog/ [R=301,L]
RewriteRule ^blog/feed/?$ /blog/ [R=301,L]
RewriteRule ^comments/feed/?$ /blog/ [R=301,L]
RewriteRule ^category/.*$ /blog/ [R=301,L]
RewriteRule ^tag/.*$ /blog/ [R=301,L]
RewriteRule ^author/.*$ /about-us/ [R=301,L]
RewriteRule ^blog/page/[0-9]+/?$ /blog/ [R=301,L]
RewriteRule ^(wp-login\\.php|xmlrpc\\.php|wp-admin(/.*)?)$ - [G,L]
RewriteRule ^contact/?$ /contact-us/ [R=301,L]
RewriteRule ^about/?$ /about-us/ [R=301,L]
RewriteRule ^faqs/?$ /faq/ [R=301,L]
RewriteRule ^terms/?$ /terms-and-conditions/ [R=301,L]
RewriteRule ^terms-of-service/?$ /terms-and-conditions/ [R=301,L]
RewriteRule ^privacy/?$ /privacy-policy/ [R=301,L]
RewriteRule ^twitter-x-reinstatement/?$ /twitter-reinstatement/ [R=301,L]
RewriteRule ^x-reinstatement/?$ /twitter-reinstatement/ [R=301,L]

# 3. Category-nested platform URLs from the staging build -> flat canonical URLs
RewriteRule ^[a-z0-9-]+-reinstatement/([a-z0-9-]+-reinstatement)/?$ /$1/ [R=301,L]

# 4. Site-specific redirects from gen/redirects.csv (old WordPress URLs)
{chr(10).join(redirects) if redirects else "# none configured"}

# 5. CMS: blog sitemap, and every URL that is not a real file or folder goes to cms.php
#    (redirects managed in the CRM, CMS blog posts, otherwise the 404 page)
RewriteRule ^blog-sitemap\\.xml$ /cms.php?sitemap=1 [L]
RewriteRule ^_theme/ - [F,L]
RewriteCond %{{REQUEST_FILENAME}} !-f
RewriteCond %{{REQUEST_FILENAME}} !-d
RewriteCond %{{REQUEST_URI}} !^/(portal|api)/
RewriteRule ^ /cms.php [L,QSA]
</IfModule>

<IfModule mod_headers.c>
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"
Header always set Cross-Origin-Opener-Policy "same-origin"
Header always set Content-Security-Policy "{csp}"
<FilesMatch "\\.(css|js|svg|png|jpg|webp|woff2)$">
Header set Cache-Control "public, max-age=2592000"
</FilesMatch>
<FilesMatch "\\.html$">
Header set Cache-Control "public, max-age=600, must-revalidate"
</FilesMatch>
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css application/javascript image/svg+xml application/xml text/plain application/json
</IfModule>

<FilesMatch "^(\\.|composer\\.|package\\.|README|.*\\.(md|csv|py|log|bak|sql)$)">
Require all denied
</FilesMatch>
"""
    (DIST / ".htaccess").write_text(ht, encoding="utf-8")


# ====================================================================== checks
class _P(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.links: list[str] = []
        self.ids: list[str] = []
        self.inline_style = 0
        self.scripts_ext = 0
        self.scripts_bad = 0
        self.ld: list[str] = []
        self._in_ld = False
        self.title = ""
        self._in_title = False
        self.desc = ""
        self.robots = ""

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if "style" in a:
            self.inline_style += 1
        if "id" in a:
            self.ids.append(a["id"])
        for k in ("href", "src", "action"):
            if k in a and a[k] is not None:
                if tag == "link" and a.get("rel") in ("canonical", "preconnect"):
                    continue
                self.links.append(a[k])
        if tag == "script":
            if a.get("type") == "application/ld+json":
                self._in_ld = True
                self.ld.append("")
            elif a.get("src"):
                self.scripts_ext += 1
            else:
                self.scripts_bad += 1
        if tag == "title":
            self._in_title = True
        if tag == "meta" and a.get("name") == "description":
            self.desc = a.get("content", "")
        if tag == "meta" and a.get("name") == "robots":
            self.robots = a.get("content", "")

    def handle_endtag(self, tag):
        if tag == "script":
            self._in_ld = False
        if tag == "title":
            self._in_title = False

    def handle_data(self, data):
        if self._in_ld:
            self.ld[-1] += data
        if self._in_title:
            self.title += data


EMOJI = re.compile("[\U0001F300-\U0001FAFF\U00002600-\U000027BF\U0001F000-\U0001F2FF\U0001F900-\U0001F9FF]")
BANNED = [
    (re.compile(r"\bwe guarantee\b", re.I), "guarantee claim"),
    (re.compile(r"\b100\s?%\s?(success|guaranteed|reinstat)", re.I), "100% claim"),
    (re.compile(r"\bguaranteed (reinstatement|results|approval)\b", re.I), "guarantee claim"),
    (re.compile(r"\b(lorem ipsum|TBD|TODO|FIXME)\b"), "placeholder"),
    (re.compile(r"\{\{|\}\}"), "template leak"),
    (re.compile(r'class="btn(?: [\w-]+)* block(?: [\w-]+)*"'), "button uses layout class 'block' (use btn-block)"),
]


def checks() -> bool:
    errors: list[str] = []
    warns: list[str] = []
    for i, t in enumerate(C.TESTIMONIALS):
        missing = [k for k in REQUIRED_T if not str(t.get(k, "")).strip()]
        if missing:
            errors.append(f"config.TESTIMONIALS[{i}] missing {missing}: every testimonial needs a real client, date and consent record")
        if t.get("platform") and t["platform"] not in BY_ID:
            errors.append(f"config.TESTIMONIALS[{i}] unknown platform {t['platform']!r}")
        if t.get("service") and t["service"] not in SVC_BY_ID:
            errors.append(f"config.TESTIMONIALS[{i}] unknown service {t['service']!r}")
    text_ext = {".html", ".css", ".js", ".txt", ".xml", ".php", ".htaccess", ""}
    for f in DIST.rglob("*"):
        if f.is_dir() or (f.suffix not in text_ext and f.name != ".htaccess"):
            continue
        raw = f.read_bytes()
        rel = f.relative_to(DIST)
        if b"\xe2\x80\x94" in raw or b"\xe2\x80\x93" in raw:
            errors.append(f"{rel}: em or en dash")
        txt = raw.decode("utf-8", "replace")
        if EMOJI.search(txt):
            errors.append(f"{rel}: emoji")

    for f in sorted(DIST.rglob("*.html")):
        rel = f.relative_to(DIST)
        html = f.read_text(encoding="utf-8")
        p = _P()
        p.feed(html)
        if p.inline_style:
            errors.append(f"{rel}: {p.inline_style} inline style attribute(s)")
        if p.scripts_ext != 1 or p.scripts_bad:
            errors.append(f"{rel}: expected exactly one external script and no inline scripts")
        for block in p.ld:
            try:
                json.loads(block)
            except json.JSONDecodeError as ex:
                errors.append(f"{rel}: JSON-LD invalid ({ex})")
        dup = {i for i in p.ids if p.ids.count(i) > 1}
        if dup:
            errors.append(f"{rel}: duplicate ids {sorted(dup)}")
        if not C.email() and re.search(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}", re.sub(r"<script.*?</script>", "", html, flags=re.S)):
            errors.append(f"{rel}: email address in page body")
        visible = re.sub(r"<script.*?</script>", "", html, flags=re.S)
        for rx, label in ([] if rel.parts[0] == "_theme" else BANNED):
            if rx.search(visible):
                errors.append(f"{rel}: {label}: {rx.search(visible).group(0)!r}")
        noindex = "noindex" in p.robots or rel.parts[0] == "_theme"
        if len(p.title) > 62:
            warns.append(f"{rel}: title {len(p.title)} chars")
        if not noindex and not (110 <= len(p.desc) <= 165):
            warns.append(f"{rel}: description {len(p.desc)} chars")
        base = f.parent
        for link in p.links:
            if link.startswith(("http:", "https:", "mailto:", "#", "data:")):
                continue
            if "portal/" in link or link.rstrip("/").endswith("portal") or "cms.php" in link:
                continue  # served by the CRM and cms.php, not part of the static build
            target = unquote(urlsplit(link).path)
            if not target:
                continue
            if rel.as_posix() == "404.html" or rel.parts[0] == "_theme":
                dest = DIST / target.lstrip("/")
            else:
                dest = (base / target).resolve()
            if dest.is_dir():
                dest = dest / "index.html"
            if not dest.exists():
                errors.append(f"{rel}: broken link {link}")

    for w in warns:
        print("WARN ", w)
    for er in errors:
        print("ERROR", er)
    n = len(list(DIST.rglob("*.html")))
    print(f"{n} HTML files, {len(PAGES)} in sitemap, {len(errors)} errors, {len(warns)} warnings")
    if not errors:
        print("All checks passed")
    return not errors


# ====================================================================== main
def main() -> int:
    if DIST.exists():
        shutil.rmtree(DIST)
    shutil.copytree(STATIC, DIST)
    # Web-facing CRM files: the whole of dist/ is public_html. The CRM app itself (crm/ascrm) is uploaded beside it.
    crm = ROOT / "crm"
    shutil.copytree(crm / "portal", DIST / "portal")
    (DIST / "api").mkdir(exist_ok=True)
    for f in ("contact.php", "config.php", ".htaccess"):
        shutil.copy2(crm / "website-api" / f, DIST / "api" / f)
    shutil.copy2(crm / "website-api" / "cms.php", DIST / "cms.php")
    build_home()
    build_services_hub()
    build_categories()
    for p in PLATFORMS:
        build_platform(p)
    for s in SERVICES:
        build_service(s)
    build_blog()
    build_tools()
    build_contact()
    build_company_pages()
    build_legal()
    build_404()
    build_theme()
    build_seo_files()
    build_htaccess()
    ok = checks()
    return 0 if ok else 1


if __name__ == "__main__":
    os.chdir(ROOT)
    sys.exit(main())
