import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { ContactForm } from "@/components/forms/PublicForms";
import { TextPage, textPageMetadata } from "@/components/TextPage";
import { publicSettings } from "@/lib/core";
import { pageLocale } from "@/lib/page";

export const revalidate = 300;

const PATH = "/contact";

export async function generateMetadata({ params }: PageProps<"/[locale]/contact">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("contact", PATH, locale);
}

/** Contact (PLAN 1.2): the client's contact facts, then the contact form (PLAN 2.6). */
export default async function ContactPage({ params }: PageProps<"/[locale]/contact">) {
  const locale = await pageLocale(params);
  const [settings, t] = await Promise.all([publicSettings(locale), getTranslations("forms")]);
  const max = typeof settings.form_message_max === "number" ? settings.form_message_max : 2000;

  return (
    <>
      <TextPage group="contact" locale={locale} label="LK-A4" sections={[{ body: "contact.body" }]} />
      <section className="px-gutter-mobile pb-20 md:px-gutter md:pb-24">
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
          <h2 className="text-h3">{t("contactTitle")}</h2>
          <ContactForm max={max} />
        </div>
      </section>
    </>
  );
}
