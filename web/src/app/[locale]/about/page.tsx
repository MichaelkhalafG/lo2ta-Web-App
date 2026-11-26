import type { Metadata } from "next";
import { Body, ContentPage } from "@/components/ContentPage";
import { textPageMetadata } from "@/components/TextPage";
import { siteTexts, text } from "@/lib/core";
import { pageLocale } from "@/lib/page";

export const revalidate = 300;

const PATH = "/about";

const FOUNDERS = ["tawfik", "enas"] as const;

export async function generateMetadata({ params }: PageProps<"/[locale]/about">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("about", PATH, locale);
}

/**
 * About (PLAN 1.2): the client's own texts (Michael 2026-11-26). The platform story gives the intro and "how Lokta
 * works"; then each founder's story, first person, under their name and role. Every text is a site text, edited in
 * the dashboard, and the page waits for legal review before launch.
 */
export default async function AboutPage({ params }: PageProps<"/[locale]/about">) {
  const locale = await pageLocale(params);
  const texts = await siteTexts("about", locale);

  return (
    <ContentPage label="LK-A2" title={text(texts, "about.title")} intro={text(texts, "about.intro")}>
      <Body text={text(texts, "about.story")} />

      <section aria-labelledby="about-how" className="flex flex-col gap-4">
        <h2 id="about-how" className="text-h3 text-text-1">
          {text(texts, "about.how_title")}
        </h2>
        <Body text={text(texts, "about.how")} headingLevel={3} />
      </section>

      <section aria-labelledby="about-founders" className="flex flex-col gap-10 border-t-[1.5px] border-text-3 pt-8">
        <h2 id="about-founders" className="text-h3 text-text-1">
          {text(texts, "about.founders_title")}
        </h2>
        {FOUNDERS.map((key) => (
          <article key={key} aria-labelledby={`founder-${key}`} className="flex flex-col gap-4 border-t border-line pt-6 first-of-type:border-t-0 first-of-type:pt-0">
            <header className="flex flex-col gap-1">
              <h3 id={`founder-${key}`} className="text-h3 text-text-1">
                {text(texts, `about.${key}.name`)}
              </h3>
              <p className="text-small text-text-3">{text(texts, `about.${key}.role`)}</p>
            </header>
            <p className="text-lead text-text-1">{text(texts, `about.${key}.title`)}</p>
            <Body text={text(texts, `about.${key}.body`)} headingLevel={4} />
          </article>
        ))}
      </section>
    </ContentPage>
  );
}
