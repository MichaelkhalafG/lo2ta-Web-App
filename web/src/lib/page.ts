import type { Metadata } from "next";
import { hasLocale } from "next-intl";
import { setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";
import { routing, type Locale } from "@/i18n/routing";
import { alternates } from "./seo";

/** Resolves the [locale] segment of a page: 404 on an unknown language, and static rendering on a known one. */
export async function pageLocale(params: Promise<{ locale: string }>): Promise<Locale> {
  const { locale } = await params;
  if (!hasLocale(routing.locales, locale)) {
    notFound();
  }
  setRequestLocale(locale);

  return locale;
}

/** Title plus canonical and hreflang for a page path. */
export function pageMetadata(path: string, locale: Locale, title: string, description?: string): Metadata {
  return { title, ...(description ? { description } : {}), alternates: alternates(path, locale) };
}
