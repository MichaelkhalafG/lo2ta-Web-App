import type { Locale } from "@/i18n/routing";
import { core } from "./account";
import { UNIT_ID, type SellerUnit, type SellerUnitSummary } from "./sell";

/* The seller wizard's reads from core (server only: the token and the server key never reach the browser). */

export async function sellerUnit(locale: Locale, id: string): Promise<SellerUnit | null> {
  if (!UNIT_ID.test(id)) {
    return null;
  }
  const result = await core<{ data: SellerUnit }>("GET", `me/units/${id}`, locale, { auth: true });

  return result.ok ? result.data.data : null;
}

export async function sellerUnits(locale: Locale): Promise<SellerUnitSummary[]> {
  const result = await core<{ data: SellerUnitSummary[] }>("GET", "me/units", locale, { auth: true });

  return result.ok ? result.data.data : [];
}
