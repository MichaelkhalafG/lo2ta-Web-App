// The opportunities page (approved proposal 2026-11-24): the phone filter count, the featured card rule, the result line.
import { test } from "node:test";
import assert from "node:assert/strict";
import { omitted, panelFilterCount, placeName, showsFeatured } from "../src/lib/listing.ts";

test("the phone filter button counts only what sits behind it", () => {
  assert.equal(panelFilterCount({}), 0);
  assert.equal(panelFilterCount({ type: "land", sort: "newest", view: "list", page: 2 }), 0);
  assert.equal(panelFilterCount({ area: "new-cairo", owner_max: 3_000_000 }), 2);
  assert.equal(panelFilterCount({ area: "x", owner_max: 1, installment_max: 1, verified: true }), 4);
  assert.equal(panelFilterCount({ verified: false }), 0);
});

test("the featured card only on page 1 of the plain list", () => {
  assert.equal(showsFeatured({}), true);
  assert.equal(showsFeatured({ view: "list" }), true);
  assert.equal(showsFeatured({ page: 2 }), false);
  assert.equal(showsFeatured({ type: "land" }), false);
  assert.equal(showsFeatured({ verified: true }), false);
  assert.equal(showsFeatured({ sort: "newest" }), false);
});

test("placeName finds a governorate, an area or a district", () => {
  const tree = [{ slug: "cairo", name: "القاهرة", areas: [{ slug: "new-cairo", name: "القاهرة الجديدة", districts: [{ slug: "fifth", name: "التجمع الخامس" }] }] }];
  assert.equal(placeName(tree, "cairo"), "القاهرة");
  assert.equal(placeName(tree, "new-cairo"), "القاهرة الجديدة");
  assert.equal(placeName(tree, "fifth"), "التجمع الخامس");
  assert.equal(placeName(tree, "nowhere"), null);
  assert.equal(placeName(tree, undefined), null);
});

test("only set filters go in the address", () => {
  assert.equal(omitted({ name: "area", value: "" }), true);
  assert.equal(omitted({ name: "area", value: "new-cairo" }), false);
  assert.equal(omitted({ name: "sort", value: "default" }), true);
  assert.equal(omitted({ name: "sort", value: "newest" }), false);
  assert.equal(omitted({ name: "type", value: "", type: "radio", checked: true }), true);
  assert.equal(omitted({ name: "type", value: "land", type: "radio", checked: true }), false);
  assert.equal(omitted({ name: "verified", value: "1", type: "checkbox", checked: false }), true);
  assert.equal(omitted({ name: "view", value: "list", type: "hidden" }), false);
});
