import type { ReactNode } from "react";
import { money } from "@/lib/listing";

/*
 * The drawing marks of the unit sheet (board 06.3): a unit page reads like one sheet of a contract drawing. Section
 * marks number the sheet's parts, the payment plan is a dimension line, the façade is a north arrow. Marks are
 * annotations: IBM Plex Mono, small, line colours; money stays in Alexandria (tokens: mono for annotations only).
 */

/** A circled section mark: the part's letter over the number of parts, tied to the heading by a short rule. */
export function SectionHead({ letter, total, title }: { letter: string; total: number; title: string }) {
  return (
    <header className="flex items-center gap-3">
      <span aria-hidden="true" className="grid size-12 shrink-0 grid-rows-2 rounded-full border border-line-strong font-mono text-annotation text-text-2" dir="ltr">
        <span className="grid place-items-end justify-center border-b border-line-strong pb-0.5">{letter}</span>
        <span className="grid place-items-start justify-center pt-0.5 text-text-3">{String(total).padStart(2, "0")}</span>
      </span>
      <span aria-hidden="true" className="h-px w-6 bg-line-strong" />
      <h2 className="text-h3">{title}</h2>
    </header>
  );
}

/**
 * The payment plan as a dimension line: paid (heavy) from the start, remaining (hairline) to the end, ticks at both
 * ends and at the split; the contract price is the overall dimension below.
 */
export function PlanLine({
  paid,
  remaining,
  total,
  paidLabel,
  remainingLabel,
  totalLabel,
  currency,
}: {
  paid: number;
  remaining: number;
  total: number;
  paidLabel: string;
  remainingLabel: string;
  totalLabel: string;
  currency: string;
}) {
  const share = total > 0 ? Math.min(100, Math.max(0, (paid / total) * 100)) : 0;

  return (
    <figure className="flex flex-col gap-2" aria-label={`${paidLabel} ${money(paid)} · ${remainingLabel} ${money(remaining)} · ${totalLabel} ${money(total)}`}>
      <div aria-hidden="true" className="flex text-small">
        <div className="flex min-w-0 flex-col items-center gap-0.5 px-1 text-center" style={{ width: `${share}%` }}>
          <span className="text-text-2">{paidLabel}</span>
          <span className="font-mono text-annotation text-text-1 tabular" dir="ltr">
            {money(paid)}
          </span>
        </div>
        <div className="flex min-w-0 flex-1 flex-col items-center gap-0.5 px-1 text-center">
          <span className="text-text-2">{remainingLabel}</span>
          <span className="font-mono text-annotation text-text-2 tabular" dir="ltr">
            {money(remaining)}
          </span>
        </div>
      </div>
      <div aria-hidden="true" className="relative h-4">
        <span className="absolute inset-x-0 top-1/2 h-px bg-line-strong" />
        <span className="absolute start-0 top-1/2 h-[3px] -translate-y-px bg-text-1" style={{ width: `${share}%` }} />
        <span className="absolute start-0 top-0 h-4 w-px bg-text-1" />
        <span className="absolute top-0 h-4 w-px bg-text-1" style={{ insetInlineStart: `${share}%` }} />
        <span className="absolute end-0 top-0 h-4 w-px bg-line-strong" />
      </div>
      <div aria-hidden="true" className="relative mt-1 h-3">
        <span className="absolute inset-x-0 top-1/2 h-px bg-line" />
        <span className="absolute start-0 top-0 h-3 w-px bg-line-strong" />
        <span className="absolute end-0 top-0 h-3 w-px bg-line-strong" />
      </div>
      <figcaption aria-hidden="true" className="text-center text-small text-text-3">
        {totalLabel} ·{" "}
        <span className="font-mono text-annotation text-text-1 tabular" dir="ltr">
          {money(total)}
        </span>{" "}
        {currency}
      </figcaption>
    </figure>
  );
}

const SIDES = ["north", "east", "south", "west"] as const;

/** A north arrow; the façade's sides are drawn heavy on the ring (a compass is physical: north up, east right). */
export function NorthArrow({ facade, label }: { facade: string[]; label: string }) {
  const on = new Set(facade);
  // One arc of the ring per side, centred on its compass direction and 50 degrees wide (radius 40 around 50,50).
  const arc = (centre: number): string => {
    const point = (deg: number) => {
      const rad = ((deg - 90) * Math.PI) / 180;
      return `${(50 + 40 * Math.cos(rad)).toFixed(1)} ${(50 + 40 * Math.sin(rad)).toFixed(1)}`;
    };
    return `M ${point(centre - 25)} A 40 40 0 0 1 ${point(centre + 25)}`;
  };
  const arcs: Record<(typeof SIDES)[number], string> = { north: arc(0), east: arc(90), south: arc(180), west: arc(270) };

  return (
    <figure className="flex flex-col items-center gap-2">
      <svg viewBox="0 0 100 112" className="h-28 w-24" role="img" aria-label={label}>
        <text x="50" y="10" textAnchor="middle" className="fill-text-2 font-mono text-[11px]">
          N
        </text>
        <circle cx="50" cy="62" r="40" className="fill-none stroke-line-strong" strokeWidth="1" />
        <g transform="translate(0 12)">
          {SIDES.map((side) => (
            <path key={side} d={arcs[side]} className={`fill-none ${on.has(side) ? "stroke-text-1" : "stroke-transparent"}`} strokeWidth="3" />
          ))}
        </g>
        <path d="M 50 34 L 60 80 L 50 72 L 40 80 Z" className="fill-text-1" />
      </svg>
      <figcaption className="text-center text-body font-semibold">{label}</figcaption>
    </figure>
  );
}

/** One row of the money panel: label at the start, value at the end, an optional note under them. */
export function MoneyRow({ label, value, note, emphasis = false }: { label: string; value: ReactNode; note?: ReactNode; emphasis?: boolean }) {
  return (
    <div className="flex flex-col gap-1 border-t border-line px-5 py-4">
      <div className="flex items-baseline justify-between gap-4">
        <span className="text-small text-text-2">{label}</span>
        <span className={`${emphasis ? "text-h3" : "text-lead font-semibold"} tabular`} dir="ltr">
          {value}
        </span>
      </div>
      {note ? <div className="flex flex-col items-end gap-0.5 text-end">{note}</div> : null}
    </div>
  );
}
