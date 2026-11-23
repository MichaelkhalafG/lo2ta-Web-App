import type { Metadata } from "next";
import { notFound, permanentRedirect } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { FavoriteButton } from "@/components/account/FavoriteButton";
import { RequestPanel } from "@/components/listing/RequestPanel";
import { MoneyRow, NorthArrow, PlanLine, SectionHead } from "@/components/listing/UnitMarks";
import { VerifiedStamp } from "@/components/VerifiedStamp";
import { Link } from "@/i18n/navigation";
import { siteTexts, text } from "@/lib/core";
import { codeFromSlug, money, monthYear, type UnitDetail } from "@/lib/listing";
import { unitByCode } from "@/lib/listingServer";
import { pageLocale } from "@/lib/page";
import { alternates, localizedPath, siteUrl } from "@/lib/seo";

/*
 * The unit page (board 06.3, PLAN 2.5 unit page row): the card's fields plus specs, the payment plan with the computed
 * schedule, the place by name only (no map), the money panel and "Request this unit". The slug ends with the unit
 * code; an old slug redirects to the current one (PLAN 5.2 point 7).
 *
 * Audit 2026-11-22 (D1 to D3): the page reads as one sheet of a drawing: a title block (sheet number, area), circled
 * section marks, the payment plan as a dimension line, a north arrow for the façade. Money is set in Alexandria with
 * tabular figures, the contract price at the price size in gold; mono stays for annotations. On phones the money
 * panel follows the photos and a bar with the amount and the request stays at the bottom.
 */

async function load(params: Promise<{ locale: string; slug: string }>) {
  const locale = await pageLocale(params);
  const { slug } = await params;
  const code = codeFromSlug(slug);
  const unit = code ? await unitByCode(code, locale) : null;

  return { locale, slug, unit };
}

export async function generateMetadata({ params }: PageProps<"/[locale]/units/[slug]">): Promise<Metadata> {
  const { locale, unit } = await load(params);
  if (!unit || !unit.slug) {
    return { robots: { index: false, follow: false } };
  }
  const cover = unit.photos.find((p) => p.kind === "photo");
  const title = `${unit.title} · ${unit.code}`;

  return {
    title,
    description: [unit.compound?.name, unit.location?.area?.name, unit.size_m2 ? `${Number(unit.size_m2)} m²` : null].filter(Boolean).join(" · "),
    alternates: alternates(`/units/${unit.slug}`, locale),
    openGraph: { title, ...(cover ? { images: [{ url: cover.src, width: cover.width, height: cover.height }] } : {}) },
  };
}

/** The sheet has three parts: specs, payment plan, location. */
const PARTS = 3;

export default async function UnitPage({ params, searchParams }: PageProps<"/[locale]/units/[slug]">) {
  const { locale, slug, unit } = await load(params);
  if (!unit) {
    notFound();
  }
  if (unit.slug && unit.slug !== slug) {
    permanentRedirect(localizedPath(`/units/${unit.slug}`, locale));
  }
  const [t, tr, buyerTexts] = await Promise.all([getTranslations("listing.unit"), getTranslations("listing.request"), siteTexts("buyer", locale)]);
  const path = `/units/${unit.slug ?? slug}`;
  const startOpen = (await searchParams).request === "1";
  const m = unit.money;
  const photos = unit.photos.filter((p) => p.kind === "photo");
  const plan = unit.photos.find((p) => p.kind === "floor_plan");
  const place = [unit.location?.district?.name, unit.location?.area?.name].filter(Boolean).join("، ");
  const sides = unit.facade?.map((s) => t(`sides.${s}`)).join("، ") ?? "";
  const published = unit.state === "published";

  return (
    <article className="px-gutter-mobile pt-8 pb-20 md:px-gutter md:pt-12 md:pb-24">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLd(unit, locale) }} />
      <div className="mx-auto flex max-w-7xl flex-col gap-8">
        <nav aria-label={t("breadcrumb")} className="flex flex-wrap items-center gap-x-2 text-small text-text-3">
          <Link href="/opportunities" className="inline-flex min-h-control-md items-center hover:text-text-1">
            {t("opportunities")}
          </Link>
          <span aria-hidden="true">/</span>
          <span>{unit.main_type?.label}</span>
          {unit.location?.area ? (
            <>
              <span aria-hidden="true">/</span>
              <span>{unit.location.area.name}</span>
            </>
          ) : null}
        </nav>

        <header className="flex flex-wrap items-start justify-between gap-6">
          <div className="flex flex-col gap-3">
            <h1 className="text-h1-m md:text-h1">{unit.title}</h1>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
              {unit.deal_tag ? <span className="rounded-none bg-gold px-2.5 py-1 text-small font-bold text-on-gold">{t("deal")}</span> : null}
              {unit.state === "reserved" ? <span className="rounded-[2px] border border-line px-2.5 py-1 text-small text-text-2">{t("reserved")}</span> : null}
              <p className="text-body text-text-2">{place}</p>
              <FavoriteButton code={unit.code} saved={false} />
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-5">
            {unit.is_verified ? <VerifiedStamp label={t("verified")} className="hidden sm:grid" /> : null}
            {/* The title block of the sheet: sheet number and area, as on a drawing. */}
            <dl className="grid grid-cols-[auto_auto] border border-line-strong text-small">
              <dt className="border-e border-b border-line-strong px-3 py-2 text-text-3">{t("code")}</dt>
              <dd className="border-b border-line-strong px-3 py-2 font-mono font-medium" dir="ltr">
                {unit.code}
              </dd>
              <dt className="border-e border-line-strong px-3 py-2 text-text-3">{t("place")}</dt>
              <dd className="px-3 py-2">{unit.location?.area?.name ?? "—"}</dd>
            </dl>
            {unit.is_verified ? <span className="inline-flex items-center gap-1.5 text-small font-semibold text-verified sm:hidden">✓ {t("verified")}</span> : null}
          </div>
        </header>

        {/* Phones read photos, then the money, then the sheet; wide screens keep the money beside the sheet. */}
        <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_24rem]">
          {photos.length > 0 ? (
            <section aria-label={t("photos")} className="flex min-w-0 flex-col gap-3 lg:col-start-1 lg:row-start-1">
              {/* eslint-disable-next-line @next/next/no-img-element -- fixed-size WebP from core */}
              <img src={photos[0]!.src} alt={unit.title} width={photos[0]!.width} height={photos[0]!.height} className="aspect-[4/3] w-full rounded-sm object-cover" fetchPriority="high" />
              {photos.length > 1 ? (
                <div className="grid grid-cols-3 gap-3 md:grid-cols-4">
                  {photos.slice(1).map((p, i) => (
                    <figure key={p.src} className="relative">
                      {/* eslint-disable-next-line @next/next/no-img-element -- fixed-size WebP from core */}
                      <img src={p.src} alt={t("photoN", { n: i + 2 })} width={p.width} height={p.height} loading="lazy" className="aspect-[4/3] w-full rounded-sm object-cover" />
                      {p.is_project_image ? <figcaption className="absolute end-2 bottom-2 rounded-[2px] bg-bg-0/85 px-2 py-0.5 text-micro text-text-2">{t("projectImage")}</figcaption> : null}
                    </figure>
                  ))}
                </div>
              ) : null}
              {photos[0]!.is_project_image ? <p className="text-micro text-text-3">{t("projectImage")}</p> : null}
            </section>
          ) : null}

          <aside className="flex flex-col gap-4 lg:sticky lg:top-24 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
            <div className="flex flex-col rounded-sm border border-line-strong bg-surface-1">
              <div className="flex flex-col gap-1 px-5 pt-5 pb-4">
                <span className="text-small text-text-2">{t("contractPrice")}</span>
                <span className="flex flex-wrap items-baseline gap-x-2">
                  <span className="text-[2rem] leading-tight font-semibold text-gold tabular sm:text-price" dir="ltr">
                    {money(m.total_price)}
                  </span>
                  <span className="text-body text-text-2">{t("currency")}</span>
                </span>
              </div>
              <MoneyRow label={t("similarToday")} value={money(m.market_price_estimate)} note={<span className="text-micro text-text-3">{t("similarHint")}</span>} />
              <MoneyRow
                label={t("toOwner")}
                value={money(m.paid_amount)}
                emphasis
                note={
                  <>
                    {m.accepts_less && m.lowest_accepted_amount ? <span className="text-small text-success">{t("ownerFrom", { amount: money(m.lowest_accepted_amount) })}</span> : null}
                    {m.to_developer_for_arrears ? <span className="text-small text-text-2">{t("arrears", { amount: money(m.to_developer_for_arrears) })}</span> : null}
                    <span className="text-micro text-text-3">{t("noOver")}</span>
                  </>
                }
              />
              <MoneyRow
                label={t("toDeveloper")}
                value={money(m.remaining_amount)}
                note={
                  m.installments_left && m.installment_amount ? (
                    <span className="text-micro text-text-3">
                      <span className="font-mono text-annotation" dir="ltr">
                        {m.installments_left} × {money(m.installment_amount)}
                      </span>{" "}
                      · {m.installment_frequency?.label}
                    </span>
                  ) : null
                }
              />
              <MoneyRow label={t("fee")} value={money(m.buyer_fee.amount)} note={<span className="text-micro text-text-3">{t("feeWhen")}</span>} />
              <div id="request" className="scroll-mt-24 border-t border-line p-5">
                {published ? (
                  <RequestPanel
                    key={startOpen ? "open" : "closed"}
                    code={unit.code}
                    path={path}
                    feeAmount={m.buyer_fee.amount}
                    consentText={text(buyerTexts, "buyer.fee_consent")}
                    note={text(buyerTexts, "buyer.request_note")}
                    startOpen={startOpen}
                  />
                ) : (
                  <p className="text-small text-text-2">{t("reservedNote")}</p>
                )}
              </div>
            </div>
            {unit.published_at ? (
              <p className="text-micro text-text-3">
                {t("published")} <span dir="ltr">{unit.published_at.slice(0, 10)}</span>
              </p>
            ) : null}
          </aside>

          <div className="flex min-w-0 flex-col gap-14 lg:col-start-1 lg:row-start-2">
            <section className="flex flex-col gap-6">
              <SectionHead letter="A" total={PARTS} title={t("specs")} />
              <dl className="grid grid-cols-2 border-t border-line md:grid-cols-4">
                <Spec label={t("size")} value={unit.size_m2 ? `${Number(unit.size_m2)} m²` : null} ltr />
                <Spec label={t("rooms")} value={unit.bedrooms !== null ? t("roomsValue", { bedrooms: unit.bedrooms, bathrooms: unit.bathrooms ?? 0 }) : null} />
                <Spec label={t("floor")} value={unit.floor ? (/^\d+$/.test(unit.floor) ? unit.floor : t(`floors.${unit.floor}`)) : null} />
                <Spec label={t("facade")} value={sides || null} />
                <Spec label={t("finishing")} value={unit.finishing?.label ?? null} />
                <Spec label={t("delivery")} value={[unit.delivery_status?.label, monthYear(unit.delivery_month, unit.delivery_year)].filter(Boolean).join(" · ") || null} />
                <Spec label={t("contractDate")} value={monthYear(unit.contract_month, unit.contract_year)} ltr />
                <Spec label={t("yearsLeft")} value={m.years_left !== null ? t("years", { count: m.years_left }) : null} />
                <Spec label={t("garden")} value={unit.garden_m2 ? `${Number(unit.garden_m2)} m²` : null} ltr />
                <Spec label={t("roof")} value={unit.roof_m2 ? `${Number(unit.roof_m2)} m²` : null} ltr />
              </dl>
              {unit.features.length > 0 ? (
                <ul className="flex flex-wrap gap-2">
                  {unit.features.map((f) => (
                    <li key={f.code} className="rounded-sm border border-line px-3 py-1 text-small text-text-2">
                      {f.name}
                    </li>
                  ))}
                </ul>
              ) : null}
            </section>

            <section className="flex flex-col gap-6">
              <SectionHead letter="B" total={PARTS} title={t("plan")} />
              {m.total_price !== null && m.paid_amount !== null ? (
                <PlanLine
                  paid={m.paid_amount}
                  remaining={m.remaining_amount ?? Math.max(0, m.total_price - m.paid_amount)}
                  total={m.total_price}
                  paidLabel={t("paidPct", { pct: m.paid_percent ?? 0 })}
                  remainingLabel={m.installments_left ? t("remainingCount", { count: m.installments_left, frequency: m.installment_frequency?.label ?? "" }) : t("remaining")}
                  totalLabel={t("contractPrice")}
                  currency={t("currency")}
                />
              ) : null}
              {unit.schedule.length > 0 ? (
                <div className="flex flex-col gap-2">
                  <ScheduleTable rows={unit.schedule.slice(0, 4)} t={t} head />
                  {unit.schedule.length > 4 ? (
                    <details className="text-small">
                      <summary className="flex min-h-control-md cursor-pointer items-center text-text-2 hover:text-text-1">
                        {t("moreInstallments", { count: unit.schedule.length - 4, last: unit.schedule[unit.schedule.length - 1]!.date })}
                      </summary>
                      <ScheduleTable rows={unit.schedule.slice(4)} t={t} />
                    </details>
                  ) : null}
                  <p className="text-micro text-text-3">{t("scheduleNote")}</p>
                </div>
              ) : null}
              <dl className="grid gap-3 text-small sm:grid-cols-2">
                <Spec label={t("annualExtra")} value={m.annual_extra_amount ? money(m.annual_extra_amount) : null} ltr plain />
                <Spec label={t("deliveryPayment")} value={m.delivery_payment ? money(m.delivery_payment) : null} ltr plain />
                <Spec label={t("maintenance")} value={m.maintenance_due ? money(m.maintenance_due) : null} ltr plain />
                <Spec label={t("overdue")} value={m.has_overdue && m.overdue_count ? t("overdueValue", { count: m.overdue_count, amount: money(m.overdue_amount) }) : null} plain />
              </dl>
            </section>

            <section className="flex flex-col gap-6">
              <SectionHead letter="C" total={PARTS} title={t("location")} />
              <div className="grid gap-6 border-t border-line pt-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                <dl className="flex flex-col">
                  <Row label={t("place")}>{[place, unit.location?.governorate?.name].filter(Boolean).join("، ") || "—"}</Row>
                  <Row label={t("compound")}>
                    {unit.compound ? (
                      <Link href={`/compounds/${unit.compound.slug}`} className="inline-flex min-h-control-md items-center text-text-1 underline underline-offset-4">
                        {unit.compound.name}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </Row>
                  <Row label={t("developer")}>{unit.developer?.name ?? "—"}</Row>
                </dl>
                <div className="flex justify-center sm:border-s sm:border-line sm:ps-8">
                  <NorthArrow facade={unit.facade ?? []} label={sides ? t("facadeLabel", { sides }) : t("noFacade")} />
                </div>
              </div>
              <p className="text-micro text-text-3">{t("noMap")}</p>
            </section>

            {unit.description ? (
              <section className="flex flex-col gap-3">
                <h2 className="text-h3">{t("description")}</h2>
                <p className="text-body whitespace-pre-line text-text-2">{unit.description}</p>
              </section>
            ) : null}

            {plan ? (
              <section className="flex flex-col gap-3">
                <h2 className="text-h3">{t("floorPlan")}</h2>
                {/* eslint-disable-next-line @next/next/no-img-element -- fixed-size WebP from core */}
                <img src={plan.src} alt={t("floorPlan")} width={plan.width} height={plan.height} loading="lazy" className="w-full rounded-sm bg-bg-1 object-contain" />
              </section>
            ) : null}
          </div>
        </div>
      </div>

      {/* Phones: what the buyer pays the owner and the request, always in reach (board 06.4). */}
      {published ? (
        <div data-sticky-bar className="fixed inset-x-0 bottom-0 z-30 border-t border-line-strong bg-bg-1/95 px-gutter-mobile py-3 backdrop-blur lg:hidden">
          <div className="mx-auto flex max-w-xl items-center justify-between gap-4">
            <div className="flex min-w-0 flex-col">
              <span className="text-micro text-text-3">{t("payNow")}</span>
              <span className="truncate text-lead font-semibold tabular">
                <span dir="ltr">{money(m.paid_amount)}</span> <span className="text-small font-normal text-text-2">{t("currency")}</span>
              </span>
              {m.accepts_less && m.lowest_accepted_amount ? <span className="text-micro text-success">{t("ownerFromShort", { amount: money(m.lowest_accepted_amount) })}</span> : null}
            </div>
            <Link
              href={`${path}?request=1#request`}
              className="inline-flex h-control-md shrink-0 items-center gap-2 rounded-sm bg-gold px-5 font-semibold text-on-gold hover:bg-gold-bright"
            >
              {tr("open")}
              <span aria-hidden="true" className="rtl:rotate-180">
                →
              </span>
            </Link>
          </div>
        </div>
      ) : null}
    </article>
  );
}

function Spec({ label, value, ltr = false, plain = false }: { label: string; value: string | null; ltr?: boolean; plain?: boolean }) {
  if (value === null || value === "") {
    return null;
  }

  return (
    <div className={`flex flex-col gap-1 ${plain ? "" : "border-b border-line py-4 pe-4"}`}>
      <dt className="text-small text-text-3">{label}</dt>
      <dd className="text-body font-medium text-text-1 tabular">
        <span dir={ltr ? "ltr" : undefined}>{value}</span>
      </dd>
    </div>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex min-h-control-md items-center justify-between gap-6 border-b border-line py-1">
      <dt className="text-small text-text-3">{label}</dt>
      <dd className="text-end text-body text-text-1">{children}</dd>
    </div>
  );
}

function ScheduleTable({ rows, t, head = false }: { rows: UnitDetail["schedule"]; t: (key: string) => string; head?: boolean }) {
  return (
    <table className="w-full text-small">
      {head ? (
        <thead>
          <tr className="border-b border-line text-text-3">
            <th className="py-2 text-start font-normal">#</th>
            <th className="py-2 text-start font-normal">{t("due")}</th>
            <th className="py-2 text-start font-normal">{t("amount")}</th>
            <th className="py-2 text-end font-normal">{t("status")}</th>
          </tr>
        </thead>
      ) : null}
      <tbody>
        {rows.map((row) => (
          <tr key={row.n} className="border-b border-line/60">
            {/* Cells keep the table direction; only the values run left to right. */}
            <td className="w-12 py-2 font-mono text-annotation text-text-3 tabular">
              <span dir="ltr">{String(row.n).padStart(2, "0")}</span>
            </td>
            <td className="py-2 font-mono text-annotation tabular">
              <span dir="ltr">{row.date}</span>
            </td>
            <td className="py-2 font-medium tabular">
              <span dir="ltr">{money(row.amount)}</span>
            </td>
            <td className={`py-2 text-end ${row.n === 1 ? "text-text-1" : "text-text-3"}`}>{row.n === 1 ? t("statusNext") : t("statusLater")}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/**
 * RealEstateListing (PLAN 2.5 structured data): name, URL, images, the amount to the owner as the offer, and the
 * region and locality only; never a street, a building or coordinates.
 */
function jsonLd(unit: UnitDetail, locale: "ar" | "en"): string {
  const data = {
    "@context": "https://schema.org",
    "@type": "RealEstateListing",
    name: unit.title,
    url: siteUrl() + localizedPath(`/units/${unit.slug}`, locale),
    image: unit.photos.filter((p) => p.kind === "photo").map((p) => p.src),
    datePosted: unit.published_at?.slice(0, 10),
    offers: { "@type": "Offer", price: unit.money.paid_amount, priceCurrency: "EGP", availability: unit.state === "published" ? "https://schema.org/InStock" : "https://schema.org/LimitedAvailability" },
    address: { "@type": "PostalAddress", addressCountry: "EG", addressRegion: unit.location?.governorate?.name, addressLocality: [unit.location?.district?.name, unit.location?.area?.name].filter(Boolean).join(", ") },
  };

  // "<" escaped so a text can never close the script tag.
  return JSON.stringify(data).replace(/</g, "\\u003c");
}
