import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/calculator";

export async function generateMetadata({ params }: PageProps<"/[locale]/calculator">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("calculator", PATH, locale);
}

export default async function CalculatorPage({ params }: PageProps<"/[locale]/calculator">) {
  await pageLocale(params);

  return <LaterPage navKey="calculator" label="LK-T1" />;
}
