import type { Metadata } from "next";
import { siteTexts, text } from "@/lib/core";
import { pageLocale, pageMetadata } from "@/lib/page";
import { TextPage } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/terms";

export async function generateMetadata({ params }: PageProps<"/[locale]/terms">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const texts = await siteTexts("legal", locale);
  return pageMetadata(PATH, locale, text(texts, "legal.terms.title"));
}

export default async function TermsPage({ params }: PageProps<"/[locale]/terms">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="legal"
      locale={locale}
      label="LK-L2"
      titleKey="legal.terms.title"
      sections={[{body: "legal.terms.body"}]}
    />
  );
}
