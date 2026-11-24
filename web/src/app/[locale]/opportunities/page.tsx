import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { FeaturedCard } from "@/components/listing/FeaturedCard";
import { FilterForm } from "@/components/listing/FilterForm";
import { ShowMore } from "@/components/listing/ShowMore";
import { UnitCard } from "@/components/listing/UnitCard";
import { Link } from "@/i18n/navigation";
import { cardWords } from "@/lib/cardWords";
import { areaTree, siteTexts, text } from "@/lib/core";
import { filterQuery, INSTALLMENT_STEPS, isFiltered, money, OWNER_STEPS, panelFilterCount, parseFilters, placeName, showsFeatured, SORTS, TYPES } from "@/lib/listing";
import { unitList } from "@/lib/listingServer";
import { pageLocale } from "@/lib/page";
import { alternates } from "@/lib/seo";

const PATH = "/opportunities";

/**
 * Opportunities (board 06.2, PLAN 1.2, 2.5; layout approved 2026-11-24): one filter bar, the week's لقطة wide on
 * page 1 of the plain list, then the cards, server-rendered, 24 a page with real page URLs and "show more" (Michael
 * 2026-11-22). Page n has its own canonical; any filter makes the page noindex, follow.
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
  const featuredOn = showsFeatured(filters);
  const [texts, t, words, areas, list, deals] = await Promise.all([
    siteTexts("opportunities", locale),
    getTranslations("listing.page"),
    cardWords(locale),
    areaTree(locale),
    unitList(filters, locale),
    featuredOn ? unitList({ deal: true }, locale, 1) : Promise.resolve(null),
  ]);
  const listView = filters.view === "list";
  const query = filterQuery({ ...filters, page: undefined });
  const featured = deals?.data[0] ?? null;
  const cards = featured ? list.data.filter((u) => u.code !== featured.code) : list.data;
  const place = placeName(areas, filters.area);
  const panelCount = panelFilterCount(filters);
  const viewHref = (v: "grid" | "list") => {
    const q = filterQuery(filters, { view: v, page: undefined });
    return q ? `${PATH}?${q}` : PATH;
  };
  // One cell of the filter bar: on phones a labelled field in the panel, on wide screens a title-block cell.
  const cell = "flex flex-col gap-1 text-small text-text-2 md:shrink-0 md:justify-center md:gap-0 md:border-s md:border-line md:px-3 md:pt-1.5";
  const select =
    "h-control-lg w-full rounded-sm border border-line-strong bg-bg-1 px-3 text-body text-text-1 md:h-control-md md:border-0 md:bg-transparent md:px-0 md:text-[0.875rem] md:font-medium";

  return (
    <section className="px-gutter-mobile pt-10 pb-20 md:px-gutter md:pt-16 md:pb-24">
      <div className="mx-auto flex max-w-7xl flex-col gap-6">
        <header className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,30rem)] lg:items-end lg:gap-8">
          <h1 className="text-h1-m md:text-h1 xl:text-display">{text(texts, "opportunities.title")}</h1>
          <p className="text-body text-text-2">
            <strong className="text-text-1">{t("count", { count: list.meta.total })}</strong> {text(texts, "opportunities.intro")}
          </p>
        </header>

        {/* One filter bar read like a title block; on phones the type tabs scroll and the rest sits behind "تصفية". */}
        <FilterForm
          action={locale === "en" ? `/en${PATH}` : PATH}
          className="group flex flex-col gap-3 md:flex-row md:flex-wrap md:items-stretch md:gap-0 md:rounded-sm md:border md:border-line-strong"
        >
          {listView ? <input type="hidden" name="view" value="list" /> : null}
          <fieldset className="flex overflow-x-auto rounded-sm border border-line-strong [scrollbar-width:none] md:order-1 md:rounded-none md:border-0">
            <legend className="sr-only">{t("types")}</legend>
            {[undefined, ...TYPES].map((type) => (
              <label
                key={type ?? "all"}
                className="flex min-h-control-lg shrink-0 cursor-pointer items-center px-4 text-body whitespace-nowrap text-text-2 hover:text-text-1 has-[:checked]:bg-text-1 has-[:checked]:font-semibold has-[:checked]:text-bg-0 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-text-1 md:px-2.5"
              >
                <input type="radio" name="type" value={type ?? ""} defaultChecked={filters.type === type} className="sr-only" />
                {t(`type.${type ?? "all"}`)}
              </label>
            ))}
          </fieldset>

          <input type="checkbox" id="filters-toggle" className="peer/filters sr-only" aria-controls="filters-panel" />
          <div className="grid grid-cols-2 rounded-sm border border-line-strong md:contents">
            <label
              htmlFor="filters-toggle"
              className="flex min-h-control-lg cursor-pointer items-center gap-2 px-4 text-body font-semibold text-text-1 md:hidden"
            >
              {t("filters")}
              {panelCount > 0 ? (
                <span className="font-mono text-annotation text-text-2" dir="ltr">
                  · {panelCount}
                </span>
              ) : null}
              <svg aria-hidden="true" viewBox="0 0 10 6" className="ms-auto h-1.5 w-2.5 fill-none stroke-text-3" strokeWidth="1.5">
                <path d="M1 1l4 4 4-4" />
              </svg>
            </label>
            <label className={`${cell} border-s border-line px-4 pt-1.5 md:order-7 md:min-w-0 md:basis-28 md:flex-1`}>
              <span className="text-micro text-text-3">{t("sort")}</span>
              <select name="sort" defaultValue={filters.sort ?? "default"} className="min-h-control-md bg-transparent text-small font-medium text-text-1 md:text-[0.875rem]">
                {SORTS.map((s) => (
                  <option key={s} value={s}>
                    {t(`sorts.${s}`)}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <div id="filters-panel" data-panel className="hidden flex-col gap-4 rounded-sm border border-line-strong bg-surface-1 p-4 peer-checked/filters:flex md:contents">
            <label className={`${cell} md:order-2 md:min-w-0 md:basis-36 md:flex-1`}>
              <span className="md:text-micro md:text-text-3">{t("area")}</span>
              <select name="area" defaultValue={filters.area ?? ""} className={select}>
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
            <label className={`${cell} md:order-3 md:min-w-0 md:basis-28 md:flex-1`}>
              <span className="md:text-micro md:text-text-3">{t("ownerMax")}</span>
              <select name="owner_max" defaultValue={filters.owner_max ? String(filters.owner_max) : ""} className={select}>
                <option value="">{t("anyAmount")}</option>
                {OWNER_STEPS.map((v) => (
                  <option key={v} value={v}>
                    {t("upTo", { amount: money(v) })}
                  </option>
                ))}
              </select>
            </label>
            <label className={`${cell} md:order-4 md:min-w-0 md:basis-28 md:flex-1`}>
              <span className="md:text-micro md:text-text-3">{t("installmentMax")}</span>
              <select name="installment_max" defaultValue={filters.installment_max ? String(filters.installment_max) : ""} className={select}>
                <option value="">{t("anyAmount")}</option>
                {INSTALLMENT_STEPS.map((v) => (
                  <option key={v} value={v}>
                    {t("upTo", { amount: money(v) })}
                  </option>
                ))}
              </select>
            </label>
            {/* "Verified only" is a cyan switch: verified is never gold. */}
            <label className="flex min-h-control-md cursor-pointer items-center gap-3 text-body font-semibold whitespace-nowrap text-verified md:order-5 md:border-s md:border-line md:px-4">
              <input type="checkbox" name="verified" value="1" defaultChecked={filters.verified === true} className="peer sr-only" />
              <span
                aria-hidden="true"
                className="relative h-5 w-9 shrink-0 rounded-[2px] border border-verified bg-bg-1 peer-checked:bg-verified-fill peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-text-1 after:absolute after:inset-y-[3px] after:start-[3px] after:w-3 after:bg-text-3 peer-checked:after:start-auto peer-checked:after:end-[3px] peer-checked:after:bg-verified"
              />
              {t("verifiedOnly")}
            </label>
            <button type="submit" className="inline-flex h-control-lg items-center justify-center rounded-sm bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright md:hidden">
              {t("showResults")}
            </button>
          </div>

          
          {/* Without JavaScript the bar needs a button; with it, changes apply at once. */}
          <button type="submit" className="hidden items-center bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright md:order-9 md:inline-flex group-data-[js]:md:hidden">
            {t("apply")}
          </button>
        </FilterForm>

        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-small text-text-2">
          <p>
            {t("units", { count: list.meta.total })}
            {place ? ` ${t("inPlace", { place })}` : ""}
            {filters.type ? ` · ${t(`type.${filters.type}`)}` : ""}
          </p>
          <div className="flex items-center gap-4">
            {isFiltered(filters) ? (
              <Link href={PATH} className="inline-flex min-h-control-md items-center text-text-2 underline underline-offset-4 hover:text-text-1">
                {t("clear")}
              </Link>
            ) : null}
            {list.meta.last_page > 1 ? <span className="text-text-3">{t("pageOf", { page: list.meta.page, last: list.meta.last_page })}</span> : null}
            <nav aria-label={t("view")} className="hidden overflow-hidden rounded-sm border border-line md:flex">
              {(["grid", "list"] as const).map((v) => (
                <Link
                  key={v}
                  href={viewHref(v)}
                  aria-current={(listView ? "list" : "grid") === v ? "true" : undefined}
                  className={`grid min-h-control-md place-items-center px-3 text-small ${(listView ? "list" : "grid") === v ? "bg-surface-2 text-text-1" : "text-text-3 hover:text-text-1"}`}
                >
                  {t(`views.${v}`)}
                </Link>
              ))}
            </nav>
          </div>
        </div>

        {featured ? <FeaturedCard unit={featured} words={words} label={t("weekDeal")} /> : null}

        {list.data.length === 0 ? (
          <p className="rounded-sm border border-line bg-surface-1 p-8 text-center text-body text-text-2">{t("empty")}</p>
        ) : (
          <div className={listView ? "flex flex-col gap-4" : "grid gap-5 sm:grid-cols-2 xl:grid-cols-3"}>
            {cards.map((unit) => (
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
