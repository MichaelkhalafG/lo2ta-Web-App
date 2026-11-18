import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { UnitManage } from "@/components/sell/UnitManage";
import { WizardProvider } from "@/components/sell/WizardContext";
import { redirect } from "@/i18n/navigation";
import { areaTree, compoundList, developerList, featureList, publicSettings, sellChoices, siteTexts } from "@/lib/core";
import { accountMetadata, requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";
import { sellerUnit } from "@/lib/sellServer";

export async function generateMetadata({ params }: PageProps<"/[locale]/account/units/[unit]">): Promise<Metadata> {
  const { unit } = await params;

  return accountMetadata(await pageLocale(params), `/account/units/${unit}`, "units.title");
}

/** One unit of the seller after submit (PLAN 2.2.7): its state, the exit report, documents, edits, withdrawal. */
export default async function AccountUnitPage({ params }: PageProps<"/[locale]/account/units/[unit]">) {
  const locale = await pageLocale(params);
  const { unit: id } = await params;
  await requireAccount(locale, `/account/units/${id}`);
  const unit = await sellerUnit(locale, id);
  if (!unit) {
    notFound();
  }
  if (unit.editable) {
    return redirect({ href: `/sell/${unit.id}/${Math.min(Math.max(unit.step_reached, 1), 6)}`, locale });
  }
  const t = await getTranslations("account.units");
  const [developers, compounds, areas, features, choices, settings, texts] = await Promise.all([
    developerList(locale),
    compoundList(locale),
    areaTree(locale),
    featureList(locale),
    sellChoices(locale),
    publicSettings(locale),
    siteTexts("seller", locale),
  ]);
  const title = [choices.sub_types.find((s) => s.code === unit.steps.step1.sub_type)?.label, compounds.find((c) => c.slug === unit.steps.step1.compound)?.name ?? unit.steps.step1.compound_other]
    .filter(Boolean)
    .join(" · ");

  return (
    <AccountPage label={unit.code ?? unit.reference ?? "LK-U3"} title={title || t("untitled")}>
      <div className="flex flex-wrap items-center gap-4">
        <span className="rounded-sm border border-line px-3 py-1 text-body">{unit.state.label}</span>
        {unit.reference ? (
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            {unit.reference}
          </span>
        ) : null}
        <span className="text-small text-text-2">{t(`stateNote.${unit.state.code}`)}</span>
      </div>

      {unit.has_exit_report ? (
        <div className="flex flex-wrap items-center gap-4 rounded-sm border border-line bg-surface-1 p-5">
          <span className="text-body">{t("exitReport")}</span>
          <a href={`/api/seller/${unit.id}/exit-report?locale=ar`} className="text-small text-text-1 underline underline-offset-4">
            العربية (PDF)
          </a>
          <a href={`/api/seller/${unit.id}/exit-report?locale=en`} className="text-small text-text-1 underline underline-offset-4" lang="en">
            English (PDF)
          </a>
        </div>
      ) : null}

      <WizardProvider unit={unit} reference={{ developers, compounds, areas, features, choices, settings, texts }}>
        <UnitManage />
      </WizardProvider>
    </AccountPage>
  );
}
