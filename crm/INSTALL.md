# AS Case Vault: installation on Hostinger

AS Case Vault is the private CRM, client portal and website CMS for accountsuspension.com. It runs on the same Hostinger plan as the website: PHP 8.1 or newer (8.2+ recommended) with the **sodium** extension, and a MySQL database. No Composer, no Node and no build step on the server.

It is adapted from DR Case Vault v1.0 (spec v1.1) for account suspension cases: Diagnose, Evidence, Appeal, Protect stages; an appeal tracker; a held-funds tracker; US dates and USD; and a CMS for blog posts, verified testimonials and redirects.

## What goes where

| From the package | Upload to | Web-facing |
|---|---|---|
| `dist/*` (website, `portal/`, `api/`, `cms.php`) | `public_html/` | Yes |
| `crm/ascrm/` | `domains/accountsuspension.com/ascrm` (next to `public_html`, never inside it) | No |

`dist/` is the complete `public_html`: the static website, the portal front controller (`portal/`), the website intake (`api/contact.php`) and the public CMS endpoint (`cms.php`). The application itself, with its keys, database and encrypted files, lives in `ascrm/` outside the web root.

**Extract the zip into a scratch folder first, then move folders.** Never extract over the live `public_html`.

## Steps

1. **Back up** the current WordPress site (files and database) from hPanel.
2. **Check PHP.** hPanel > Advanced > PHP Configuration: PHP 8.2 or 8.3, and tick the `sodium` extension (usually on by default).
3. **Create the database.** hPanel > Databases > MySQL Databases. Note the database name, user and password.
4. **Upload.** Move the WordPress files out of `public_html` (keep your backup), then upload the contents of `dist/`, including the hidden `.htaccess` files, into `public_html`. Upload `crm/ascrm` to `domains/accountsuspension.com/ascrm`. In File Manager set `ascrm/config`, `ascrm/keys` and `ascrm/storage` to permission 700.
5. **Run the installer.** Open `https://accountsuspension.com/portal/`. You are sent to the installer. It creates `ascrm/config/setup.key`; open that file in File Manager and paste its contents into the form. Enter the portal address (`https://accountsuspension.com/portal`), the MySQL details and the master admin account.
6. **Back up the master key now.** Download `ascrm/keys/master.key` and keep it offline (password manager or encrypted USB). Without it, encrypted client data and backups cannot be recovered.
7. **Sign in** and connect an authenticator app (compulsory for every account). Save the ten recovery codes.
8. **Settings** (master admin): trading and legal name, From address on your domain (for example `no-reply@accountsuspension.com`), lead alert addresses, SMTP (`smtp.hostinger.com`, port 587, STARTTLS), response windows, Tax ID and bank or ACH details for invoices. Have your attorney review the **client engagement agreement** text before inviting clients. Use "Send test email".
9. **Scheduler.** hPanel > Advanced > Cron Jobs, every 15 minutes:
   `php /home/USER/domains/accountsuspension.com/ascrm/cli/cron.php`
   The Security page also shows a private cron URL for an external scheduler.
10. **Test the website hookup.** Submit a case on `/contact-us/`. It appears in Leads within seconds and the enquirer sees an `AS-` reference on the thank-you page. Then check `https://accountsuspension.com/api/config.php` returns 403 and `https://accountsuspension.com/_theme/post.html` returns 403.
11. **Redirect old WordPress URLs.** Website > Redirects in the portal: add every old URL that is not rebuilt with the same path (export them from your WordPress sitemaps before switching). They apply immediately.
12. **Search Console.** Submit `https://accountsuspension.com/sitemap.xml` and `https://accountsuspension.com/blog-sitemap.xml`.

## How the website connects

- `api/contact.php` loads the CRM from `../../ascrm` (override in `api/config.php`), validates the five-step form (platform, issue, appeal history, urgency, details) against the CRM's own vocabularies, and creates an encrypted lead. A "response deadline within 72 hours" request becomes a priority lead. Allowed origins, rate limit and an optional Cloudflare Turnstile secret are in `ascrm/config/config.php` under `intake`.
- `cms.php` serves published blog posts at `/blog/{slug}/` inside the website theme, applies redirects, and provides small JSON feeds for the blog listing and verified testimonials. `.htaccess` sends every URL that is not a real file or folder to it, so static pages always win.
- The website header, mobile menu and footer link to **Client login** (`/portal/`) and **Check a case** (`/portal/status`).

## Website CMS

| Area | Who | What |
|---|---|---|
| Blog posts | Case Lead and above | Write, publish and update posts. Simple formatting (headings, lists, links, bold). Published posts appear at `/blog/slug/`, in the blog listing and in `blog-sitemap.xml`. |
| Testimonials | Case Lead adds, Admin approves | Genuine client words only, with a consent record. A different admin approves before publishing (a master can self-approve when working alone). The website shows an average rating once five or more are published. |
| Redirects | Admin | Permanent redirects from old URLs, with hit counts. |

Fake or invented reviews are prohibited by the FTC rule on consumer reviews and testimonials (16 CFR Part 465, in force since October 2024), with civil penalties per violation. The CMS enforces a consent record and a second approver.

## Security model in one paragraph

A 256-bit master key lives outside the web root. Each client gets its own random data key, stored only in wrapped (encrypted) form. Messages, notes, appeal identifiers and outcomes, fund references, folder names and file names are sealed with that client key (XSalsa20-Poly1305). Files are encrypted chunk by chunk as they arrive (XChaCha20-Poly1305 secretstream) and stored under 48-character random names inside a client folder with a 64-character random name; plaintext is never written to disk. Website intakes, authenticator secrets and the SMTP password are sealed with purpose-specific subkeys. Every account uses a password (Argon2id) plus a compulsory authenticator code. Sessions are server-side, rotate at sign-in, expire after 30 idle minutes, and sensitive actions ask for the password and a fresh code again. Emails never contain case content. Every view, download, change and denied attempt is written to the audit log.

## Roles

| Role | Can |
|---|---|
| Master Admin | Everything, plus settings, key rotation, backups, retention, maintenance mode and client erasure |
| Admin | All cases and clients, users (except admins), payments, audit log, catalog, testimonial approval, redirects |
| Case Lead | Screen and accept leads, open clients and cases, invoices, reports, blog posts, testimonial drafts; sees own and assigned cases |
| Staff | Work on assigned cases only |
| Client | Own cases: messages, documents, appeal tracker, held funds, invoices, engagement agreement, data export |
| Adviser | Same as client for every client file they are linked to (attorney, accountant, agency) |

## Backups and key rotation

- Security > Create backup writes an encrypted database backup (`storage/backups`). Download it and store it with the master key. Also copy `ascrm/storage/vault` (already encrypted).
- Restore into a fresh database: `php ascrm/cli/tool.php backup:restore FILE [KEYFILE]`.
- Security > Rotate master key re-wraps every client key in one transaction; files are not re-encrypted. The previous key is kept as `master.key.prev-DATE` for restoring older backups. Move it offline.

## Command line

```
php ascrm/cli/tool.php selftest
php ascrm/cli/tool.php backup:create
php ascrm/cli/tool.php user:unlock EMAIL
php ascrm/cli/tool.php user:reset-2fa EMAIL
php ascrm/cli/tool.php key:rotate
php ascrm/cli/tool.php migrate        # after an update: creates any new tables
```

## Limits and notes

- The sodium PHP extension is required. The DR v1.1 spec mentions a bundled `sodium_compat` fallback, but it was not part of the supplied v1.0 package; Hostinger PHP 8.2+ includes sodium.
- Upload limit 100 MB per file by default (Settings, up to 500 MB). Uploads are sent in 4 MB pieces, so PHP `post_max_size` only needs to exceed 4 MB (`portal/.user.ini` sets 16 MB).
- Hostinger shared hosting has no antivirus for uploads; files are type-checked by content and served to browsers only as downloads or in a sandbox, never as web pages. Staff should open client files on patched machines.
- Payments are recorded manually. No card data is handled.
