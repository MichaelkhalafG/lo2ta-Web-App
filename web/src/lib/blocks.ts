/*
 * A site text body as blocks (K7): paragraphs separated by a blank line; a block starting "## " is a heading; a block
 * whose every line starts "1. " (or any number) is a numbered list, "- " a bulleted list; "**lead**" at the start of
 * a paragraph or list item is the bold lead. Plain text in, plain data out: nothing is ever read as HTML.
 */

export type Inline = { lead: string | null; text: string };

export type Block = { kind: "heading"; text: string } | { kind: "paragraph"; inline: Inline } | { kind: "list"; ordered: boolean; items: Inline[] };

/** "**For sellers:** we review…" → lead "For sellers:", text "we review…". */
export function inline(text: string): Inline {
  const match = /^\*\*(.+?)\*\*\s*([\s\S]*)$/.exec(text);

  return match ? { lead: match[1] ?? "", text: match[2] ?? "" } : { lead: null, text };
}

export function blocks(body: string): Block[] {
  const out: Block[] = [];
  for (const raw of body.split(/\n\s*\n/)) {
    const block = raw.trim();
    if (block === "") {
      continue;
    }
    if (block.startsWith("## ")) {
      out.push({ kind: "heading", text: block.slice(3).trim() });
      continue;
    }
    const lines = block.split("\n").map((line) => line.trim()).filter((line) => line !== "");
    if (lines.every((line) => /^\d+\.\s+/.test(line))) {
      out.push({ kind: "list", ordered: true, items: lines.map((line) => inline(line.replace(/^\d+\.\s+/, ""))) });
      continue;
    }
    if (lines.every((line) => line.startsWith("- "))) {
      out.push({ kind: "list", ordered: false, items: lines.map((line) => inline(line.slice(2).trim())) });
      continue;
    }
    out.push({ kind: "paragraph", inline: inline(lines.join(" ")) });
  }

  return out;
}
