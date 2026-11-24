// The seller wizard rail (approved proposal 2026-11-24).
import { test } from "node:test";
import assert from "node:assert/strict";
import { stepProgress, stepState } from "../src/lib/sell.ts";

test("step states in the rail", () => {
  // On step 3 of a draft that reached step 3.
  assert.deepEqual([1, 2, 3, 4, 5, 6].map((n) => stepState(n, 3, 3)), ["done", "done", "current", "locked", "locked", "locked"]);
  // Back on step 2 after reaching step 4: step 4 is open, 3 is done.
  assert.deepEqual([1, 2, 3, 4, 5, 6].map((n) => stepState(n, 2, 4)), ["done", "current", "done", "open", "locked", "locked"]);
  // A fresh draft.
  assert.deepEqual([1, 2].map((n) => stepState(n, 1, 0)), ["current", "locked"]);
  // Reached beyond six is clamped.
  assert.equal(stepState(6, 1, 9), "open");
});

test("progress line", () => {
  assert.equal(stepProgress(1, 1), 0);
  assert.equal(stepProgress(3, 3), 40);
  assert.equal(stepProgress(2, 4), 60);
  assert.equal(stepProgress(6, 6), 100);
  assert.equal(stepProgress(1, 12), 100);
});
