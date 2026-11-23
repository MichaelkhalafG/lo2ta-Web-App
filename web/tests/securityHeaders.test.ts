// Audit M1: the public site sent no safety headers.
import { test } from "node:test";
import assert from "node:assert/strict";
import { securityHeaders } from "../src/lib/securityHeaders.ts";

const asMap = (production: boolean) => new Map(securityHeaders(production).map((h) => [h.key, h.value]));

test("frame protection, nosniff, referrer policy and feature policy on every answer", () => {
  const h = asMap(false);
  assert.equal(h.get("X-Frame-Options"), "DENY");
  assert.equal(h.get("Content-Security-Policy"), "frame-ancestors 'none'");
  assert.equal(h.get("X-Content-Type-Options"), "nosniff");
  assert.equal(h.get("Referrer-Policy"), "strict-origin-when-cross-origin");
  assert.match(h.get("Permissions-Policy") ?? "", /camera=\(\)/);
});

test("HSTS in production only, without subdomains", () => {
  assert.equal(asMap(false).has("Strict-Transport-Security"), false);
  assert.equal(asMap(true).get("Strict-Transport-Security"), "max-age=31536000");
});
