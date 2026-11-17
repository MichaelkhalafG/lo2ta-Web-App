import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthFrame } from "@/components/account/AccountFrame";
import { ResetForm } from "@/components/account/AuthForms";
import { accountMetadata } from "@/lib/guard";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/reset-password/[token]">): Promise<Metadata> {
  const { token } = await params;

  return accountMetadata(await pageLocale(params), `/reset-password/${token}`, "reset.title");
}

export default async function ResetPasswordPage({ params, searchParams }: PageProps<"/[locale]/reset-password/[token]">) {
  await pageLocale(params);
  const { token } = await params;
  const query = await searchParams;
  const email = typeof query.email === "string" ? query.email : "";
  const t = await getTranslations("account");

  return (
    <AuthFrame label="LK-U4" title={t("reset.title")} intro={t("reset.intro")}>
      <ResetForm token={decodeURIComponent(token)} email={email} />
    </AuthFrame>
  );
}
