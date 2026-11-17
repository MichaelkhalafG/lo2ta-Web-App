import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthFrame } from "@/components/account/AccountFrame";
import { ForgotForm } from "@/components/account/AuthForms";
import { accountMetadata } from "@/lib/guard";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/forgot-password">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/forgot-password", "forgot.title");
}

export default async function ForgotPasswordPage({ params }: PageProps<"/[locale]/forgot-password">) {
  await pageLocale(params);
  const t = await getTranslations("account");

  return (
    <AuthFrame label="LK-U3" title={t("forgot.title")} intro={t("forgot.intro")}>
      <ForgotForm />
    </AuthFrame>
  );
}
