#!/usr/bin/env node
/**
 * Fails when a brand colour in either app drifts from the delivered design tokens.
 *
 * The reference is docs/design/tokens/lokta-tokens.css (`--lk-<name>: <colour>`). The public site's copy
 * (web/src/styles/lokta-theme.css, `--color-<name>`) and the dashboard's copy (core/resources/css/lokta-tokens.css,
 * `--lk-<name>`) must carry every reference colour with the same value. The apps cannot share one file (copy,
 * never link), so this check is what keeps them in step.
 *
 * Usage: node scripts/check-tokens.mjs        Exit 0 = in step, 1 = drift, 2 = a file is missing.
 */
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const REFERENCE = { file: "docs/design/tokens/lokta-tokens.css", prefix: "--lk-" };
const COPIES = [
  { file: "web/src/styles/lokta-theme.css", prefix: "--color-" },
  { file: "core/resources/css/lokta-tokens.css", prefix: "--lk-" },
];

/** #abc / #AABBCC / rgb(a)(r, g, b, a) / rgb(r g b / a) → "r,g,b,a" so equal colours compare equal. */
function normalise(value) {
  const v = value.trim().toLowerCase();
  const hex = v.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/);
  if (hex) {
    const h = hex[1].length === 3 ? [...hex[1]].map((c) => c + c).join("") : hex[1];
    return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16)).join(",") + ",1";
  }
  const fn = v.match(/^rgba?\(([^)]+)\)$/);
  if (fn) {
    const parts = fn[1].split(/[\s,/]+/).filter(Boolean).map(Number);
    return [...parts.slice(0, 3), parts[3] ?? 1].join(",");
  }
  return null; // not a colour (sizes, fonts …): ignored
}

function colours(file, prefix) {
  const path = join(root, file);
  if (!existsSync(path)) {
    console.error(`check-tokens: missing ${file}`);
    process.exit(2);
  }
  const css = readFileSync(path, "utf8").replace(/\/\*[\s\S]*?\*\//g, "");
  const out = new Map();
  const re = new RegExp(`${prefix.replace(/-/g, "\\-")}([a-z0-9-]+)\\s*:\\s*([^;]+);`, "g");
  for (const [, name, value] of css.matchAll(re)) {
    const n = normalise(value);
    if (n !== null && !out.has(name)) {
      out.set(name, { raw: value.trim(), n });
    }
  }
  return out;
}

const reference = colours(REFERENCE.file, REFERENCE.prefix);
let problems = 0;

for (const copy of COPIES) {
  const found = colours(copy.file, copy.prefix);
  for (const [name, ref] of reference) {
    const mine = found.get(name);
    if (!mine) {
      console.error(`${copy.file}: ${copy.prefix}${name} missing (reference ${ref.raw})`);
      problems++;
    } else if (mine.n !== ref.n) {
      console.error(`${copy.file}: ${copy.prefix}${name} is ${mine.raw}, reference ${ref.raw}`);
      problems++;
    }
  }
}

if (problems > 0) {
  console.error(`check-tokens: ${problems} colour(s) out of step with ${REFERENCE.file}`);
  process.exit(1);
}
console.log(`check-tokens: ${reference.size} colours in step across ${COPIES.length} copies`);
