import type { Metadata } from "next";
import { hasLocale, useTranslations } from "next-intl";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { use } from "react";
import { Link } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { alternates } from "@/lib/seo";

export const revalidate = 300;

export async function generateMetadata({ params }: PageProps<"/[locale]">): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "setup" });
  const lang = hasLocale(routing.locales, locale) ? locale : routing.defaultLocale;

  return { title: t("title"), alternates: alternates("/", lang) };
}

/** Setup placeholder: proves locale routing, RTL/LTR and hreflang. Replaced by the real home page in phase 2. */
export default function HomePage({ params }: PageProps<"/[locale]">) {
  const { locale } = use(params);
  setRequestLocale(locale);
  const t = useTranslations("setup");
  const other = locale === "ar" ? "en" : "ar";

  return (
    <main className="mx-auto w-full max-w-2xl px-6 py-16">
      <h1 className="text-3xl font-semibold">{t("title")}</h1>
      <p className="mt-4 text-start opacity-80">{t("body")}</p>
      <div className="mt-8 flex gap-3">
        <span className="rounded-md border ps-4 pe-2 py-1">{t("start")}</span>
        <Link href="/" locale={other} className="ms-auto rounded-md border px-3 py-1 underline">
          {t("switch")}
        </Link>
      </div>
    </main>
  );
}
