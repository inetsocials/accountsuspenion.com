"""Reusable HTML components."""
from __future__ import annotations

import config as C
from templates import Ctx, breadcrumbs, e, icon

TRUST = ["Independent: not affiliated with any platform", "Confidential from the first message", "Honest about the odds"]


def trust_line() -> str:
    return '<ul class="trust">' + "".join(f'<li>{icon("check", "ic sm")}{e(t)}</li>' for t in TRUST) + "</ul>"


def btn(ctx: Ctx, label: str, href: str, kind: str = "primary", extra: str = "") -> str:
    return f'<a class="btn btn-{kind}{(" " + extra) if extra else ""}" href="{ctx.link(href)}">{e(label)}</a>'


def page_hero(ctx: Ctx, trail, pill: str, h1: str, lede: str, ctas: list[tuple[str, str, str]] | None = None,
              trust: bool = True) -> str:
    cta_html = ""
    if ctas:
        cta_html = '<div class="ctas">' + "".join(btn(ctx, l, h, k) for l, h, k in ctas) + "</div>"
    pill_html = f'<span class="pill">{e(pill)}</span>' if pill else ""
    return (f'<section class="phero"><div class="wrap">{breadcrumbs(ctx, trail)}{pill_html}'
            f'<h1>{e(h1)}</h1><p class="lede">{e(lede)}</p>{cta_html}{trust_line() if trust else ""}</div></section>')


def section(inner: str, *, title: str = "", eyebrow: str = "", intro: str = "", cls: str = "", sid: str = "") -> str:
    head = ""
    if title:
        head = '<div class="sec-head">'
        if eyebrow:
            head += f'<p class="eyebrow">{e(eyebrow)}</p>'
        head += f'<h2>{e(title)}</h2>'
        if intro:
            head += f'<p class="sec-intro">{e(intro)}</p>'
        head += "</div>"
    idattr = f' id="{sid}"' if sid else ""
    return f'<section class="sec {cls}"{idattr}><div class="wrap">{head}{inner}</div></section>'


def card(ctx: Ctx, href: str, title: str, text: str, tag: str = "", ic: str = "", extra_attrs: str = "") -> str:
    tag_html = f'<span class="tag">{e(tag)}</span>' if tag else ""
    ic_html = f'<span class="card-ic">{icon(ic)}</span>' if ic else ""
    return (f'<a class="card reveal" href="{ctx.link(href)}"{extra_attrs}>{ic_html}{tag_html}<h3>{e(title)}</h3>'
            f'<p>{e(text)}</p><span class="more">Read more {icon("arrow", "ic sm")}</span></a>')


def ticks(items, kind: str = "ticks") -> str:
    ic = "check" if kind == "ticks" else "x"
    return f'<ul class="{kind}">' + "".join(f'<li>{icon(ic, "ic sm")}<span>{e(i)}</span></li>' for i in items) + "</ul>"


def steps(items: list[str] | list[tuple[str, str]]) -> str:
    out = []
    for it in items:
        if isinstance(it, tuple):
            out.append(f'<li><strong>{e(it[0])}</strong><span>{e(it[1])}</span></li>')
        else:
            out.append(f'<li><span>{e(it)}</span></li>')
    return '<ol class="steps">' + "".join(out) + "</ol>"


def faq_html(faqs: list[tuple[str, str]]) -> str:
    return '<div class="faq">' + "".join(
        f'<details><summary>{e(q)}</summary><div class="faq-a"><p>{e(a)}</p></div></details>' for q, a in faqs) + "</div>"


def sources_html(sources: list[tuple[str, str]]) -> str:
    if not sources:
        return ""
    lis = "".join(f'<li><a href="{e(u)}" rel="noopener nofollow" target="_blank">{e(n)}</a></li>' for n, u in sources)
    return f'<div class="sources"><p class="f-h">Official sources</p><ul>{lis}</ul></div>'


def cta_band(ctx: Ctx, title: str = "Start with the notice.",
             text: str = "Send us what the platform sent you. We reply in writing with what we see, what we would do, and whether we think the case is worth pursuing.",
             source: str = "") -> str:
    href = "/contact-us/" + (f"?source={source}" if source else "")
    return (f'<section class="cta-band"><div class="wrap cta-in"><div><h2>{e(title)}</h2><p>{e(text)}</p></div>'
            f'<div class="ctas">{btn(ctx, "Start a confidential case", href, "primary")}'
            f'{btn(ctx, "Get your Readiness Score", "/tools/reinstatement-readiness-score/", "ghost")}</div></div></section>')


def prose(blocks) -> str:
    out = []
    for b in blocks:
        if isinstance(b, list):
            out.append(ticks(b))
        else:
            out.append(f"<p>{e(b)}</p>")
    return "".join(out)


def fmt(s: str) -> str:
    return s.replace("{review}", C.REVIEW_WINDOW).replace("{reply}", C.REPLY_WINDOW).replace("{priority}", C.PRIORITY_WINDOW)
