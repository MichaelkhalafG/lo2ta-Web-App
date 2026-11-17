import type { Locale } from "@/i18n/routing";

/*
 * Server-side reads from the core API (handover 2.2): the server key travels in X-Api-Key and never reaches the
 * browser (no NEXT_PUBLIC_ prefix). Answers are cached with ISR for 5 minutes. A missing setting or a failed call
 * throws, so a build or a revalidation fails loudly instead of rendering an empty page.
 */

const REVALIDATE_SECONDS = 300;

function coreConfig(): { base: string; key: string } {
  const base = (process.env.CORE_API_URL ?? "").trim().replace(/\/+$/, "");
  const key = (process.env.CORE_API_KEY ?? "").trim();
  if (base === "" || key === "") {
    throw new Error("CORE_API_URL and CORE_API_KEY must be set for the Next server.");
  }

  return { base, key };
}

async function coreGet<T>(path: string, locale: Locale, tags: string[]): Promise<T> {
  const { base, key } = coreConfig();
  const separator = path.includes("?") ? "&" : "?";
  const response = await fetch(`${base}/api/v1/${path}${separator}locale=${locale}`, {
    headers: { "X-Api-Key": key, Accept: "application/json" },
    next: { revalidate: REVALIDATE_SECONDS, tags },
  });
  if (!response.ok) {
    throw new Error(`Core API ${path} answered ${response.status}.`);
  }

  return (await response.json()) as T;
}

/** Site texts of one group, keyed by text key. */
export type Texts = Record<string, string | null>;

export async function siteTexts(group: string, locale: Locale): Promise<Texts> {
  const body = await coreGet<{ data: Texts }>(`site-texts?group=${encodeURIComponent(group)}`, locale, [
    `site-texts:${group}`,
  ]);

  return body.data;
}

export type AreaTree = {
  slug: string;
  name: string;
  areas: { slug: string; code: string | null; name: string; districts: { slug: string; name: string }[] }[];
}[];
export type CompoundItem = { slug: string; name: string; developer: string | null; area: string | null };
export type Choices = Record<"main_types" | "installment_frequencies" | "delivery_preferences", { code: string; label: string }[]>;

/** Governorate > area > district, active only (PLAN 3.3). */
export async function areaTree(locale: Locale): Promise<AreaTree> {
  return (await coreGet<{ data: AreaTree }>("areas", locale, ["places"])).data;
}

export async function compoundList(locale: Locale): Promise<CompoundItem[]> {
  return (await coreGet<{ data: CompoundItem[] }>("compounds", locale, ["places"])).data;
}

export async function choices(locale: Locale): Promise<Choices> {
  return (await coreGet<{ data: Choices }>("choices", locale, ["choices"])).data;
}

export async function publicSettings(locale: Locale): Promise<Record<string, number | boolean | string>> {
  return (await coreGet<{ data: Record<string, number | boolean | string> }>("settings", locale, ["settings"])).data;
}

/** One text, or its key when missing, so a gap shows on the page instead of disappearing. */
export function text(texts: Texts, key: string): string {
  return texts[key] ?? key;
}

/** Paragraphs of a multi-paragraph text (separated by a blank line). */
export function paragraphs(texts: Texts, key: string): string[] {
  return text(texts, key)
    .split(/\n\s*\n/)
    .map((p) => p.trim())
    .filter((p) => p !== "");
}
