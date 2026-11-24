// Second security pass N2: the language that server actions receive from the browser never shapes core's address.
import { test } from "node:test";
import assert from "node:assert/strict";
import { coreUrl } from "../src/lib/coreConfig.ts";

const BASE = "http://core.test";

test("coreUrl keeps the two languages", () => {
  assert.equal(coreUrl(BASE, "me", "ar"), "http://core.test/api/v1/me?locale=ar");
  assert.equal(coreUrl(BASE, "me", "en"), "http://core.test/api/v1/me?locale=en");
  assert.equal(coreUrl(BASE, "site-texts?group=home", "en"), "http://core.test/api/v1/site-texts?group=home&locale=en");
});

test("coreUrl turns any other value into Arabic, so nothing can be added to the query (the N2 proof)", () => {
  for (const bad of ["ar&_method=DELETE", "en&page=9", "en#x", "../x", "EN", "", null, undefined, 7, { toString: () => "en" }]) {
    const url = coreUrl(BASE, "me/units/01JABCDEFGHJKMNPQRSTVWXYZ0/submit", bad);
    assert.equal(url, "http://core.test/api/v1/me/units/01JABCDEFGHJKMNPQRSTVWXYZ0/submit?locale=ar", String(bad));
    assert.equal(new URL(url).searchParams.toString(), "locale=ar");
  }
});
