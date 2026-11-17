import type { Metadata } from "next";
import { LaterPage, laterPageMetadata } from "@/components/TextPage";
import { pageLocale } from "@/lib/page";

/** Built in a later phase (PLAN 7); keeps the header link working until then. */
const PATH = "/login";

export async function generateMetadata({ params }: PageProps<"/[locale]/login">): Promise<Metadata> {
  const locale = await pageLocale(params);

  return laterPageMetadata("login", PATH, locale);
}

export default async function LoginPage({ params }: PageProps<"/[locale]/login">) {
  await pageLocale(params);

  return <LaterPage navKey="login" label="LK-U0" />;
}
