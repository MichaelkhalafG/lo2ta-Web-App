import type { Metadata } from "next";
import { pageLocale } from "@/lib/page";
import { TextPage, textPageMetadata } from "@/components/TextPage";

export const revalidate = 300;

const PATH = "/contact";

export async function generateMetadata({ params }: PageProps<"/[locale]/contact">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("contact", PATH, locale);
}

export default async function ContactPage({ params }: PageProps<"/[locale]/contact">) {
  const locale = await pageLocale(params);

  return (
    <TextPage
      group="contact"
      locale={locale}
      label="LK-A4"
      sections={[{body: "contact.body"}]}
    />
  );
}
