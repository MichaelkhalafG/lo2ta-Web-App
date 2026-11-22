import { Link } from "@/i18n/navigation";
import { money, monthYear, type UnitCard as Card } from "@/lib/listing";

/*
 * The unit sheet card (board "Unit sheet card", PLAN 2.5 card row): the photo on top, the reading in the middle, a
 * title-block strip along the bottom. Gold only for the contract price and the لقطة tag; the verified stamp is a
 * cyan square, never gold. The dimension line draws paid (solid) against remaining (hairline). Renders the same on
 * the server and in the browser ("show more"), so it takes its words as props.
 */

export type CardWords = {
  verified: string;
  deal: string;
  reserved: string;
  projectImage: string;
  contractPrice: string;
  similarToday: string;
  payOwner: string;
  remaining: string;
  ownerFrom: string;
  installments: string;
  open: string;
  delivery: string;
  facade: string;
  sides: Record<string, string>;
  egp: string;
  noPhoto: string;
};

export function UnitCard({ unit, words, list = false }: { unit: Card; words: CardWords; list?: boolean }) {
  const href = unit.slug ? `/units/${unit.slug}` : "/opportunities";
  const paid = unit.paid_percent ?? 0;
  const delivery = monthYear(unit.delivery.month, unit.delivery.year);
  const facade = unit.facade?.map((s) => words.sides[s] ?? s).join(" ");

  return (
    <article className={`group flex overflow-hidden rounded-sm border border-line bg-surface-1 transition-colors duration-150 hover:border-line-strong ${list ? "flex-col sm:flex-row" : "flex-col"}`}>
      <Link href={href} className={`relative block bg-bg-1 ${list ? "sm:w-72 sm:shrink-0" : ""}`} tabIndex={-1} aria-hidden="true">
        {unit.cover ? (
          // eslint-disable-next-line @next/next/no-img-element -- a fixed-size WebP from core; next/image would add a second copy
          <img src={unit.cover.src} alt="" width={unit.cover.width} height={unit.cover.height} loading="lazy" decoding="async" className="aspect-[4/3] h-full w-full object-cover" />
        ) : (
          <div className="grid aspect-[4/3] place-items-center text-small text-text-3">{words.noPhoto}</div>
        )}
        <span className="absolute start-3 top-3 flex flex-wrap gap-2">
          {unit.is_verified ? (
            <span className="inline-flex items-center gap-1 rounded-[2px] border border-verified bg-bg-0/90 px-2 py-0.5 text-micro font-semibold text-verified">✓ {words.verified}</span>
          ) : null}
          {unit.deal_tag ? <span className="rounded-[2px] bg-gold px-2 py-0.5 text-micro font-bold text-on-gold">{words.deal}</span> : null}
          {unit.is_reserved ? <span className="rounded-[2px] bg-bg-0/90 px-2 py-0.5 text-micro text-text-2">{words.reserved}</span> : null}
        </span>
        {unit.cover?.is_project_image ? <span className="absolute end-3 bottom-3 rounded-[2px] bg-bg-0/85 px-2 py-0.5 text-micro text-text-2">{words.projectImage}</span> : null}
      </Link>

      <div className="flex flex-1 flex-col gap-4 p-5">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-small text-text-3">
          <span>{unit.main_type?.label}</span>
          {unit.size_m2 ? (
            <span>
              · <span className="font-mono tabular" dir="ltr">{Number(unit.size_m2)} m²</span>
            </span>
          ) : null}
          {facade ? <span>· {words.facade} {facade}</span> : null}
          <span className="ms-auto font-mono text-annotation" dir="ltr">
            {unit.code}
          </span>
        </div>

        <div className="flex flex-col gap-1">
          <h3 className="text-h3">
            <Link href={href} className="hover:underline hover:underline-offset-4">
              {unit.title}
            </Link>
          </h3>
          <p className="text-small text-text-2">
            {unit.place.join("، ")}
            {delivery ? ` · ${words.delivery} ${delivery}` : ""}
          </p>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div className="flex flex-col">
            <span className="text-micro text-text-3">{words.contractPrice}</span>
            <span className="font-mono text-price-card tabular text-gold" dir="ltr">
              {money(unit.total_price)}
            </span>
          </div>
          <div className="flex flex-col">
            <span className="text-micro text-text-3">{words.similarToday}</span>
            <span className="font-mono text-body tabular text-text-2" dir="ltr">
              {money(unit.market_price_estimate)}
            </span>
          </div>
        </div>

        {/* The dimension line: paid (solid) against remaining (hairline), exact values above. */}
        <div className="flex flex-col gap-1.5" aria-label={`${words.payOwner} ${money(unit.paid_amount)} · ${words.remaining} ${money(unit.remaining_amount)}`}>
          <div className="flex justify-between font-mono text-small tabular" dir="ltr">
            <span>{money(unit.remaining_amount)}</span>
            <span className="text-text-1">{money(unit.paid_amount)}</span>
          </div>
          <div className="relative h-2" aria-hidden="true">
            <div className="absolute inset-x-0 top-1/2 h-px bg-line-strong" />
            <div className="absolute end-0 top-1/2 h-0.5 -translate-y-1/4 bg-text-1" style={{ width: `${Math.min(100, Math.max(0, paid))}%` }} />
          </div>
          <div className="flex justify-between text-micro text-text-3">
            <span>
              {words.payOwner} · <span dir="ltr">{paid}%</span>
            </span>
            <span>{words.remaining}</span>
          </div>
          {unit.accepts_less && unit.owner_from !== null ? (
            <span className="text-small text-success">
              {words.ownerFrom} <span className="font-mono tabular" dir="ltr">{money(unit.owner_from)}</span>
            </span>
          ) : null}
        </div>

        <div className="mt-auto flex items-center justify-between gap-3 border-t border-line pt-3 text-small">
          <span className="text-text-2">
            {unit.installments.count && unit.installments.amount
              ? `${unit.installments.count} ${words.installments} ${unit.installments.frequency?.label ?? ""} × `
              : ""}
            {unit.installments.amount ? (
              <span className="font-mono tabular" dir="ltr">
                {money(unit.installments.amount)}
              </span>
            ) : null}
          </span>
          <Link href={href} className="inline-flex items-center gap-1 text-text-1 hover:text-gold-bright">
            {words.open}{" "}
            <span aria-hidden="true" className="rtl:rotate-180">
              →
            </span>
          </Link>
        </div>
      </div>
    </article>
  );
}
