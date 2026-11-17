import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import type { Locale } from "@/i18n/routing";
import { paragraphs, siteTexts, text } from "@/lib/core";
import { pageMetadata } from "@/lib/page";
import { ComingSoon } from "./ComingSoon";
import { ContentPage, Paragraphs } from "./ContentPage";

/**
 * A page made of site texts (PLAN 6): `{group}.title`, `{group}.intro`, then sections. A section is either a body
 * key (paragraphs) or a titled pair. FAQ pages list question and answer pairs.
 */
export type Section = { body: string } | { title: string; body: string } | { faq: [string, string][] };

export async function textPageMetadata(group: string, path: string, locale: Locale): Promise<Metadata> {
  const texts = await siteTexts(group, locale);

  return pageMetadata(path, locale, text(texts, `${group}.title`), text(texts, `${group}.intro`));
}

export async function TextPage({
  group,
  locale,
  label,
  sections,
  titleKey,
}: {
  group: string;
  locale: Locale;
  label: string;
  sections: Section[];
  titleKey?: string;
}) {
  const texts = await siteTexts(group, locale);
  const introKey = `${group}.intro`;

  return (
    <ContentPage
      label={label}
      title={text(texts, titleKey ?? `${group}.title`)}
      intro={texts[introKey] ? text(texts, introKey) : undefined}
    >
      {sections.map((section, i) => {
        if ("faq" in section) {
          return (
            <dl key={i} className="flex flex-col border-t-[1.5px] border-text-3">
              {section.faq.map(([q, a]) => (
                <div key={q} className="flex flex-col gap-2 border-b border-line py-6">
                  <dt className="text-h3 text-text-1">{text(texts, q)}</dt>
                  <dd>{text(texts, a)}</dd>
                </div>
              ))}
            </dl>
          );
        }
        if ("title" in section) {
          return (
            <section key={i} className="flex flex-col gap-3">
              <h2 className="text-h3 text-text-1">{text(texts, section.title)}</h2>
              <Paragraphs items={paragraphs(texts, section.body)} />
            </section>
          );
        }

        return <Paragraphs key={i} items={paragraphs(texts, section.body)} />;
      })}
    </ContentPage>
  );
}

/** Metadata for a page built in a later phase, titled from the navigation labels. */
export async function laterPageMetadata(navKey: string, path: string, locale: Locale): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "nav" });

  return pageMetadata(path, locale, t(navKey));
}

export async function LaterPage({ navKey, label }: { navKey: string; label: string }) {
  const t = await getTranslations("nav");

  return <ComingSoon title={t(navKey)} label={label} />;
}
