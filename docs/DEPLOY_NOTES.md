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

The dashboard routes `/dashboard/files/documents/{document}` and `/dashboard/files/photos/{photo}` stay on the
dashboard host only.

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
