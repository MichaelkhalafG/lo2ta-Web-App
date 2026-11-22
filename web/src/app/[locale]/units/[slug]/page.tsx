import type { Metadata } from "next";
import { notFound, permanentRedirect } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { FavoriteButton } from "@/components/account/FavoriteButton";
import { RequestPanel } from "@/components/listing/RequestPanel";
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

export default async function UnitPage({ params, searchParams }: PageProps<"/[locale]/units/[slug]">) {
  const { locale, slug, unit } = await load(params);
  if (!unit) {
    notFound();
  }
  if (unit.slug && unit.slug !== slug) {
    permanentRedirect(localizedPath(`/units/${unit.slug}`, locale));
  }
  const [t, buyerTexts] = await Promise.all([getTranslations("listing.unit"), siteTexts("buyer", locale)]);
  const path = `/units/${unit.slug ?? slug}`;
  const startOpen = (await searchParams).request === "1";
  const m = unit.money;
  const photos = unit.photos.filter((p) => p.kind === "photo");
  const plan = unit.photos.find((p) => p.kind === "floor_plan");
  const place = [unit.location?.district?.name, unit.location?.area?.name].filter(Boolean).join("، ");

  return (
    <article className="px-gutter-mobile pt-8 pb-20 md:px-gutter md:pt-12 md:pb-24">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLd(unit, locale) }} />
      <div className="mx-auto flex max-w-7xl flex-col gap-8">
        <nav aria-label={t("breadcrumb")} className="flex flex-wrap gap-2 text-small text-text-3">
          <Link href="/opportunities" className="hover:text-text-1">
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
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-h1-m md:text-h1">{unit.title}</h1>
              {unit.deal_tag ? <span className="rounded-[2px] bg-gold px-2.5 py-1 text-small font-bold text-on-gold">{t("deal")}</span> : null}
              {unit.state === "reserved" ? <span className="rounded-[2px] border border-line px-2.5 py-1 text-small text-text-2">{t("reserved")}</span> : null}
            </div>
            <p className="text-body text-text-2">{place}</p>
          </div>
          <div className="flex items-center gap-3">
            {unit.is_verified ? (
              <span className="inline-flex items-center gap-1.5 rounded-[2px] border border-verified px-3 py-1.5 text-small font-semibold text-verified">✓ {t("verified")}</span>
            ) : null}
            <FavoriteButton code={unit.code} saved={false} />
            <div className="flex flex-col items-end rounded-sm border border-line px-3 py-1.5">
              <span className="text-micro text-text-3">{t("code")}</span>
              <span className="font-mono text-small" dir="ltr">
                {unit.code}
              </span>
            </div>
          </div>
        </header>

        <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_24rem]">
          <div className="flex min-w-0 flex-col gap-10">
            {photos.length > 0 ? (
              <section aria-label={t("photos")} className="flex flex-col gap-3">
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

            <Block letter="A" title={t("specs")}>
              <dl className="grid grid-cols-2 gap-x-6 gap-y-4 md:grid-cols-3">
                <Spec label={t("size")} value={unit.size_m2 ? `${Number(unit.size_m2)} m²` : null} ltr />
                <Spec label={t("garden")} value={unit.garden_m2 ? `${Number(unit.garden_m2)} m²` : null} ltr />
                <Spec label={t("roof")} value={unit.roof_m2 ? `${Number(unit.roof_m2)} m²` : null} ltr />
                <Spec label={t("rooms")} value={unit.bedrooms !== null ? t("roomsValue", { bedrooms: unit.bedrooms, bathrooms: unit.bathrooms ?? 0 }) : null} />
                <Spec label={t("floor")} value={unit.floor ? (/^\d+$/.test(unit.floor) ? unit.floor : t(`floors.${unit.floor}`)) : null} />
                <Spec label={t("facade")} value={unit.facade?.map((s) => t(`sides.${s}`)).join(" ") ?? null} />
                <Spec label={t("finishing")} value={unit.finishing?.label ?? null} />
                <Spec label={t("delivery")} value={[unit.delivery_status?.label, monthYear(unit.delivery_month, unit.delivery_year)].filter(Boolean).join(" · ") || null} />
                <Spec label={t("contractDate")} value={monthYear(unit.contract_month, unit.contract_year)} ltr />
                <Spec label={t("yearsLeft")} value={m.years_left !== null ? t("years", { count: m.years_left }) : null} />
              </dl>
              {unit.features.length > 0 ? (
                <ul className="mt-6 flex flex-wrap gap-2">
                  {unit.features.map((f) => (
                    <li key={f.code} className="rounded-sm border border-line px-3 py-1 text-small text-text-2">
                      {f.name}
                    </li>
                  ))}
                </ul>
              ) : null}
            </Block>

            <Block letter="B" title={t("plan")}>
              <div className="grid gap-4 sm:grid-cols-3">
                <Figure label={t("paidPct", { pct: m.paid_percent ?? 0 })} value={money(m.paid_amount)} />
                <Figure label={m.installments_left ? t("remainingCount", { count: m.installments_left, frequency: m.installment_frequency?.label ?? "" }) : t("remaining")} value={money(m.remaining_amount)} />
                <Figure label={t("contractPrice")} value={money(m.total_price)} gold />
              </div>
              {unit.schedule.length > 0 ? (
                <div className="mt-6 flex flex-col gap-2">
                  <ScheduleTable rows={unit.schedule.slice(0, 4)} t={t} />
                  {unit.schedule.length > 4 ? (
                    <details className="text-small">
                      <summary className="cursor-pointer text-text-2 hover:text-text-1">
                        {t("moreInstallments", { count: unit.schedule.length - 4, last: unit.schedule[unit.schedule.length - 1]!.date })}
                      </summary>
                      <div className="mt-2">
                        <ScheduleTable rows={unit.schedule.slice(4)} t={t} />
                      </div>
                    </details>
                  ) : null}
                  <p className="text-micro text-text-3">{t("scheduleNote")}</p>
                </div>
              ) : null}
              <dl className="mt-6 grid gap-3 text-small sm:grid-cols-2">
                <Spec label={t("annualExtra")} value={m.annual_extra_amount ? money(m.annual_extra_amount) : null} ltr />
                <Spec label={t("deliveryPayment")} value={m.delivery_payment ? money(m.delivery_payment) : null} ltr />
                <Spec label={t("maintenance")} value={m.maintenance_due ? money(m.maintenance_due) : null} ltr />
                <Spec label={t("overdue")} value={m.has_overdue && m.overdue_count ? t("overdueValue", { count: m.overdue_count, amount: money(m.overdue_amount) }) : null} />
              </dl>
            </Block>

            <Block letter="C" title={t("location")}>
              <dl className="grid gap-4 sm:grid-cols-2">
                <Spec label={t("place")} value={[place, unit.location?.governorate?.name].filter(Boolean).join("، ") || null} />
                <div className="flex flex-col gap-1">
                  <dt className="text-small text-text-3">{t("compound")}</dt>
                  <dd>{unit.compound ? <Link href={`/compounds/${unit.compound.slug}`} className="text-text-1 underline underline-offset-4">{unit.compound.name}</Link> : "—"}</dd>
                </div>
                <Spec label={t("developer")} value={unit.developer?.name ?? null} />
              </dl>
              <p className="mt-4 text-micro text-text-3">{t("noMap")}</p>
            </Block>

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

          <aside className="flex flex-col gap-5 lg:sticky lg:top-24 lg:self-start">
            <div className="flex flex-col gap-4 rounded-sm border border-line bg-surface-1 p-5">
              <Figure label={t("contractPrice")} value={money(m.total_price)} gold />
              <Figure label={t("similarToday")} value={money(m.market_price_estimate)} hint={t("similarHint")} />
              <div className="flex flex-col gap-1 border-t border-line pt-4">
                <span className="text-small text-text-3">{t("toOwner")}</span>
                <span className="font-mono text-h2 tabular" dir="ltr">
                  {money(m.paid_amount)}
                </span>
                {m.accepts_less && m.lowest_accepted_amount ? (
                  <span className="text-small text-success">{t("ownerFrom", { amount: money(m.lowest_accepted_amount) })}</span>
                ) : null}
                {m.to_developer_for_arrears ? <span className="text-small text-text-2">{t("arrears", { amount: money(m.to_developer_for_arrears) })}</span> : null}
                <span className="text-micro text-text-3">{t("noOver")}</span>
              </div>
              <div className="flex flex-col gap-1 border-t border-line pt-4">
                <span className="text-small text-text-3">{t("toDeveloper")}</span>
                <span className="font-mono text-h3 tabular" dir="ltr">
                  {money(m.remaining_amount)}
                </span>
                {m.installments_left && m.installment_amount ? (
                  <span className="text-small text-text-2">
                    <span dir="ltr">
                      {m.installments_left} × {money(m.installment_amount)}
                    </span>{" "}
                    · {m.installment_frequency?.label}
                  </span>
                ) : null}
              </div>
              <div className="flex flex-col gap-1 border-t border-line pt-4">
                <span className="text-small text-text-3">{t("fee")}</span>
                <span className="font-mono text-body tabular" dir="ltr">
                  {money(m.buyer_fee.amount)}
                </span>
                <span className="text-micro text-text-3">{t("feeWhen")}</span>
              </div>
            </div>

            {unit.state === "published" ? (
              <RequestPanel
                code={unit.code}
                path={path}
                feeAmount={m.buyer_fee.amount}
                consentText={text(buyerTexts, "buyer.fee_consent")}
                note={text(buyerTexts, "buyer.request_note")}
                startOpen={startOpen}
              />
            ) : (
              <p className="rounded-sm border border-line px-4 py-3 text-small text-text-2">{t("reservedNote")}</p>
            )}
            {unit.published_at ? (
              <p className="text-micro text-text-3">
                {t("published")} <span dir="ltr">{unit.published_at.slice(0, 10)}</span>
              </p>
            ) : null}
          </aside>
        </div>
      </div>
    </article>
  );
}

function Block({ letter, title, children }: { letter: string; title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-5 border-t border-line pt-6">
      <header className="flex items-baseline gap-3">
        <span className="font-mono text-annotation text-text-3" dir="ltr">
          {letter}
        </span>
        <h2 className="text-h3">{title}</h2>
      </header>
      {children}
    </section>
  );
}

function Spec({ label, value, ltr = false }: { label: string; value: string | null; ltr?: boolean }) {
  if (value === null || value === "") {
    return null;
  }

  return (
    <div className="flex flex-col gap-1">
      <dt className="text-small text-text-3">{label}</dt>
      <dd className="text-body text-text-1" dir={ltr ? "ltr" : undefined}>
        {value}
      </dd>
    </div>
  );
}

function Figure({ label, value, gold = false, hint }: { label: string; value: string; gold?: boolean; hint?: string }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-small text-text-3">{label}</span>
      <span className={`font-mono text-h3 tabular ${gold ? "text-gold" : "text-text-1"}`} dir="ltr">
        {value}
      </span>
      {hint ? <span className="text-micro text-text-3">{hint}</span> : null}
    </div>
  );
}

function ScheduleTable({ rows, t }: { rows: UnitDetail["schedule"]; t: (key: string) => string }) {
  return (
    <table className="w-full text-small">
      <thead>
        <tr className="border-b border-line text-text-3">
          <th className="py-2 text-start font-normal">#</th>
          <th className="py-2 text-start font-normal">{t("due")}</th>
          <th className="py-2 text-end font-normal">{t("amount")}</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => (
          <tr key={row.n} className="border-b border-line/60">
            {/* Cells keep the table direction; only the values run left to right. */}
            <td className="py-2 font-mono tabular">
              <span dir="ltr">{row.n}</span>
            </td>
            <td className="py-2 font-mono tabular">
              <span dir="ltr">{row.date}</span>
            </td>
            <td className="py-2 text-end font-mono tabular">
              <span dir="ltr">{money(row.amount)}</span>
            </td>
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
