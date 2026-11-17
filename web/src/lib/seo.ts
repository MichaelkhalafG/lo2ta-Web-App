import { hreflang, routing, type Locale } from "@/i18n/routing";

/** Absolute site origin, server-side only. Set SITE_URL in the process for every build. */
export function siteUrl(): string {
  return (process.env.SITE_URL ?? "http://127.0.0.1:3000").trim().replace(/\/+$/, "");
}

/** Path for a locale under the "as-needed" scheme: Arabic at the root, English under /en. */
export function localizedPath(path: string, locale: Locale): string {
  const clean = path === "/" ? "" : path.replace(/\/+$/, "");
  return locale === routing.defaultLocale ? clean || "/" : `/${locale}${clean}`;
}

/** Canonical (self) plus hreflang for every language and x-default (Arabic). */
export function alternates(path: string, locale: Locale) {
  const languages: Record<string, string> = {};
  for (const l of routing.locales) {
    languages[hreflang[l]] = siteUrl() + localizedPath(path, l);
  }
  languages["x-default"] = siteUrl() + localizedPath(path, routing.defaultLocale);

  return { canonical: siteUrl() + localizedPath(path, locale), languages };
}
