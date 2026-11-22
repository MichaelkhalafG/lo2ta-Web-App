import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { ShowMore } from "@/components/listing/ShowMore";
import { UnitCard } from "@/components/listing/UnitCard";
import { Link } from "@/i18n/navigation";
import { cardWords } from "@/lib/cardWords";
import { areaTree, siteTexts, text } from "@/lib/core";
import { filterQuery, INSTALLMENT_STEPS, isFiltered, money, OWNER_STEPS, parseFilters, SORTS, TYPES, type Filters } from "@/lib/listing";
import { unitList } from "@/lib/listingServer";
import { pageLocale } from "@/lib/page";
import { alternates } from "@/lib/seo";

const PATH = "/opportunities";

/**
 * Opportunities (board 06.2, PLAN 1.2, 2.5): the filter bar, then the cards, server-rendered, 24 a page with real page
 * URLs and "show more" (Michael 2026-11-22). Page n has its own canonical; any filter makes the page noindex, follow.
 */
export async function generateMetadata({ params, searchParams }: PageProps<"/[locale]/opportunities">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const filters = parseFilters(await searchParams);
  const texts = await siteTexts("opportunities", locale);
  const t = await getTranslations({ locale, namespace: "listing.page" });
  const path = filters.page ? `${PATH}?page=${filters.page}` : PATH;

  return {
    title: filters.page ? t("titlePage", { title: text(texts, "opportunities.title"), page: filters.page }) : text(texts, "opportunities.title"),
    description: text(texts, "opportunities.intro"),
    alternates: alternates(path, locale),
    ...(isFiltered(filters) ? { robots: { index: false, follow: true } } : {}),
  };
}

export default async function OpportunitiesPage({ params, searchParams }: PageProps<"/[locale]/opportunities">) {
  const locale = await pageLocale(params);
  const filters = parseFilters(await searchParams);
  const [texts, t, words, areas, list] = await Promise.all([
    siteTexts("opportunities", locale),
    getTranslations("listing.page"),
    cardWords(locale),
    areaTree(locale),
    unitList(filters, locale),
  ]);
  const listView = filters.view === "list";
  const query = filterQuery({ ...filters, page: undefined });
  const linkWith = (over: Partial<Filters>) => {
    const q = filterQuery(filters, { page: undefined, ...over });
    return q ? `${PATH}?${q}` : PATH;
  };

  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-16 md:pb-24">
      <div className="mx-auto flex max-w-7xl flex-col gap-8">
        <header className="flex flex-col gap-3">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            LK-P1
          </span>
          <h1 className="text-h1-m md:text-h1">{text(texts, "opportunities.title")}</h1>
          <p className="max-w-3xl text-body text-text-2">
            <strong className="text-text-1">{t("count", { count: list.meta.total })}</strong> {text(texts, "opportunities.intro")}
          </p>
        </header>

        <nav aria-label={t("types")} className="flex flex-wrap gap-2">
          {[undefined, ...TYPES].map((type) => (
            <Link
              key={type ?? "all"}
              href={linkWith({ type })}
              aria-current={filters.type === type ? "page" : undefined}
              className={`inline-flex min-h-11 items-center rounded-sm border px-4 text-body ${filters.type === type ? "border-text-1 bg-surface-2 text-text-1" : "border-line-strong text-text-2 hover:text-text-1"}`}
            >
              {t(`type.${type ?? "all"}`)}
            </Link>
          ))}
        </nav>

        <form method="get" action={locale === "en" ? `/en${PATH}` : PATH} className="grid gap-4 rounded-sm border border-line bg-surface-1 p-4 md:grid-cols-[repeat(4,minmax(0,1fr))_auto] md:items-end">
          {filters.type ? <input type="hidden" name="type" value={filters.type} /> : null}
          {listView ? <input type="hidden" name="view" value="list" /> : null}
          <label className="flex flex-col gap-2 text-small text-text-2">
            {t("area")}
            <select name="area" defaultValue={filters.area ?? ""} className="h-control-lg rounded-sm border border-line-strong bg-bg-1 px-3 text-body text-text-1">
              <option value="">{t("anyArea")}</option>
              {areas.map((gov) => (
                <optgroup key={gov.slug} label={gov.name}>
                  <option value={gov.slug}>{t("allOf", { name: gov.name })}</option>
                  {gov.areas.map((area) => [
                    <option key={area.slug} value={area.slug}>
                      {area.name}
                    </option>,
                    ...area.districts.map((d) => (
                      <option key={d.slug} value={d.slug}>
                        {"  "}
                        {d.name}
                      </option>
                    )),
                  ])}
                </optgroup>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-2 text-small text-text-2">
            {t("ownerMax")}
            <select name="owner_max" defaultValue={filters.owner_max ? String(filters.owner_max) : ""} className="h-control-lg rounded-sm border border-line-strong bg-bg-1 px-3 text-body text-text-1">
              <option value="">{t("anyAmount")}</option>
              {OWNER_STEPS.map((v) => (
                <option key={v} value={v}>
                  {t("upTo", { amount: money(v) })}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-2 text-small text-text-2">
            {t("installmentMax")}
            <select name="installment_max" defaultValue={filters.installment_max ? String(filters.installment_max) : ""} className="h-control-lg rounded-sm border border-line-strong bg-bg-1 px-3 text-body text-text-1">
              <option value="">{t("anyAmount")}</option>
              {INSTALLMENT_STEPS.map((v) => (
                <option key={v} value={v}>
                  {t("upTo", { amount: money(v) })}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-2 text-small text-text-2">
            {t("sort")}
            <select name="sort" defaultValue={filters.sort ?? "default"} className="h-control-lg rounded-sm border border-line-strong bg-bg-1 px-3 text-body text-text-1">
              {SORTS.map((s) => (
                <option key={s} value={s}>
                  {t(`sorts.${s}`)}
                </option>
              ))}
            </select>
          </label>
          <div className="flex flex-wrap items-center gap-3 md:flex-col md:items-stretch">
            <label className="flex min-h-11 items-center gap-2 text-body text-text-1">
              <input type="checkbox" name="verified" value="1" defaultChecked={filters.verified === true} className="size-4 accent-[var(--color-verified)]" />
              {t("verifiedOnly")}
            </label>
            <button type="submit" className="h-control-lg rounded-sm bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright">
              {t("apply")}
            </button>
          </div>
        </form>

        <div className="flex flex-wrap items-center justify-between gap-3">
          {isFiltered(filters) ? (
            <Link href={PATH} className="text-small text-text-2 underline underline-offset-4 hover:text-text-1">
              {t("clear")}
            </Link>
          ) : (
            <span />
          )}
          <div className="flex gap-1" role="group" aria-label={t("view")}>
            {(["grid", "list"] as const).map((v) => (
              <Link
                key={v}
                href={`${PATH}${filterQuery(filters, { view: v }) ? `?${filterQuery(filters, { view: v })}` : ""}`}
                aria-current={(listView ? "list" : "grid") === v ? "true" : undefined}
                className={`rounded-sm border px-3 py-1.5 text-small ${(listView ? "list" : "grid") === v ? "border-text-1 text-text-1" : "border-line text-text-3 hover:text-text-1"}`}
              >
                {t(`views.${v}`)}
              </Link>
            ))}
          </div>
        </div>

        {list.data.length === 0 ? (
          <p className="rounded-sm border border-line bg-surface-1 p-8 text-center text-body text-text-2">{t("empty")}</p>
        ) : (
          <div className={listView ? "flex flex-col gap-4" : "grid gap-5 sm:grid-cols-2 xl:grid-cols-3"}>
            {list.data.map((unit) => (
              <UnitCard key={unit.code} unit={unit} words={words} list={listView} />
            ))}
          </div>
        )}

        {list.meta.page < list.meta.last_page ? (
          <ShowMore
            key={`${query}-${list.meta.page}`}
            query={query}
            startPage={list.meta.page}
            lastPage={list.meta.last_page}
            words={words}
            list={listView}
            label={t("showMore")}
            loading={t("loading")}
          />
        ) : null}

        {list.meta.last_page > 1 ? (
          <nav aria-label={t("pages")} className="flex flex-wrap items-center justify-center gap-1 border-t border-line pt-6">
            {Array.from({ length: list.meta.last_page }, (_, i) => i + 1).map((n) => (
              <Link
                key={n}
                href={`${PATH}${filterQuery(filters, { page: n }) ? `?${filterQuery(filters, { page: n })}` : ""}`}
                aria-current={n === list.meta.page ? "page" : undefined}
                className={`grid size-11 place-items-center rounded-sm font-mono text-small ${n === list.meta.page ? "bg-text-1 text-bg-0" : "text-text-2 hover:bg-surface-2"}`}
              >
                {n}
              </Link>
            ))}
          </nav>
        ) : null}
      </div>
    </section>
  );
}
