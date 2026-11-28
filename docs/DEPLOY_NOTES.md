# Deploy notes (Hostinger)

Facts and steps every deploy of core must respect. The numbered deploy scripts (handover section 3) read this file;
anything here that a script does not check yet is done by hand and ticked in the deploy log.

## 1. Uploads: nothing uploaded may ever execute (PLAN 2.2.15, Michael 2026-11-18)

The archive leaves `storage/` out (handover 3.1), so the rules below live on the server only and must be re-checked
after every deploy.

1. Core's web root is `core/public`. `storage/` is outside it; documents and draft photos are on the private disk
   (`storage/app/private`) and leave only through PHP routes that check the owner or staff.
2. After the copy and the caches step, run:
   ```
   php artisan storage:link          # once per server; creates public/storage -> storage/app/public
   php artisan uploads:harden        # writes the three .htaccess files below
   php artisan uploads:harden --check
   ```
   The check must print `OK` on all three lines and exit 0. `MISSING` or `CHANGED` means STOP.
3. What the files do:
   - `storage/app/public/.htaccess` (reached as `/storage/...`): everything denied except our own file names
     (40 hex characters + `.webp`), which are forced to `image/webp` with `nosniff` and a sandbox CSP; PHP and other
     script handlers removed. A stray `x.php` there can be neither run nor downloaded.
   - `storage/app/private/.htaccess` and `storage/.htaccess`: deny all (a second lock; they are outside the web root).
4. Live probe after the deploy, from outside, without the key (under two requests a second):
   - `GET https://<core host>/storage/does-not-exist.php` must answer 403 (denied), never 200 or a PHP error.
   - `GET https://<core host>/storage/.htaccess` must answer 403.
5. Never use the hosting panel's file manager to upload into `storage/`.

## 2. PHP settings in hPanel for the core site

- `upload_max_filesize` at least `12M` and `post_max_size` at least `16M` (files are capped at 10 MB by the
  `upload_max_mb` setting; the margin is for the form fields and multipart overhead).
- `memory_limit` at least `256M` (handover 2.4). The image limits (`image_max_mp` 25, `image_max_side_px` 8000) and the
  PDF caps (16 MB per object stream, 32 MB per file) are sized for this.
- `max_execution_time` at least `120`.
- Extensions needed: `gd` with WebP, `fileinfo`, `zlib`, `openssl` (PDF decryption for the check), `mbstring`.

## 3. API allow-list in `core/public/.htaccess` (handover 2.4)

New `/api/v1` routes in phase 4 (all behind the server key and a signed-in user):

```
GET    /api/v1/me/units
POST   /api/v1/me/units
GET    /api/v1/me/units/{unit}
DELETE /api/v1/me/units/{unit}
PUT    /api/v1/me/units/{unit}/steps/{step}
POST   /api/v1/me/units/{unit}/submit
POST   /api/v1/me/units/{unit}/documents
GET    /api/v1/me/units/{unit}/documents/{document}
DELETE /api/v1/me/units/{unit}/documents/{document}
POST   /api/v1/me/units/{unit}/photos
PUT    /api/v1/me/units/{unit}/photos
GET    /api/v1/me/units/{unit}/photos/{photo}
DELETE /api/v1/me/units/{unit}/photos/{photo}
```

Added later in phase 4:

```
GET    /api/v1/me/units/{unit}/exit-report
PUT    /api/v1/me/units/{unit}/live
POST   /api/v1/me/units/{unit}/withdraw
```

Added in phase 5 (listings):

```
GET    /api/v1/compound-pages
GET    /api/v1/compound-pages/{slug}
POST   /api/v1/contact
POST   /api/v1/partnerships
GET    /api/v1/me/requests
POST   /api/v1/me/requests
```

Added in phase 6 (tools; behind the server key, no sign-in; the two calculator routes are limited per address):

```
GET    /api/v1/market-values
GET    /api/v1/indicators
POST   /api/v1/calculator/seller
POST   /api/v1/calculator/buyer
```

Phase 6 also adds two migrations (`2026_11_25_100000_create_tool_tables`, `2026_11_25_100100_add_unit_transfer_fee_fixed`)
and seeds the market values with the client's answers of 2026-11-25 (`db:seed --class=ReferenceSeeder`, add-only).
On a database seeded before then, the calculator settings rows already exist and are not overwritten: set them in
the dashboard (Calculator settings) or remove those rows before seeding. Nothing is deployed yet, so a first deploy
needs nothing extra.

The texts written on 2026-11-26 (K1-K7) are in the seed data with their state (ours or awaiting legal review), and the
deep-loss line (25%) and the deposit range (8% to 10%) are setting defaults. A database seeded before then keeps its
old texts and settings rows: a first deploy needs nothing extra; any earlier copy needs those rows removed before
seeding (`site_texts`, and the settings `deep_loss_ratio_bp`, `maintenance_deposit_min_bp`,
`maintenance_deposit_max_bp`). The `about.body` text is no longer used.

The dashboard routes `/dashboard/files/documents/{document}` and `/dashboard/files/photos/{photo}` stay on the
dashboard host only.

Part 7.1 (staff, 2026-11-27) adds the migration `2026_11_27_100000_create_admin_password_links_table` and the dashboard
routes `/dashboard/staff`, `/dashboard/account` and `/dashboard/password/{token}` (dashboard host only, like every
dashboard route). Staff invite and password-link e-mails carry an absolute link built from core's `APP_URL`, so
`APP_URL` must be the dashboard's public address and mail must work before the first invite. The first admin is still
created with `php artisan admin:create`.

Part 7.2 (units review, 2026-11-28) adds no migration. New dashboard routes (dashboard host only): `/dashboard/units`,
`/dashboard/units/{unit}` and its actions (request-changes, reject, publish, publish-verified, verified, deal-tag,
fields, places, documents/{document}, photos/{photo}, withdrawal), and `/dashboard/duplicates`. New seller e-mail
templates: `seller.unit_changes_requested`, `seller.unit_rejected`, `seller.unit_published` (carries the exit report
after review as a PDF attachment), `seller.unit_verified`, `seller.withdraw_declined`; their links are built from
core's `FRONTEND_URL` setting (`app.frontend_url`), so it must be the public site's address. Publishing writes two PDFs
per unit under `storage/app/private/exit-reports/` (mPDF, PHP memory as in section 2).

**`APP_URL` in core's `.env` must be core's public address** (the host the browser reaches): published photo URLs
are built from it (`{APP_URL}/storage/units/…`). A wrong value gives every card a broken image (seen locally
2026-11-22). After a change: `php artisan config:cache`.

## 4. Exit report PDF (mPDF)

- `vendor/mpdf` adds about 94 MB to the archive (mPDF ships its own fonts). Our report uses only
  `core/resources/fonts/Alexandria-{Regular,Bold}.ttf`; trimming mPDF's fonts is possible later but not needed.
- mPDF caches font tables in `storage/app/mpdf/` (created on first use, outside the web root, never served).
  **After changing a font file, delete `storage/app/mpdf/ttfontdata/`**: the cache does not notice a changed font
  and keeps failing or using the old one (seen 2026-11-18).
- The Next app must forward uploads of up to 12 MB: the route handlers under `web/src/app/api/seller/` are outside
  the next-intl proxy, so Next's proxy body limit does not apply to them.

## 5. Scheduled jobs (one cron line, handover 2.5)

- `ids:purge` daily at 04:00 Cairo (PLAN 2.2.8): deletes national ID copies past their retention period. Before the
  first live run, check what it would do: `php artisan ids:purge --dry-run`.

## 6. Production settings and the visitor's address (audit 2026-11-22, M1 M4 H1)

Core `.env` on the server (each line is checked by hand in the deploy log):

```
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true     # the default in production since the audit; keep it explicit
```

hPanel PHP settings for core: `expose_php` **Off** (core also removes `X-Powered-By` itself).

The Next server reads the visitor's address from the right of `X-Forwarded-For`: the entry the host's proxy
appended (`web/src/lib/clientIp.ts`). `TRUSTED_PROXY_HOPS` in the Next environment is the number of proxies in front
of Next: `1` (the default) for Hostinger's web server alone; `2` if a CDN is put in front of it. A wrong value is
not silent: too low lets a visitor choose their own address again, too high counts every visitor as one.

Live probe after the first deploy and after any change of proxy or CDN:

1. `curl -sI https://loktaeg.com/` and `curl -sI https://<core host>/dashboard/login`: both show
   `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` and
   `Strict-Transport-Security: max-age=31536000`; neither shows `X-Powered-By`. The core answer's session cookie
   carries `secure`.
2. Address check: send the contact form six times within an hour with `X-Forwarded-For: 198.51.100.1` and so on (a
   different made-up address each time). The sixth must be refused ("محاولات كتير"). If all six pass, the proxy
   layout is not what `TRUSTED_PROXY_HOPS` says: STOP and fix it before launch. Delete the six test messages after.

### Before the first deploy: confirm the proxy header for both apps (second security pass, Michael 2026-11-24)

Checklist, filled in the deploy log before anything goes live:

- [ ] **Public site (Next).** From hPanel or Hostinger support, write down: is there a proxy or CDN (Hostinger CDN,
  Cloudflare) in front of the Node app, how many, and which header carries the visitor's address
  (`X-Forwarded-For` appended, or another one such as `X-Real-IP`). Set `TRUSTED_PROXY_HOPS` to that count. If the
  address does not arrive in `X-Forwarded-For`, STOP: `clientIp.ts` reads only that header.
- [ ] **Core (API and dashboard).** The same questions for the core site. Core reads the address from the connection
  (`$request->ip()`, used by the staff sign-in limit of five tries a minute per e-mail and address) and has no
  trusted proxy set. With no proxy in front of core this is the visitor; with one, every visitor would share the
  proxy's address. If there is a proxy in front of core, STOP and ask Michael: core then needs its trusted proxy set
  (a small code change), and HTTPS detection for the secure cookie depends on the same setting.
- [ ] **Probe for core** (after deploy, with a test staff account, then delete it): five wrong dashboard sign-ins from
  one network (a phone hotspot), then the right password for the same account from another network (office Wi-Fi).
  It must sign in. If it is refused as "too many attempts", core sees the proxy, not the visitor: STOP as above.
  The account-wide lock (20 failures an hour, settings) counts by e-mail and is not affected by the address.
- [ ] Record the answers (proxy yes or no, header, hop count) in this file under this heading.
