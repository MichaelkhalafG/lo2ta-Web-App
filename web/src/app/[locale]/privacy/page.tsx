import type { Metadata } from "next";
import { siteTexts, text } from "@/lib/core";
import { pageLocale, pageMetadata } from "@/lib/page";
import { TextPage } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/privacy";

export async function generateMetadata({ params }: PageProps<"/[locale]/privacy">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const texts = await siteTexts("legal", locale);
  return pageMetadata(PATH, locale, text(texts, "legal.privacy.title"));
}

export default async function PrivacyPage({ params }: PageProps<"/[locale]/privacy">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="legal"
      locale={locale}
      label="LK-L1"
      titleKey="legal.privacy.title"
      sections={[{body: "legal.privacy.body"}, {body: "legal.privacy.id_retention"}]}
    />
  );
}
