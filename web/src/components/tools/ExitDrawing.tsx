import { money } from "@/lib/listing";

/*
 * The seller's two exits drawn as dimension lines on one scale (the Blueprint identity's plan line, board 06.3):
 * what was paid is the overall dimension; cancelling returns a range (solid to the lowest refund, a hatched band to
 * the highest); selling through Lokta returns the net. Read from the reading-start edge in both languages.
 */

type Row = { label: string; from: number; to: number; value: string; tone: "paid" | "cancel" | "sell" };

function share(amount: number, scale: number): number {
  return scale > 0 ? Math.min(100, Math.max(0, (amount / scale) * 100)) : 0;
}

export function ExitDrawing({
  paid,
  refundMin,
  refundMax,
  net,
  labels,
  currency,
}: {
  paid: number;
  refundMin: number;
  refundMax: number;
  net: number;
  labels: { paid: string; cancel: string; sell: string };
  currency: string;
}) {
  const scale = Math.max(paid, net, refundMax, 1);
  const rows: Row[] = [
    { label: labels.paid, from: paid, to: paid, value: money(paid), tone: "paid" },
    { label: labels.cancel, from: refundMin, to: refundMax, value: refundMin === refundMax ? money(refundMin) : `${money(refundMin)} – ${money(refundMax)}`, tone: "cancel" },
    { label: labels.sell, from: Math.max(0, net), to: Math.max(0, net), value: money(net), tone: "sell" },
  ];
  const summary = rows.map((r) => `${r.label}: ${r.value} ${currency}`).join(". ");

  return (
    <figure className="flex flex-col gap-5" aria-label={summary}>
      {rows.map((row) => {
        const solid = share(row.from, scale);
        const band = share(row.to, scale) - solid;
        const bar = row.tone === "sell" ? "bg-gold" : row.tone === "cancel" ? "bg-text-2" : "bg-text-1";

        return (
          <div key={row.tone} aria-hidden="true" className="flex flex-col gap-1.5">
            <div className="flex items-baseline justify-between gap-3 text-small">
              <span className="text-text-2">{row.label}</span>
              <span className={`font-semibold tabular ${row.tone === "sell" ? "text-gold" : "text-text-1"}`} dir="ltr">
                {row.value}
              </span>
            </div>
            <div className="relative h-4">
              <span className="absolute inset-x-0 top-1/2 h-px bg-line" />
              <span className={`absolute start-0 top-1/2 h-[3px] -translate-y-px ${bar}`} style={{ width: `${solid}%` }} />
              {band > 0 ? (
                <span
                  className="absolute top-1/2 h-[7px] -translate-y-[3px] border-y border-text-3 bg-[repeating-linear-gradient(135deg,var(--color-line-strong)_0_2px,transparent_2px_6px)]"
                  style={{ insetInlineStart: `${solid}%`, width: `${band}%` }}
                />
              ) : null}
              <span className="absolute start-0 top-0 h-4 w-px bg-text-1" />
              <span className={`absolute top-0 h-4 w-px ${row.tone === "sell" ? "bg-gold" : "bg-text-1"}`} style={{ insetInlineStart: `calc(${share(row.to, scale)}% - 1px)` }} />
            </div>
          </div>
        );
      })}
    </figure>
  );
}
