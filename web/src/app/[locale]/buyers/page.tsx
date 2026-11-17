import type { Metadata } from "next";
import { pageLocale } from "@/lib/page";
import { TextPage, textPageMetadata } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/buyers";

export async function generateMetadata({ params }: PageProps<"/[locale]/buyers">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("buyers", PATH, locale);
}

export default async function BuyersPage({ params }: PageProps<"/[locale]/buyers">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="buyers"
      locale={locale}
      label="LK-B0"
      sections={[{body: "buyers.body"}]}
    />
  );
}
