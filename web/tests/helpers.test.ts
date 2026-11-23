// Pure helpers that guard what arrives from the address bar (audit C1: the web had no tests).
import { test } from "node:test";
import assert from "node:assert/strict";
import { coreConfig } from "../src/lib/coreConfig.ts";
import { intentPath, parseIntent, returnQuery, safeNext } from "../src/lib/intent.ts";
import { codeFromSlug, filterQuery, parseFilters } from "../src/lib/listing.ts";

test("safeNext keeps paths on this site only (the audit's seven redirect tricks)", () => {
  for (const bad of ["//evil.example/x", "/\\evil.example", "https://evil.example", "javascript:alert(1)", "/\t/evil.example", "evil", "", "/" + "a".repeat(400)]) {
    assert.equal(safeNext(bad), "/account", bad);
  }
  assert.equal(safeNext(42), "/account");
  assert.equal(safeNext("/units/x-lk-nc-0124?request=1"), "/units/x-lk-nc-0124?request=1");
  assert.equal(safeNext("/%2F%2Fevil.example"), "/%2F%2Fevil.example");
});

test("parseIntent accepts the known intents only", () => {
  assert.deepEqual(parseIntent("budget"), { kind: "budget" });
  assert.deepEqual(parseIntent("favorite:lk-nc-0124"), { kind: "favorite", code: "LK-NC-0124" });
  assert.equal(parseIntent("favorite:../../x"), null);
  assert.equal(parseIntent("delete"), null);
  assert.equal(parseIntent(undefined), null);
});

test("intentPath and returnQuery", () => {
  assert.equal(intentPath({ kind: "request" }, "/units/a-lk-nc-0124?x=1"), "/units/a-lk-nc-0124?request=1");
  assert.equal(intentPath({ kind: "request" }, "/account"), "/account");
  assert.equal(intentPath({ kind: "sell" }, "/x"), "/sell");
  assert.equal(returnQuery("//evil.example", "budget"), "next=%2Faccount&intent=budget");
});

test("parseFilters drops unknown and out-of-range values", () => {
  assert.deepEqual(parseFilters({ type: "castle", area: "../x", owner_max: "1e9", installment_max: "0", sort: "drop", page: "1", view: "map" }), {});
  assert.deepEqual(parseFilters({ type: "land", area: "new-cairo", owner_max: "1000000000", verified: "1", sort: "newest", page: "3", view: "list" }), {
    type: "land",
    area: "new-cairo",
    owner_max: 1_000_000_000,
    verified: true,
    sort: "newest",
    view: "list",
    page: 3,
  });
  assert.deepEqual(parseFilters({ owner_max: "1000000001", page: "1001", type: ["land", "land"] }), {});
  assert.equal(filterQuery({ type: "land", page: 1, view: "grid" }, { verified: true }), "type=land&verified=1");
});

test("codeFromSlug", () => {
  assert.equal(codeFromSlug("villa-in-new-cairo-lk-nc-0124"), "LK-NC-0124");
  assert.equal(codeFromSlug("lk-nc-12"), null);
});

test("coreConfig needs both settings", () => {
  assert.deepEqual(coreConfig({ CORE_API_URL: " http://core.test/// ", CORE_API_KEY: " k " }), { base: "http://core.test", key: "k" });
  assert.throws(() => coreConfig({ CORE_API_URL: "http://core.test" }));
  assert.throws(() => coreConfig({ CORE_API_KEY: "k" }));
});

test("draftDeletable: only a draft never sent (audit L8)", async () => {
  const { draftDeletable, UNIT_ID } = await import("../src/lib/sell.ts");
  assert.equal(draftDeletable({ state: { code: "draft", label: "" }, reference: null }), true);
  assert.equal(draftDeletable({ state: { code: "draft", label: "" }, reference: "#0124" }), false);
  assert.equal(draftDeletable({ state: { code: "changes_requested", label: "" }, reference: "#0124" }), false);
  assert.equal(draftDeletable({ state: { code: "published", label: "" }, reference: "#0124" }), false);
  assert.equal(UNIT_ID.test("01M7Q6WNY96TT1CV1HPA8SMSXR"), true);
  assert.equal(UNIT_ID.test("../../etc/passwd"), false);
});
