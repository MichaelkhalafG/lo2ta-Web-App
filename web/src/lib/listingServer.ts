import type { Locale } from "@/i18n/routing";
import { coreFind } from "./core";
import { filterQuery, type CompoundIndexItem, type CompoundPage, type Filters, type ListMeta, type UnitCard, type UnitDetail } from "./listing";

/*
 * Server reads for the public listing pages. Cached for 60 seconds (PLAN D23): a newly published unit shows within a
 * minute, and every visitor shares one answer per filter set.
 */

const LISTING_SECONDS = 60;

export async function unitList(filters: Filters, locale: Locale, perPage?: number): Promise<{ data: UnitCard[]; meta: ListMeta }> {
  const query = filterQuery({ ...filters, view: undefined }, {});
  const path = `units?${query}${perPage ? `${query ? "&" : ""}per_page=${perPage}` : ""}`;
  const body = await coreFind<{ data: UnitCard[]; meta: ListMeta }>(path, locale, ["units"], LISTING_SECONDS);

  return body ?? { data: [], meta: { page: 1, last_page: 1, per_page: 24, total: 0 } };
}

export async function unitByCode(code: string, locale: Locale): Promise<UnitDetail | null> {
  if (!/^LK-[A-Z0-9]{1,4}-\d{4,6}$/.test(code)) {
    return null;
  }

  return (await coreFind<{ data: UnitDetail }>(`units/${code}`, locale, ["units", `unit:${code}`], LISTING_SECONDS))?.data ?? null;
}

export async function compoundIndex(locale: Locale): Promise<CompoundIndexItem[]> {
  return (await coreFind<{ data: CompoundIndexItem[] }>("compound-pages", locale, ["places", "units"], LISTING_SECONDS))?.data ?? [];
}

export async function compoundPage(slug: string, locale: Locale): Promise<CompoundPage | null> {
  if (!/^[a-z0-9-]{1,96}$/.test(slug)) {
    return null;
  }

  return (await coreFind<{ data: CompoundPage }>(`compound-pages/${slug}`, locale, ["places", "units"], LISTING_SECONDS))?.data ?? null;
}
