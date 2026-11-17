import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { BudgetForm } from "@/components/account/AccountParts";
import { core, type Budget } from "@/lib/account";
import { areaTree, choices, compoundList, publicSettings } from "@/lib/core";
import { accountMetadata, requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";

export async function generateMetadata({ params }: PageProps<"/[locale]/account/budget">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/account/budget", "budget.title");
}

/** Register or edit the buyer budget (PLAN 2.3). */
export default async function BudgetPage({ params }: PageProps<"/[locale]/account/budget">) {
  const locale = await pageLocale(params);
  await requireAccount(locale, "/account/budget");
  const t = await getTranslations("account.budget");

  const [budget, areas, compounds, options, settings] = await Promise.all([
    core<{ data: Budget | null }>("GET", "me/budget", locale, { auth: true }),
    areaTree(locale),
    compoundList(locale),
    choices(locale),
    publicSettings(locale),
  ]);
  const maxAreas = typeof settings.budget_max_areas === "number" ? settings.budget_max_areas : 10;

  return (
    <AccountPage label="LK-B1" title={t("title")} intro={t("intro")}>
      <BudgetForm budget={budget.ok ? budget.data.data : null} areas={areas} compounds={compounds} options={options} maxAreas={maxAreas} />
    </AccountPage>
  );
}
