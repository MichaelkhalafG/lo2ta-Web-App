// The unit gallery (approved proposal 2026-11-24): what stands on the sheet and how the viewer moves.
import { test } from "node:test";
import assert from "node:assert/strict";
import { arrowStep, galleryLayout, step, type Picture } from "../src/lib/gallery.ts";

const photo = (n: number): Picture => ({ src: `/p${n}.webp`, width: 1200, height: 900, is_project_image: false, kind: "photo" });
const plan: Picture = { src: "/plan.webp", width: 1200, height: 900, is_project_image: false, kind: "floor_plan" };

test("nine photos and a plan: main, plan, next photo with +7", () => {
  const g = galleryLayout(Array.from({ length: 9 }, (_, i) => photo(i + 1)), plan);
  assert.equal(g.items.length, 10);
  assert.equal(g.items[g.items.length - 1], plan);
  assert.equal(g.main, 0);
  assert.deepEqual(g.side, [9, 1]);
  assert.equal(g.more, 7);
});

test("photos without a plan: two photos beside the main one", () => {
  const g = galleryLayout([photo(1), photo(2), photo(3), photo(4)], null);
  assert.deepEqual(g.side, [1, 2]);
  assert.equal(g.more, 1);
});

test("small sets: no badge, no empty tiles", () => {
  assert.deepEqual(galleryLayout([photo(1)], null), { items: [photo(1)], main: 0, side: [], more: 0 });
  assert.deepEqual(galleryLayout([photo(1)], plan).side, [1]);
  assert.equal(galleryLayout([photo(1), photo(2)], plan).more, 0);
  assert.deepEqual(galleryLayout([], null), { items: [], main: null, side: [], more: 0 });
  // Only a plan: it is the main picture, nothing beside it.
  assert.deepEqual(galleryLayout([], plan), { items: [plan], main: 0, side: [], more: 0 });
});

test("the viewer wraps at both ends", () => {
  assert.equal(step(0, -1, 10), 9);
  assert.equal(step(9, 1, 10), 0);
  assert.equal(step(4, 1, 10), 5);
  assert.equal(step(0, 1, 0), 0);
});

test("arrow keys follow the reading direction", () => {
  assert.equal(arrowStep("ArrowLeft", "rtl"), 1);
  assert.equal(arrowStep("ArrowRight", "rtl"), -1);
  assert.equal(arrowStep("ArrowRight", "ltr"), 1);
  assert.equal(arrowStep("ArrowLeft", "ltr"), -1);
  assert.equal(arrowStep("Enter", "ltr"), 0);
});
