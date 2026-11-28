import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { UnitManage } from "@/components/sell/UnitManage";
import { ReviewNotice } from "@/components/sell/WizardFrame";
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

      {unit.review ? <ReviewNotice review={unit.review} /> : null}

      {unit.last_change && !unit.pending_change ? (
        <p role="status" className="rounded-sm border border-line bg-surface-1 p-5 text-small text-text-2">
          <span className="text-text-1">
            {unit.last_change.state === "approved"
              ? t("lastChangeApproved", { fields: unit.last_change.fields.join(locale === "ar" ? "، " : ", "), date: unit.last_change.at ?? "" })
              : t("lastChangeRejected", { fields: unit.last_change.fields.join(locale === "ar" ? "، " : ", "), date: unit.last_change.at ?? "" })}
          </span>
          {unit.last_change.note ? <span className="mt-1 block">{t("teamChangesReason", { reason: unit.last_change.note })}</span> : null}
        </p>
      ) : null}

      {unit.team_changes.length > 0 ? (
        <section aria-labelledby="team-changes" className="flex flex-col gap-3 rounded-sm border border-line bg-surface-1 p-5">
          <h2 id="team-changes" className="text-body font-semibold">
            {t("teamChanges")}
          </h2>
          <p className="text-small text-text-2">{t("teamChangesNote")}</p>
          <ul className="flex flex-col divide-y divide-line">
            {unit.team_changes.map((c, i) => (
              <li key={`${c.field}-${i}`} className="flex flex-col gap-1 py-3 text-small">
                <span className="text-text-1">
                  {c.label}:{" "}
                  <span className="font-mono tabular-nums" dir="ltr">
                    {c.from ?? "—"} → {c.to ?? "—"}
                  </span>
                </span>
                {c.reason ? <span className="text-text-2">{t("teamChangesReason", { reason: c.reason })}</span> : null}
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {unit.has_exit_report ? (
        <div className="flex flex-wrap items-center gap-4 rounded-sm border border-line bg-surface-1 p-5">
          <span className="text-body">{t("exitReport")}</span>
          <a href={`/api/seller/${unit.id}/exit-report?locale=ar`} className="inline-flex min-h-control-md items-center text-small text-text-1 underline underline-offset-4">
            العربية (PDF)
          </a>
          <a href={`/api/seller/${unit.id}/exit-report?locale=en`} className="inline-flex min-h-control-md items-center text-small text-text-1 underline underline-offset-4" lang="en">
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
