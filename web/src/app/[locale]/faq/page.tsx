import type { Metadata } from "next";
import { pageLocale } from "@/lib/page";
import { TextPage, textPageMetadata } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/faq";

export async function generateMetadata({ params }: PageProps<"/[locale]/faq">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("faq", PATH, locale);
}

export default async function FaqPage({ params }: PageProps<"/[locale]/faq">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="faq"
      locale={locale}
      label="LK-A3"
      sections={[{ faq: Array.from({ length: 10 }, (_, i): [string, string] => [`faq.q${i + 1}`, `faq.a${i + 1}`]) }]}
    />
  );
}
