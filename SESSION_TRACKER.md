# SESSION_TRACKER.md

## Project
Name: accountsuspension.com website rebuild
Client: AccountSuspension.com (DeepAI Services project)
Status: v2 complete: website + AS Case Vault CRM + client portal + CMS; site audit 0 issues; QA matrix 154/154; CRM end-to-end 151/151; awaiting Hostinger deploy on MySQL
Last Updated: 09/25/2026

## Objective
Current goal: Rebuild accountsuspension.com as a static, fast, SEO-preserving site on Hostinger public_html, using the Digital Repu v4 theme and build architecture, without changing any indexed URL.

## Business Context
Summary: Independent account suspension case preparation service (diagnosis, Plan of Action, appeals, held-funds release) for US account holders across marketplaces, payments, social, ads, content, gig and email platforms. Positioned as independent and honest: no guaranteed outcomes, no inside-access claims, no ban evasion.

## Technical Stack
Frontend: Python-generated static HTML, one CSS file, one vanilla JS file, CSS-only mobile nav
Backend: PHP 8.1+ intake (api/contact.php), libsodium encryption at rest
Database: none (encrypted files in as_data/ above public_html)
Hosting: Hostinger public_html (Apache/LiteSpeed .htaccess)
Third-party services: Google Fonts; optional Cloudflare Turnstile

## Requirements
- Keep exact URL structure of the live site (SEO in progress)
- All files in public_html on Hostinger
- Same theme as Digital Repu v4 master prompt (tokens, fonts, mega menus, components)
- No em or en dashes, no emojis, no invented stats, testimonials, clients or prices
- US English, MM/DD/YYYY

## Decisions Made
- Canonical host: https://accountsuspension.com (non-www; matches indexed URLs); www and http 301 in one hop
- Preserved 21 indexed platform slugs plus /, /services/, /blog/, /contact-us/
- Added 21 new platform pages on the same /{platform}-reinstatement/ pattern
- Added 7 category hubs (/content-sharing-reinstatement/ mirrors the staging subdomain structure); nested staging URLs 301 to flat URLs
- Method: Diagnose, Evidence, Appeal, Protect (adapted from R4)
- Contact is written case intake only; email and phone rendered only if set in gen/config.py
- Live-site unverifiable claims ("thousands of clients", "398+ reinstated", testimonials) omitted until evidenced
- Kept live-site commercial claims: fixed pricing confirmed in writing, 24 to 48 hour initial analysis (owner to confirm)
- Ethics page: no ban evasion, no document alteration, no inside access, no guaranteed outcome, no fraud or child-safety cases
- Logo: new SVG wordmark outlined from Plus Jakarta Sans (no client logo supplied)

## v1.1 changes (09/25/2026)
- Platform logos: 34 official marks from Simple Icons (CC0 data, vendored in gen/brand/simple-icons); 7 neutral letter tiles for brands that asked Simple Icons to remove their marks (Amazon, Walmart, Wayfair, Lowe's, LinkedIn, Xbox, Yahoo)
- Logos in mega menu, mobile menu, platform hero, cards, chips, services hub, notice decoder
- Icons on every menu item (category heads, services, resources, solo links, mobile)
- FAQs: platform pages 10 to 13 each (specific + category + common), services 7 each, home 8, FAQ page 22 in 6 groups
- Footer trademark and endorsement disclaimer: names and logos used for identification and information only; not a partner, affiliate, sponsor or endorsee
- Testimonials: DECLINED to fabricate reviews or a 4.9 rating (FTC 16 CFR Part 465). Built verified-only system: config.TESTIMONIALS renders on home, platform and service pages; true average shown at 5+ reviews; build fails without date and consent_ref
- Fix: .pcard display rule overrode [hidden] (services filter); header CTA hidden under 1480 px

## v2 changes (09/25/2026): CRM, portal, CMS, element audit
- Source: DR_CASE_VAULT_v1.1_MASTER_PROMPT.md plus the dr-case-vault-v1.zip code (v1.0) supplied by the user; adapted rather than rebuilt
- Rebranded to AccountSuspension.com / AS Case Vault: ascrm folder, ascv_ cookies, AS- references, US English, MM/DD/YYYY, America/New_York, USD, sales tax instead of VAT (default 0)
- Domain: stages Diagnose, Evidence, Appeal, Protect; intake fields platform, issue, appeal history, urgency (deadline = priority lead); 42 platforms and 9 services mirrored from the website
- Removal tracker became the appeal tracker (submission per platform route); search-ranking tracker replaced by a held-funds tracker (amounts, released, status, expected release, per-currency totals) on staff, portal and data export
- Ethics screening rewritten for account cases (no ban evasion, genuine documents, no fraud/child-safety/sanctions/extremism cases, sanctions and funds, identity)
- Client NDA became an engagement and confidentiality agreement (attorney to review); "never phones" claims removed
- New CMS: blog posts (safe Markdown, published at /blog/slug/ via cms.php and the site theme shell, blog-sitemap.xml), verified testimonials (consent record required, second-person approval, average shown from 5 reviews), redirects with hit counts
- dist/ is now the complete public_html (site + portal + api + cms.php); standalone file-store intake retired
- Website fixes: long buttons could overflow phones (Gmail, Merchant Center pages); 24 px tap targets everywhere; footer contrast; touch-tablet mega menus open on first tap; local file:// preview links work; Client login and Check a case links; thank-you status link
- CRM contrast fixes (green and neutral badges, calendar adjacent-month days)

## v2.1 fixes (09/25/2026)
- Legacy /page.html URLs: 301 to /page/ matched on THE_REQUEST only (first version looped on LiteSpeed); index.html redirects removed
- Reports crashed on MySQL: alias `leading` is a MySQL reserved word; renamed to lead_count
- E2E suite now runs on MySQL/MariaDB too (AS_DB=mysql): 151/151; SQLite 151/151
- Deploy layout confirmed: public_html + ascrm side by side

## Completed Work
- Live site inspection via search index (direct fetch blocked by sandbox egress): URL map reconstructed
- gen/ generator, static/ assets, api/contact.php, server-tools/read_cases.php, qa/qa_matrix.py
- 42 platform pages, 9 service pages, 7 category hubs, 12 guides, 2 tools, company and legal pages, 404, thank-you
- sitemap.xml (85 URLs), robots.txt (AI search bots allowed), llms.txt, .htaccess (HTTPS, host, WP legacy paths, CSP, headers)
- Build checks: 0 errors, 0 warnings. QA: 154/154 at 6 breakpoints including end-to-end encrypted intake

## Pending Work
- Deploy: MySQL on Hostinger, installer, master key offline backup, cron, SMTP, test intake
- Attorney review of the client engagement agreement text (Settings)
- Export WordPress sitemap and add old URLs in CRM > Website > Redirects
- Collect genuine client reviews with written consent; add to gen/config.py TESTIMONIALS; rebuild
- Export WordPress sitemaps and add unmatched URLs (especially blog posts) to gen/redirects.csv, rebuild
- Owner inputs: legal name, registration line, optional contact email, response windows
- Create api/config.php on server with APP_KEY, IP_SALT, NOTIFY_TO, NOTIFY_FROM
- Optional: Turnstile keys; analytics decision (update cookie policy if added)

## Issues and Root Causes
- Live site not fetchable from build sandbox (egress policy): URL list built from search index; blog post slugs unknown
- Live copy includes unverifiable volume claims and testimonials: excluded pending evidence

## Fixes Applied
- Header nav wrapping at 1440 px: nowrap on menu and buttons, secondary CTA hidden under 1320 px
- Burger icon: absolute-positioned bars for a reliable X state

## Deployment Notes
- Upload dist/* (including .htaccess files) into public_html after backing up WordPress
- api/config.php must return 403; as_data/ lives above public_html
- Submit sitemap.xml in Search Console; spot-check legacy URLs return 200

## Security Notes
- Intake: origin check, honeypot, min fill time, hashed-IP rate limit, allow-list validation, sodium secretbox at rest, content-free staff email
- CSP: self, Google Fonts, Turnstile only; no inline scripts or styles
- Secrets referenced only as {{APP_KEY}}, {{IP_SALT}} placeholders

## Open Questions
- sodium_compat fallback from spec v1.1 was not in the supplied v1.0 zip; sodium extension required
- Full list of current blog post URLs
- Legal entity, jurisdiction and registration details
- Real turnaround windows and whether priority handling is offered
- Any consented case studies or testimonials with evidence

## Next Action
- Owner provides WordPress sitemap export and config facts; rebuild; deploy to Hostinger; submit sitemap
