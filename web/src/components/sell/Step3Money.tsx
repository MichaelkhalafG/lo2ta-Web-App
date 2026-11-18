"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import type { Step3 } from "@/lib/sell";
import { ExitPanel } from "./ExitPanel";
import { MoneyInput, NumberInput, Pills, Section, SelectInput, TextInput, YesNo } from "./fields";
import { StepNav, WizardFrame } from "./WizardFrame";
import { useStep, useWizard } from "./WizardContext";

/**
 * Step 3, the money (PLAN 2.2.2, 2.2.9, 2.2.12; board 07.1). The exit report beside it updates after each autosave.
 * The transfer fee is the developer's, paid by the seller to the developer, never to Lokta (PLAN 6 row 8c).
 */
export function Step3Money() {
  const t = useTranslations("sell.step3");
  const common = useTranslations("sell.common");
  const { unit, reference } = useWizard();
  const { values, set, errors, next } = useStep<Step3>(3, unit.steps.step3);
  const [annual, setAnnual] = useState(values.annual_extra_amount !== null);
  const fullyPaid = values.total_price !== null && values.paid_amount !== null && values.paid_amount === values.total_price;
  const egp = common("egp");
  const feePercent = values.transfer_fee_bp === null ? "" : String(values.transfer_fee_bp / 100);

  return (
    <WizardFrame step={3} title={t("title")} intro={t("intro")} aside={<ExitPanel />}>
      <div className="rounded-sm border border-line bg-surface-1 px-4 py-3 text-small text-text-2">{t("noOver")}</div>

      <Section number="3.1" title={t("contract")}>
        <div className="grid gap-5 md:grid-cols-2">
          <MoneyInput label={t("total")} required unit={egp} value={values.total_price} onChange={(v) => set("total_price", v)} hint={t("totalHint")} error={errors.total_price} />
          <MoneyInput label={t("paid")} required unit={egp} value={values.paid_amount} onChange={(v) => set("paid_amount", v)} hint={t("paidHint")} error={errors.paid_amount} />
          <MoneyInput label={t("market")} required unit={egp} value={values.market_price_estimate} onChange={(v) => set("market_price_estimate", v)} hint={t("marketHint")} error={errors.market_price_estimate} />
        </div>
      </Section>

      <Section number="3.2" title={t("installments")} note={t("installmentsNote")}>
        {fullyPaid ? (
          <p className="text-body text-success">{t("finished")}</p>
        ) : (
          <div className="grid gap-5 md:grid-cols-2">
            <MoneyInput label={t("installment")} required unit={egp} value={values.installment_amount} onChange={(v) => set("installment_amount", v)} error={errors.installment_amount} />
            <SelectInput label={t("frequency")} required placeholder={common("pick")} value={values.installment_frequency} onChange={(v) => set("installment_frequency", v)} options={reference.choices.installment_frequencies.map((f) => ({ value: f.code, label: f.label }))} error={errors.installment_frequency} />
            <TextInput label={t("nextDue")} required dir="ltr" placeholder="YYYY-MM-DD" value={values.next_due_date} onChange={(v) => set("next_due_date", v === "" ? null : v)} error={errors.next_due_date} />
            <NumberInput label={t("yearsLeft")} suffix={t("years")} value={values.years_left} onChange={(v) => set("years_left", v as number | null)} error={errors.years_left} />
          </div>
        )}
        {unit.warnings.money.includes("installments_mismatch") ? <p className="text-small text-warning">{t("mismatch")}</p> : null}

        {fullyPaid ? null : (
          <>
            <YesNo
              label={t("annualQ")}
              value={annual}
              yes={common("yes")}
              no={common("no")}
              onChange={(v) => {
                setAnnual(v);
                if (!v) {
                  set("annual_extra_amount", null);
                }
              }}
            />
            {annual ? <MoneyInput label={t("annual")} required unit={egp} value={values.annual_extra_amount} onChange={(v) => set("annual_extra_amount", v)} error={errors.annual_extra_amount} /> : null}
          </>
        )}
        <MoneyInput label={t("deliveryPayment")} unit={egp} value={values.delivery_payment} onChange={(v) => set("delivery_payment", v)} hint={t("deliveryPaymentHint")} error={errors.delivery_payment} />

        <YesNo label={t("overdueQ")} value={values.has_overdue} yes={common("yes")} no={common("no")} onChange={(v) => set("has_overdue", v)} hint={t("overdueHint")} />
        {values.has_overdue ? (
          <>
            <div className="grid gap-5 md:grid-cols-2">
              <NumberInput label={t("overdueCount")} required suffix={t("installmentUnit")} value={values.overdue_count} onChange={(v) => set("overdue_count", v as number | null)} error={errors.overdue_count} />
              <MoneyInput label={t("overdueAmount")} required unit={egp} value={values.overdue_amount} onChange={(v) => set("overdue_amount", v)} error={errors.overdue_amount} />
            </div>
            <Pills label={t("settlementQ")} required value={values.overdue_settlement} onChange={(v) => set("overdue_settlement", v)} options={reference.choices.overdue_settlements.map((o) => ({ value: o.code, label: o.label }))} error={errors.overdue_settlement} />
          </>
        ) : null}
        <MoneyInput label={t("maintenance")} unit={egp} value={values.maintenance_due} onChange={(v) => set("maintenance_due", v)} error={errors.maintenance_due} />
      </Section>

      <Section number="3.3" title={t("sale")}>
        <YesNo label={t("acceptsLessQ")} value={values.accepts_less} yes={common("yes")} no={common("no")} onChange={(v) => set("accepts_less", v)} />
        {values.accepts_less ? <MoneyInput label={t("lowest")} required unit={egp} value={values.lowest_accepted_amount} onChange={(v) => set("lowest_accepted_amount", v)} hint={t("lowestHint")} error={errors.lowest_accepted_amount} /> : null}
        <TextInput
          label={t("transferFee")}
          dir="ltr"
          maxLength={5}
          value={feePercent}
          onChange={(v) => {
            const n = Number(v.replace(",", "."));
            set("transfer_fee_bp", v.trim() === "" || !Number.isFinite(n) ? null : Math.round(n * 100));
          }}
          hint={t("transferFeeHint")}
          error={errors.transfer_fee_bp}
        />
      </Section>

      <StepNav backHref="2" backLabel={common("back2")} nextLabel={t("next")} onNext={() => void next(`/sell/${unit.id}/4`)} />
    </WizardFrame>
  );
}
