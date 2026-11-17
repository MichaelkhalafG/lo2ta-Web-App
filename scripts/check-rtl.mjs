#!/usr/bin/env node
// Fails when a .ts/.tsx file under the given folder uses a physical-direction Tailwind class
// (ml-, mr-, pl-, pr-, left-, right-, text-left, rounded-l, border-r, ...). Use the logical ones
// (ms-, me-, ps-, pe-, start-, end-, text-start, rounded-s, border-e) so one markup serves RTL and LTR.
// A line may opt out with the comment "rtl-ok" when a physical side is truly meant (an arrow icon, a chart axis).
// Usage: node scripts/check-rtl.mjs <folder> [<folder> ...]

import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";

const roots = process.argv.slice(2);
if (roots.length === 0) {
  console.error("usage: check-rtl.mjs <folder> [...]");
  process.exit(2);
}

const PHYSICAL =
  /(?<![\w-])-?(?:m[lr]|p[lr]|scroll-m[lr]|scroll-p[lr]|left|right|inset-[lr]|border-[lr]|rounded-[lr]|rounded-(?:tl|tr|bl|br))-[\w./[\]-]+|(?<![\w-])(?:text-(?:left|right)|float-(?:left|right)|clear-(?:left|right)|border-[lr]|rounded-[lr])(?![\w-])/g;
const CLASS_CONTEXT = /className=|class=|\bcn\(|\bclsx\(|\bcva\(|\btw`/;

const files = [];
function walk(dir) {
  for (const name of readdirSync(dir)) {
    if (name === "node_modules" || name.startsWith(".")) continue;
    const full = join(dir, name);
    if (statSync(full).isDirectory()) walk(full);
    else if (/\.(tsx?|jsx?)$/.test(name)) files.push(full);
  }
}
roots.forEach(walk);

if (files.length === 0) {
  console.error("check-rtl: no files found under " + roots.join(", ") + " (a check of nothing is not a pass)");
  process.exit(2);
}

let hits = 0;
for (const file of files) {
  const lines = readFileSync(file, "utf8").split(/\r?\n/);
  lines.forEach((line, i) => {
    if (!CLASS_CONTEXT.test(line) || line.includes("rtl-ok")) return;
    const found = line.match(PHYSICAL);
    if (found) {
      hits += found.length;
      console.log(`${relative(process.cwd(), file)}:${i + 1}  ${found.join(" ")}`);
    }
  });
}

console.log(`check-rtl: ${files.length} files scanned, ${hits} physical-direction classes`);
process.exit(hits === 0 ? 0 : 1);
