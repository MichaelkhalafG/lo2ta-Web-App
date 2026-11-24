import { Link } from "@/i18n/navigation";
import { money, monthYear, type UnitCard as Card } from "@/lib/listing";
import type { CardWords } from "./UnitCard";

/*
 * The week's لقطة, wide at the top of the plain list (board 06.2, approved proposal 2026-11-24): the photo beside
 * the reading, the dimension line with its ticks, and the page's one gold button. The other cards keep a text link,
 * so gold stays a small share of the screen.
 */
export function FeaturedCard({ unit, words, label }: { unit: Card; words: CardWords; label: string }) {
  const href = unit.slug ? `/units/${unit.slug}` : "/opportunities";
  const paid = Math.min(100, Math.max(0, unit.paid_percent ?? 0));
  const delivery = monthYear(unit.delivery.month, unit.delivery.year);
  const facade = unit.facade?.map((s) => words.sides[s] ?? s).join(" ");

  return (
    <article className="grid overflow-hidden rounded-sm border border-line bg-surface-1 md:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
      <Link href={href} className="relative block min-h-60 bg-bg-1" tabIndex={-1} aria-hidden="true">
        {unit.cover ? (
          // eslint-disable-next-line @next/next/no-img-element -- a fixed-size WebP from core
          <img src={unit.cover.src} alt="" width={unit.cover.width} height={unit.cover.height} className="absolute inset-0 size-full object-cover" />
        ) : (
          <span className="absolute inset-0 grid place-items-center text-small text-text-3">{words.noPhoto}</span>
        )}
        <span className="absolute end-5 top-5 rounded-none bg-gold px-3 py-0.5 text-small font-bold text-on-gold">{label}</span>
        {unit.is_verified ? (
          <span className="absolute start-5 top-5 rounded-[2px] border border-verified bg-bg-0/90 px-2 py-0.5 text-small font-semibold text-verified">✓ {words.verified}</span>
        ) : null}
        {unit.cover?.is_project_image ? <span className="absolute end-5 bottom-5 rounded-[2px] bg-bg-0/85 px-2 py-0.5 text-micro text-text-2">{words.projectImage}</span> : null}
      </Link>

      <div className="flex flex-col gap-4 p-5 md:p-8">
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
          <h2 className="text-h2-m md:text-h2">
            <Link href={href} className="hover:underline hover:underline-offset-4">
              {unit.title}
            </Link>
          </h2>
          <p className="text-small text-text-2">
            {unit.place.join("، ")}
            {delivery ? ` · ${words.delivery} ${delivery}` : ""}
          </p>
        </div>

        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
          <div className="flex min-w-0 flex-col">
            <span className="text-micro text-text-3">{words.contractPrice}</span>
            <span className="text-[2rem] leading-tight font-semibold text-gold tabular md:text-[2.25rem]" dir="ltr">
              {money(unit.total_price)}
            </span>
          </div>
          <div className="flex min-w-0 flex-col">
            <span className="text-micro text-text-3">{words.similarToday}</span>
            <span className="text-lead font-medium text-text-2 tabular" dir="ltr">
              {money(unit.market_price_estimate)}
            </span>
          </div>
        </div>

        <div className="flex flex-col gap-1.5" aria-label={`${words.payOwner} ${money(unit.paid_amount)} · ${words.remaining} ${money(unit.remaining_amount)}`}>
          <div className="flex justify-between font-mono text-annotation tabular" dir="ltr">
            <span className="text-text-2">{money(unit.remaining_amount)}</span>
            <span className="text-text-1">{money(unit.paid_amount)}</span>
          </div>
          <div className="relative h-3" aria-hidden="true">
            <div className="absolute inset-x-0 top-1/2 h-px bg-line-strong" />
            <div className="absolute end-0 top-1/2 h-0.5 -translate-y-1/4 bg-text-1" style={{ width: `${paid}%` }} />
            <div className="absolute start-0 top-0 h-3 w-px bg-line-strong" />
            <div className="absolute top-0 h-3 w-px bg-text-1" style={{ insetInlineEnd: `${paid}%` }} />
            <div className="absolute end-0 top-0 h-3 w-px bg-text-1" />
          </div>
          <div className="flex justify-between gap-3 text-micro text-text-3">
            <span>
              {words.payOwner} · <span dir="ltr">{paid}%</span>
            </span>
            {unit.installments.count && unit.installments.amount ? (
              <span>
                {unit.installments.count} {words.installments} {unit.installments.frequency?.label ?? ""} ×{" "}
                <span className="tabular" dir="ltr">
                  {money(unit.installments.amount)}
                </span>
              </span>
            ) : (
              <span>{words.remaining}</span>
            )}
          </div>
        </div>

        <div className="mt-auto flex flex-wrap items-center justify-between gap-4 pt-2">
          <Link href={href} className="inline-flex h-control-lg items-center gap-3 rounded-sm bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright">
            {words.open}
            <span aria-hidden="true" className="rtl:rotate-180">
              →
            </span>
          </Link>
          {unit.accepts_less && unit.owner_from !== null ? (
            <span className="text-small text-success">
              {words.ownerFrom}{" "}
              <span className="tabular" dir="ltr">
                {money(unit.owner_from)}
              </span>
            </span>
          ) : null}
        </div>
      </div>
    </article>
  );
}
