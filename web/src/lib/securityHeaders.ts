/*
 * Browser safety headers on every page and file (audit M1, Michael 2026-11-23 decision 7A): no framing by another
 * site, no type guessing, the page address kept from other sites, unused browser features off, HTTPS remembered
 * for a year in production (browsers ignore HSTS over plain HTTP, so local runs are unaffected). Subdomains are
 * left out of HSTS on purpose: the host's mail and panel subdomains are not ours to promise. The full
 * Content-Security-Policy with nonces is Phase 8.
 */
export function securityHeaders(production: boolean): { key: string; value: string }[] {
  return [
    { key: "X-Frame-Options", value: "DENY" },
    { key: "Content-Security-Policy", value: "frame-ancestors 'none'" },
    { key: "X-Content-Type-Options", value: "nosniff" },
    { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
    { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), payment=(), usb=()" },
    { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
    ...(production ? [{ key: "Strict-Transport-Security", value: "max-age=31536000" }] : []),
  ];
}
