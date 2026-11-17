import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { ProfileForm, ResendVerification } from "@/components/account/AccountParts";
import { Notice } from "@/components/account/Form";
import { Link } from "@/i18n/navigation";
import { accountMetadata, requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/account">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/account", "overview.title");
}

/** My account (PLAN 2.1, 2.3): details, e-mail confirmation, budget and favorites at a glance. */
export default async function AccountOverviewPage({ params }: PageProps<"/[locale]/account">) {
  const locale = await pageLocale(params);
  const account = await requireAccount(locale, "/account");
  const t = await getTranslations("account.overview");

  return (
    <AccountPage label="LK-U0" title={t("title")} intro={t("intro", { name: account.name })}>
      {account.email_verified ? null : (
        <div className="flex flex-col gap-3 rounded-sm border border-warning/40 bg-warning-tint p-5">
          <p className="text-body text-warning">{t("notVerified", { email: account.email })}</p>
          <ResendVerification />
        </div>
      )}

      <div className="grid gap-6 md:grid-cols-3">
        <div className="flex flex-col gap-2 rounded-sm border border-line bg-surface-1 p-5">
          <span className="text-small text-text-3">{t("email")}</span>
          <span className="font-mono text-[0.875rem] break-all" dir="ltr">
            {account.email}
          </span>
          <span className={`text-small ${account.email_verified ? "text-success" : "text-warning"}`}>
            {account.email_verified ? t("verified") : t("unverified")}
          </span>
        </div>
        <Link href="/account/budget" className="flex flex-col gap-2 rounded-sm border border-line bg-surface-1 p-5 hover:bg-surface-2">
          <span className="text-small text-text-3">{t("budget")}</span>
          <span className="text-body text-text-1">{account.has_budget ? t("budgetSet") : t("budgetNone")}</span>
        </Link>
        <Link href="/account/favorites" className="flex flex-col gap-2 rounded-sm border border-line bg-surface-1 p-5 hover:bg-surface-2">
          <span className="text-small text-text-3">{t("favorites")}</span>
          <span className="font-mono text-h3 tabular">{account.favorites_count}</span>
        </Link>
      </div>

      <Notice tone="info">{t("later")}</Notice>

      <section className="flex max-w-xl flex-col gap-5">
        <h2 className="text-h3">{t("details")}</h2>
        <ProfileForm account={account} />
      </section>
    </AccountPage>
  );
}
