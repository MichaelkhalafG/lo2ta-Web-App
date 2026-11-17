import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/compounds";

export async function generateMetadata({ params }: PageProps<"/[locale]/compounds">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("compounds", PATH, locale);
}

export default async function CompoundsPage({ params }: PageProps<"/[locale]/compounds">) {
  await pageLocale(params);

  return <LaterPage navKey="compounds" label="LK-P2" />;
}
