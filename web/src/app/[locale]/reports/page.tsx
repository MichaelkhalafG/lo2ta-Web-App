import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/reports";

export async function generateMetadata({ params }: PageProps<"/[locale]/reports">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("reports", PATH, locale);
}

export default async function ReportsPage({ params }: PageProps<"/[locale]/reports">) {
  await pageLocale(params);

  return <LaterPage navKey="reports" label="LK-T2" />;
}
