import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/opportunities";

export async function generateMetadata({ params }: PageProps<"/[locale]/opportunities">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("opportunities", PATH, locale);
}

export default async function OpportunitiesPage({ params }: PageProps<"/[locale]/opportunities">) {
  await pageLocale(params);

  return <LaterPage navKey="opportunities" label="LK-P1" />;
}
