"use server";

import { routing, type Locale } from "@/i18n/routing";
import { parseFilters, type UnitCard } from "../listing";
import { unitList } from "../listingServer";

/*
 * "Show more" on the opportunities page (Michael 2026-11-22: numbered pages plus show more). The query arrives from
 * the browser, so it goes through the same parser as the page: unknown values are dropped.
 */
export async function moreUnitsAction(query: string, page: number, locale: string): Promise<{ cards: UnitCard[]; lastPage: number }> {
  const filters = parseFilters(Object.fromEntries(new URLSearchParams(query.slice(0, 500))));
  const safePage = Number.isInteger(page) && page >= 2 && page <= 1000 ? page : 2;
  const result = await unitList({ ...filters, page: safePage }, (locale === "en" ? "en" : routing.defaultLocale) as Locale);

  return { cards: result.data, lastPage: result.meta.last_page };
}
