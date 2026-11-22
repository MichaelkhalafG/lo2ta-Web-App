import { getTranslations } from "next-intl/server";
import type { Locale } from "@/i18n/routing";
import type { CardWords } from "@/components/listing/UnitCard";

/** The card's words in one language, read on the server and handed to cards rendered in the browser too. */
export async function cardWords(locale: Locale): Promise<CardWords> {
  const t = await getTranslations({ locale, namespace: "listing.card" });

  return {
    verified: t("verified"),
    deal: t("deal"),
    reserved: t("reserved"),
    projectImage: t("projectImage"),
    contractPrice: t("contractPrice"),
    similarToday: t("similarToday"),
    payOwner: t("payOwner"),
    remaining: t("remaining"),
    ownerFrom: t("ownerFrom"),
    installments: t("installments"),
    open: t("open"),
    delivery: t("delivery"),
    facade: t("facade"),
    sides: { north: t("sides.north"), east: t("sides.east"), south: t("sides.south"), west: t("sides.west") },
    egp: t("egp"),
    noPhoto: t("noPhoto"),
  };
}
