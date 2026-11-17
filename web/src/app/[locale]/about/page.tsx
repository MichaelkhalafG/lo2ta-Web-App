import type { Metadata } from "next";
import { pageLocale } from "@/lib/page";
import { TextPage, textPageMetadata } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/about";

export async function generateMetadata({ params }: PageProps<"/[locale]/about">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("about", PATH, locale);
}

export default async function AboutPage({ params }: PageProps<"/[locale]/about">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="about"
      locale={locale}
      label="LK-A2"
      sections={[{body: "about.body"}]}
    />
  );
}
