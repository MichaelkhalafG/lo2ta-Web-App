import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AuthFrame } from "@/components/account/AccountFrame";
import { LoginForm } from "@/components/account/AuthForms";
import { redirect } from "@/i18n/navigation";
import { currentAccount } from "@/lib/account";
import { accountMetadata } from "@/lib/guard";
import { intentPath, parseIntent, safeNext } from "@/lib/intent";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/login">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/login", "login.title");
}

export default async function LoginPage({ params, searchParams }: PageProps<"/[locale]/login">) {
  const locale = await pageLocale(params);
  const query = await searchParams;
  const next = safeNext(query.next);
  const intent = typeof query.intent === "string" && parseIntent(query.intent) ? query.intent : null;

  // Already signed in: go straight on.
  if (await currentAccount(locale)) {
    redirect({ href: intentPath(parseIntent(intent), next), locale });
  }

  const t = await getTranslations("account");

  return (
    <AuthFrame label="LK-U1" title={t("login.title")} intro={t("login.intro")}>
      <LoginForm next={next} intent={intent} resetDone={query.reset === "1"} />
    </AuthFrame>
  );
}
