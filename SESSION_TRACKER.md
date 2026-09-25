# SESSION_TRACKER.md

## Project
Name: accountsuspension.com website rebuild
Client: AccountSuspension.com (DeepAI Services project)
Status: v1 rebuild complete; 87 pages; build checks and 154/154 Playwright QA pass; awaiting WordPress URL export and Hostinger deploy
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

## Completed Work
- Live site inspection via search index (direct fetch blocked by sandbox egress): URL map reconstructed
- gen/ generator, static/ assets, api/contact.php, server-tools/read_cases.php, qa/qa_matrix.py
- 42 platform pages, 9 service pages, 7 category hubs, 12 guides, 2 tools, company and legal pages, 404, thank-you
- sitemap.xml (85 URLs), robots.txt (AI search bots allowed), llms.txt, .htaccess (HTTPS, host, WP legacy paths, CSP, headers)
- Build checks: 0 errors, 0 warnings. QA: 154/154 at 6 breakpoints including end-to-end encrypted intake

## Pending Work
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
- Full list of current blog post URLs
- Legal entity, jurisdiction and registration details
- Real turnaround windows and whether priority handling is offered
- Any consented case studies or testimonials with evidence

## Next Action
- Owner provides WordPress sitemap export and config facts; rebuild; deploy to Hostinger; submit sitemap
