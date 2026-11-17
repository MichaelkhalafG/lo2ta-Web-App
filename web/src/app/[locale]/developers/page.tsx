import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/developers";

export async function generateMetadata({ params }: PageProps<"/[locale]/developers">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("developers", PATH, locale);
}

export default async function DevelopersPage({ params }: PageProps<"/[locale]/developers">) {
  await pageLocale(params);

  return <LaterPage navKey="developers" label="LK-P3" />;
}
