// Audit H1: the visitor's address comes from the right of X-Forwarded-For, where the trusted proxy wrote it.
import { test } from "node:test";
import assert from "node:assert/strict";
import { clientIpFrom, trustedHops } from "../src/lib/clientIp.ts";

test("a forged first entry is ignored: the proxy's entry wins", () => {
  // The audit's attack: the visitor sends 198.51.100.7, the host's proxy appends the real address.
  assert.equal(clientIpFrom("198.51.100.7, 203.0.113.9", 1), "203.0.113.9");
  assert.equal(clientIpFrom("1.1.1.1,2.2.2.2,  203.0.113.9 ", 1), "203.0.113.9");
});

test("seven forged addresses all resolve to the same visitor", () => {
  const seen = new Set<string | null>();
  for (let n = 1; n <= 7; n++) {
    seen.add(clientIpFrom(`198.51.100.${n}, 203.0.113.9`, 1));
  }
  assert.deepEqual([...seen], ["203.0.113.9"]);
});

test("a single entry (no proxy, Next filled it from the socket) is used", () => {
  assert.equal(clientIpFrom("127.0.0.1", 1), "127.0.0.1");
  assert.equal(clientIpFrom("::ffff:127.0.0.1", 1), "::ffff:127.0.0.1");
  assert.equal(clientIpFrom("2001:db8::1", 1), "2001:db8::1");
});

test("two proxies: the second entry from the right", () => {
  assert.equal(clientIpFrom("198.51.100.1, 203.0.113.9, 10.0.0.2", 2), "203.0.113.9");
});

test("a list shorter than the proxy count is not trusted", () => {
  assert.equal(clientIpFrom("203.0.113.9", 2), null);
});

test("missing, empty, oversized or non-IP values give null", () => {
  assert.equal(clientIpFrom(null, 1), null);
  assert.equal(clientIpFrom(undefined, 1), null);
  assert.equal(clientIpFrom("", 1), null);
  assert.equal(clientIpFrom("1.1.1.1, evil.example", 1), null);
  assert.equal(clientIpFrom("1.1.1.1, <script>", 1), null);
  assert.equal(clientIpFrom("1.1.1.1, ", 1), null);
  assert.equal(clientIpFrom("1.1.1.1,".repeat(200) + "203.0.113.9", 1), null);
});

test("TRUSTED_PROXY_HOPS: 1 to 5, anything else is 1", () => {
  assert.equal(trustedHops(undefined), 1);
  assert.equal(trustedHops(""), 1);
  assert.equal(trustedHops("2"), 2);
  assert.equal(trustedHops(" 3 "), 3);
  assert.equal(trustedHops("0"), 1);
  assert.equal(trustedHops("9"), 1);
  assert.equal(trustedHops("two"), 1);
});
