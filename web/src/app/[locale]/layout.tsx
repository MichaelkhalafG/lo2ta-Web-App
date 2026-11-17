import type { Metadata, Viewport } from "next";
import { hasLocale, NextIntlClientProvider } from "next-intl";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { notFound } from "next/navigation";
import { SiteHeader } from "@/components/header/SiteHeader";
import { SiteFooter } from "@/components/SiteFooter";
import { localeDir, routing } from "@/i18n/routing";
import { siteUrl } from "@/lib/seo";
import { fontVariables } from "../fonts";
import "../globals.css";

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export const viewport: Viewport = {
  themeColor: "#090C11",
  colorScheme: "dark",
};

export async function generateMetadata({ params }: LayoutProps<"/[locale]">): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: "meta" });

  return {
    metadataBase: new URL(siteUrl()),
    title: { default: t("siteName"), template: `%s | ${t("siteName")}` },
    // Noindex until launch; switched in the SEO phase, not here.
    robots: { index: false, follow: false },
  };
}

export default async function LocaleLayout({ children, params }: LayoutProps<"/[locale]">) {
  const { locale } = await params;
  if (!hasLocale(routing.locales, locale)) {
    notFound();
  }
  setRequestLocale(locale);

  return (
    <html lang={locale} dir={localeDir[locale]} className={`${fontVariables} h-full antialiased`}>
      <body className="flex min-h-full flex-col bg-bg-0 text-text-1">
        <NextIntlClientProvider>
          <SiteHeader />
          <main id="main" tabIndex={-1} className="flex-1 outline-none">
            {children}
          </main>
          <SiteFooter />
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
