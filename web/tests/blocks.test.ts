import { test } from "node:test";
import assert from "node:assert/strict";
import { blocks, inline } from "../src/lib/blocks.ts";

test("a body splits into paragraphs, headings and lists (K7)", () => {
  const body = "First line\nsame paragraph.\n\n## A heading\n\n1. **For sellers:** no fee.\n2. **For buyers:** 1%.\n\n- one\n- two\n\nLast.";
  assert.deepEqual(blocks(body), [
    { kind: "paragraph", inline: { lead: null, text: "First line same paragraph." } },
    { kind: "heading", text: "A heading" },
    { kind: "list", ordered: true, items: [{ lead: "For sellers:", text: "no fee." }, { lead: "For buyers:", text: "1%." }] },
    { kind: "list", ordered: false, items: [{ lead: null, text: "one" }, { lead: null, text: "two" }] },
    { kind: "paragraph", inline: { lead: null, text: "Last." } },
  ]);
});

test("a mixed block stays a paragraph, and markup is text, never HTML", () => {
  assert.deepEqual(blocks("1. one\nnot a list item"), [{ kind: "paragraph", inline: { lead: null, text: "1. one not a list item" } }]);
  assert.deepEqual(blocks("<b>x</b> **not a lead** here"), [{ kind: "paragraph", inline: { lead: null, text: "<b>x</b> **not a lead** here" } }]);
  assert.deepEqual(inline("**Only a lead**"), { lead: "Only a lead", text: "" });
  assert.deepEqual(blocks("\n\n  \n\n"), []);
});
