import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { redirect } from "@/i18n/navigation";
import type { Locale } from "@/i18n/routing";
import { currentAccount, type Account } from "./account";
import { returnQuery } from "./intent";
import { alternates } from "./seo";

/** The signed-in account for an account page, or a redirect to sign-in that comes back to `path`. */
export async function requireAccount(locale: Locale, path: string): Promise<Account> {
  const account = await currentAccount(locale);
  if (!account) {
    return redirect({ href: `/login?${returnQuery(path)}`, locale });
  }

  return account;
}

/** Account and sign-in pages are never indexed, whatever the site-wide setting becomes at launch. */
export async function accountMetadata(locale: Locale, path: string, titleKey: string): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: "account" });

  return { title: t(titleKey), robots: { index: false, follow: false }, alternates: alternates(path, locale) };
}
