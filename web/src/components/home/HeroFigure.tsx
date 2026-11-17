import type { CSSProperties } from "react";

const fmt = new Intl.NumberFormat("en-US");

type Props = {
  contract: number;
  today: number;
  labels: { note: string; contract: string; find: string; today: string; currency: string };
};

/**
 * The hero's dimension line (board 03, "Dimension line" and 06.1): the contract price as a measured length, the
 * buyer's find (today's price minus the contract price) in gold, and today's similar-unit price as the overall
 * line below. Segments draw in from the inline-start edge, one after the other; off under reduced motion.
 * The figures are an illustrative example until real units exist, and the label says so.
 */
export function HeroFigure({ contract, today, labels }: Props) {
  const find = today - contract;
  const share = Math.round((contract / today) * 1000) / 10;
  const contractWidth = `${share}%`;
  const findWidth = `${Math.round((100 - share) * 10) / 10}%`;
  const delay = (i: number): CSSProperties => ({ animationDelay: `${i * 420}ms` });

  return (
    <figure className="flex flex-col gap-1.5" aria-label={labels.note}>
      <figcaption className="mb-2 font-mono text-annotation text-text-3">{labels.note}</figcaption>

      <div className="grid" style={{ gridTemplateColumns: `${share}fr ${100 - share}fr` }}>
        <div className="flex animate-fade flex-col items-center gap-0.5" style={delay(0)}>
          <span className="text-annotation text-text-2">{labels.contract}</span>
          <span className="font-mono text-[0.875rem] font-medium text-text-1 tabular">{fmt.format(contract)}</span>
        </div>
        <div className="flex animate-fade flex-col items-center gap-0.5" style={delay(1)}>
          <span className="text-annotation font-semibold text-gold">{labels.find}</span>
          <span className="font-mono text-[0.875rem] font-medium text-gold tabular">{fmt.format(find)}</span>
        </div>
      </div>

      <div className="relative h-[15px]" aria-hidden="true">
        <span className="absolute start-0 top-[7px] h-px animate-draw bg-text-1 origin-start" style={{ width: contractWidth, ...delay(0) }} />
        <span className="absolute end-0 top-1.5 h-[3px] animate-draw bg-gold origin-start" style={{ width: findWidth, ...delay(1) }} />
        <Tick at="0%" tone="bg-text-1" />
        <Tick at={contractWidth} tone="bg-text-1" />
        <Tick at="100%" tone="bg-gold" />
      </div>

      <div className="relative mt-1 h-[15px]" aria-hidden="true">
        <span className="absolute start-0 end-0 top-[7px] h-px animate-draw bg-text-3 origin-start" style={delay(2)} />
        <span className="absolute start-0 top-0 h-[15px] w-px bg-text-3" />
        <span className="absolute end-0 top-0 h-[15px] w-px bg-text-3" />
      </div>

      <span className="animate-fade text-center text-annotation text-text-2" style={delay(2)}>
        {labels.today} · <span className="font-mono text-[0.8125rem] font-medium text-text-1 tabular">{fmt.format(today)}</span>{" "}
        {labels.currency}
      </span>
    </figure>
  );
}

/** A dimension tick: a vertical stroke and the 45° slash, at a position along the line. */
function Tick({ at, tone }: { at: string; tone: string }) {
  const position: CSSProperties = { insetInlineStart: at === "100%" ? "calc(100% - 1px)" : at };

  return (
    <>
      <span className={`absolute top-0 h-[15px] w-px ${tone}`} style={position} />
      <span className={`absolute top-0.5 h-[11px] w-px rotate-45 ${tone}`} style={position} />
    </>
  );
}
