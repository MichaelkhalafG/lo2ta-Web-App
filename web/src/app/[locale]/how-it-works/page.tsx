import type { Metadata } from "next";
import { pageLocale } from "@/lib/page";
import { TextPage, textPageMetadata } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/how-it-works";

export async function generateMetadata({ params }: PageProps<"/[locale]/how-it-works">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("how", PATH, locale);
}

export default async function HowItWorksPage({ params }: PageProps<"/[locale]/how-it-works">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="how"
      locale={locale}
      label="LK-A1"
      sections={[
        { title: "how.seller_title", body: "how.seller_body" },
        { title: "how.buyer_title", body: "how.buyer_body" },
        { title: "how.deal_title", body: "how.deal_body" },
      ]}
    />
  );
}
