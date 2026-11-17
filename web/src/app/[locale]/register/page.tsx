import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthFrame } from "@/components/account/AccountFrame";
import { RegisterForm } from "@/components/account/AuthForms";
import { redirect } from "@/i18n/navigation";
import { currentAccount } from "@/lib/account";
import { accountMetadata } from "@/lib/guard";
import { intentPath, parseIntent, safeNext } from "@/lib/intent";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/register">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/register", "register.title");
}

export default async function RegisterPage({ params, searchParams }: PageProps<"/[locale]/register">) {
  const locale = await pageLocale(params);
  const query = await searchParams;
  const next = safeNext(query.next);
  const intent = typeof query.intent === "string" && parseIntent(query.intent) ? query.intent : null;

  if (await currentAccount(locale)) {
    redirect({ href: intentPath(parseIntent(intent), next), locale });
  }

  const t = await getTranslations("account");

  return (
    <AuthFrame label="LK-U2" title={t("register.title")} intro={t("register.intro")}>
      <RegisterForm next={next} intent={intent} />
    </AuthFrame>
  );
}
