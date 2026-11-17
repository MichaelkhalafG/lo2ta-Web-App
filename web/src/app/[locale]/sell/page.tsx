import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/sell";

export async function generateMetadata({ params }: PageProps<"/[locale]/sell">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("sell", PATH, locale);
}

export default async function SellPage({ params }: PageProps<"/[locale]/sell">) {
  await pageLocale(params);

  return <LaterPage navKey="sell" label="LK-S0" />;
}
