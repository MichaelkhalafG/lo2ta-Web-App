import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthFrame } from "@/components/account/AccountFrame";
import { Notice } from "@/components/account/Form";
import { Link } from "@/i18n/navigation";
import { core } from "@/lib/account";
import { accountMetadata } from "@/lib/guard";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/verify-email">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/verify-email", "verify.title");
}

/** The link in the verification e-mail lands here; core checks its signature and marks the e-mail verified. */
export default async function VerifyEmailPage({ params, searchParams }: PageProps<"/[locale]/verify-email">) {
  const locale = await pageLocale(params);
  const query = await searchParams;
  const t = await getTranslations("account.verify");
  const field = (name: string) => (typeof query[name] === "string" ? query[name] : "");

  const result = await core("POST", "auth/verify-email", locale, {
    body: { id: Number(field("id")), hash: field("hash"), expires: Number(field("expires")), signature: field("signature") },
  });

  return (
    <AuthFrame label="LK-U5" title={t("title")}>
      {result.ok ? <Notice tone="success">{t("ok")}</Notice> : <Notice tone="error">{t("failed")}</Notice>}
      <Link href="/account" className="text-body text-text-1 underline underline-offset-4">
        {t("toAccount")}
      </Link>
    </AuthFrame>
  );
}
