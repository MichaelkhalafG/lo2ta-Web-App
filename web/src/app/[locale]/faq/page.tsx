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
      sections={[{ faq: [["faq.q1", "faq.a1"], ["faq.q2", "faq.a2"], ["faq.q3", "faq.a3"], ["faq.q4", "faq.a4"]] }]}
    />
  );
}
