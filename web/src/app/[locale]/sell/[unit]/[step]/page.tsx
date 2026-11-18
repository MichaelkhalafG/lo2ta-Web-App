import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Step1Contract } from "@/components/sell/Step1Contract";
import { Step2Unit } from "@/components/sell/Step2Unit";
import { Step3Money } from "@/components/sell/Step3Money";
import { Step4Documents } from "@/components/sell/Step4Documents";
import { Step5Photos } from "@/components/sell/Step5Photos";
import { Step6Review } from "@/components/sell/Step6Review";
import { WizardProvider } from "@/components/sell/WizardContext";
import { redirect } from "@/i18n/navigation";
import { areaTree, compoundList, developerList, featureList, publicSettings, sellChoices, siteTexts } from "@/lib/core";
import { requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";
import { sellerUnit } from "@/lib/sellServer";

export async function generateMetadata({ params }: PageProps<"/[locale]/sell/[unit]/[step]">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const t = await getTranslations({ locale, namespace: "sell.frame" });

  return { title: t("title"), robots: { index: false, follow: false } };
}

/** One step of the seller wizard (PLAN 2.2.2). Only the unit's seller reaches it (core answers 404 to anyone else). */
export default async function SellStepPage({ params }: PageProps<"/[locale]/sell/[unit]/[step]">) {
  const locale = await pageLocale(params);
  const { unit: id, step: stepParam } = await params;
  await requireAccount(locale, `/sell/${id}/${stepParam}`);

  const step = Number(stepParam);
  if (!Number.isInteger(step) || step < 1 || step > 6) {
    notFound();
  }
  const unit = await sellerUnit(locale, id);
  if (!unit) {
    notFound();
  }
  if (!unit.editable) {
    return redirect({ href: `/account/units/${unit.id}`, locale });
  }
  if (step > Math.max(1, unit.step_reached)) {
    return redirect({ href: `/sell/${unit.id}/${Math.min(unit.step_reached, 6)}`, locale });
  }

  const [developers, compounds, areas, features, choices, settings, texts] = await Promise.all([
    developerList(locale),
    compoundList(locale),
    areaTree(locale),
    featureList(locale),
    sellChoices(locale),
    publicSettings(locale),
    siteTexts("seller", locale),
  ]);
  const reference = { developers, compounds, areas, features, choices, settings, texts };
  const Step = [Step1Contract, Step2Unit, Step3Money, Step4Documents, Step5Photos, Step6Review][step - 1]!;

  return (
    <WizardProvider key={`${unit.id}-${step}`} unit={unit} reference={reference}>
      <Step />
    </WizardProvider>
  );
}
