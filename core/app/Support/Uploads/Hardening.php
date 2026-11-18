<?php

namespace App\Support\Uploads;

/**
 * Server rules that make uploaded files inert (Michael 2026-11-18, PLAN 2.2.15, docs/DEPLOY_NOTES.md).
 *
 * The deploy archive leaves storage/ out (handover 3.1), so these files are written on the server by
 * "php artisan uploads:harden" and checked by "php artisan uploads:harden --check".
 *
 * - storage/app/public (reached through public/storage): everything is denied except our own file names,
 *   40 hex characters + ".webp"; those are forced to image/webp with nosniff and a sandbox CSP. No script handler can
 *   run there, so even a file that slipped in under another name could neither execute nor be fetched.
 * - storage/app/private and storage/: denied outright (documents and draft photos leave only through PHP routes
 *   that check the owner or staff). They sit outside the web root anyway; this is the second lock.
 */
final class Hardening
{
    public const PUBLIC = <<<'HTACCESS'
# Lokta: written by "php artisan uploads:harden" (PLAN 2.2.15). Uploaded files are data, never code.
Options -Indexes -ExecCGI -Includes
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht .phps .cgi .pl .py .sh .shtml
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht .phps
Require all denied
<FilesMatch "^[a-f0-9]{40}\.webp$">
    Require all granted
    <IfModule mod_mime.c>
        ForceType image/webp
    </IfModule>
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set Content-Security-Policy "default-src 'none'; sandbox"
        Header always set Cache-Control "public, max-age=31536000, immutable"
    </IfModule>
</FilesMatch>

HTACCESS;

    public const DENY = <<<'HTACCESS'
# Lokta: written by "php artisan uploads:harden" (PLAN 2.2.15). Nothing here is ever served directly.
Options -Indexes -ExecCGI -Includes
Require all denied

HTACCESS;

    /**
     * @return array<string, string> absolute path => required content
     */
    public static function files(): array
    {
        return [
            storage_path('app/public/.htaccess') => self::PUBLIC,
            storage_path('app/private/.htaccess') => self::DENY,
            storage_path('.htaccess') => self::DENY,
        ];
    }
}
