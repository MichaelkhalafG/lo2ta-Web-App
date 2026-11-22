import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { PartnershipForm } from "@/components/forms/PublicForms";
import { TextPage, textPageMetadata } from "@/components/TextPage";
import { publicSettings } from "@/lib/core";
import { pageLocale } from "@/lib/page";

export const revalidate = 300;

const PATH = "/developers";

export async function generateMetadata({ params }: PageProps<"/[locale]/developers">): Promise<Metadata> {
  const locale = await pageLocale(params);
  return textPageMetadata("developers", PATH, locale);
}

/** For developers (PLAN 1.2, 2.5 developer row, 5.1 point 10): the page text and the partnership form, no unit data. */
export default async function DevelopersPage({ params }: PageProps<"/[locale]/developers">) {
  const locale = await pageLocale(params);
  const [settings, t] = await Promise.all([publicSettings(locale), getTranslations("forms")]);
  const max = typeof settings.form_message_max === "number" ? settings.form_message_max : 2000;

  return (
    <>
      <TextPage group="developers" locale={locale} label="LK-P3" sections={[{ body: "developers.body" }]} />
      <section className="px-gutter-mobile pb-20 md:px-gutter md:pb-24">
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
          <h2 className="text-h3">{t("partnershipTitle")}</h2>
          <PartnershipForm max={max} />
        </div>
      </section>
    </>
  );
}
