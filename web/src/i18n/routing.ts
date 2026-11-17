import { defineRouting } from "next-intl/routing";

/**
 * Arabic first. PROVISIONAL URL scheme until Michael decides (docs/PLAN.md, open questions):
 * Arabic at the root ("/..."), English under "/en/...".
 * The URL alone decides the language: no cookie, no Accept-Language redirect (handover 5.1).
 */
export const routing = defineRouting({
  locales: ["ar", "en"],
  defaultLocale: "ar",
  localePrefix: "as-needed",
  localeDetection: false,
  localeCookie: false,
  alternateLinks: false,
});

export type Locale = (typeof routing.locales)[number];

export const localeDir: Record<Locale, "rtl" | "ltr"> = { ar: "rtl", en: "ltr" };
export const hreflang: Record<Locale, string> = { ar: "ar-EG", en: "en" };
