"use client";

import { useTranslations } from "next-intl";
import { useState, useTransition } from "react";
import type { FieldErrors } from "@/lib/account";
import { liveEditAction, withdrawAction } from "@/lib/actions/sell";
import type { Step3 } from "@/lib/sell";
import { DocumentSlots, type Upload } from "./Step4Documents";
import { PhotoManager, type PendingPhoto } from "./Step5Photos";
import { MoneyInput, NumberInput, Pills, SelectInput, TextArea, TextInput, YesNo } from "./fields";
import { useWizard } from "./WizardContext";

/*
 * A submitted or published unit in My account (PLAN 2.2.7): new documents at any time; on a live unit, photo and
 * description changes go live at once, figure changes wait for the team (the unit stays live with the old values);
 * withdraw, or ask to withdraw during an open deal.
 */

const REVIEW_FIELDS = [
  "paid_amount", "market_price_estimate", "installment_amount", "installment_frequency", "next_due_date", "years_left",
  "accepts_less", "lowest_accepted_amount", "has_overdue", "overdue_count", "overdue_amount", "overdue_settlement", "maintenance_due",
] as const;

type LiveValues = Pick<Step3, (typeof REVIEW_FIELDS)[number]> & { description: string | null };

export function UnitManage() {
  const t = useTranslations("sell.manage");
  const { unit } = useWizard();
  const [uploads, setUploads] = useState<Upload[]>([]);
  const [pending, setPending] = useState<PendingPhoto[]>([]);

  return (
    <div className="flex flex-col gap-10">
      {unit.pending_change ? <PendingNotice /> : null}
      {unit.withdraw_requested ? <p className="rounded-sm border border-warning/40 bg-warning-tint px-4 py-3 text-small text-warning">{t("withdrawRequested")}</p> : null}

      {unit.can_edit_live ? <LiveEditForm /> : null}

      {unit.can_edit_live ? (
        <section className="flex flex-col gap-4">
          <p className="text-small text-text-3">{t("photosLive")}</p>
          <PhotoManager pending={pending} setPending={setPending} />
        </section>
      ) : null}

      <section className="flex flex-col gap-4">
        <h2 className="text-h3">{t("documents")}</h2>
        <p className="text-small text-text-3">{t("documentsNote")}</p>
        <DocumentSlots uploads={uploads} setUploads={setUploads} />
      </section>

      {unit.can_withdraw ? <WithdrawForm /> : null}
    </div>
  );
}

function PendingNotice() {
  const t = useTranslations("sell.manage");
  const fields = useTranslations("sell.fields");
  const { unit } = useWizard();

  return (
    <div className="flex flex-col gap-2 rounded-sm border border-line bg-surface-1 p-5 text-small">
      <h2 className="text-body font-semibold">{t("pendingTitle")}</h2>
      <p className="text-text-2">{t("pendingNote")}</p>
      <ul className="list-inside list-disc text-text-2">
        {Object.keys(unit.pending_change ?? {}).map((field) => (
          <li key={field}>{fields.has(field) ? fields(field) : field}</li>
        ))}
      </ul>
    </div>
  );
}

function LiveEditForm() {
  const t = useTranslations("sell.manage");
  const step3 = useTranslations("sell.step3");
  const common = useTranslations("sell.common");
  const { unit, setUnit, reference, locale } = useWizard();
  const initial: LiveValues = { ...Object.fromEntries(REVIEW_FIELDS.map((f) => [f, unit.steps.step3[f]])), description: unit.steps.step5.description } as LiveValues;
  const [values, setValues] = useState<LiveValues>(initial);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [saving, start] = useTransition();
  const egp = common("egp");
  const set = <K extends keyof LiveValues>(key: K, value: LiveValues[K]) => setValues((v) => ({ ...v, [key]: value }));

  function save() {
    // Only what changed is sent, so an untouched figure never becomes a pending change.
    const changed = Object.fromEntries(Object.entries(values).filter(([k, v]) => v !== initial[k as keyof LiveValues]));
    if (Object.keys(changed).length === 0) {
      setNotice(t("nothing"));
      return;
    }
    start(async () => {
      const result = await liveEditAction(unit.id, changed, locale);
      if (result.ok && result.unit) {
        setUnit(result.unit);
        setErrors({});
        setNotice(result.unit.pending_change ? t("savedPending") : t("savedLive"));
      } else {
        setErrors(result.errors ?? {});
        setNotice(result.message ?? null);
      }
    });
  }

  return (
    <section className="flex flex-col gap-5">
      <h2 className="text-h3">{t("editTitle")}</h2>
      <p className="text-small text-text-3">{t("editNote")}</p>
      <TextArea label={t("description")} max={Number(reference.settings.description_max ?? 1200)} value={values.description} onChange={(v) => set("description", v)} error={errors.description} counter={`${(values.description ?? "").length}`} />
      <div className="grid gap-5 md:grid-cols-2">
        <MoneyInput label={step3("paid")} unit={egp} value={values.paid_amount} onChange={(v) => set("paid_amount", v)} error={errors.paid_amount} />
        <MoneyInput label={step3("market")} unit={egp} value={values.market_price_estimate} onChange={(v) => set("market_price_estimate", v)} error={errors.market_price_estimate} />
        <MoneyInput label={step3("installment")} unit={egp} value={values.installment_amount} onChange={(v) => set("installment_amount", v)} error={errors.installment_amount} />
        <SelectInput label={step3("frequency")} placeholder={common("pick")} value={values.installment_frequency} onChange={(v) => set("installment_frequency", v)} options={reference.choices.installment_frequencies.map((f) => ({ value: f.code, label: f.label }))} error={errors.installment_frequency} />
        <TextInput label={step3("nextDue")} dir="ltr" placeholder="YYYY-MM-DD" value={values.next_due_date} onChange={(v) => set("next_due_date", v === "" ? null : v)} error={errors.next_due_date} />
        <NumberInput label={step3("yearsLeft")} suffix={step3("years")} value={values.years_left} onChange={(v) => set("years_left", v as number | null)} error={errors.years_left} />
        <MoneyInput label={step3("maintenance")} unit={egp} value={values.maintenance_due} onChange={(v) => set("maintenance_due", v)} error={errors.maintenance_due} />
      </div>
      <YesNo label={step3("acceptsLessQ")} value={values.accepts_less} yes={common("yes")} no={common("no")} onChange={(v) => set("accepts_less", v)} />
      {values.accepts_less ? <MoneyInput label={step3("lowest")} unit={egp} value={values.lowest_accepted_amount} onChange={(v) => set("lowest_accepted_amount", v)} error={errors.lowest_accepted_amount} /> : null}
      <YesNo label={step3("overdueQ")} value={values.has_overdue} yes={common("yes")} no={common("no")} onChange={(v) => set("has_overdue", v)} />
      {values.has_overdue ? (
        <div className="grid gap-5 md:grid-cols-2">
          <NumberInput label={step3("overdueCount")} value={values.overdue_count} onChange={(v) => set("overdue_count", v as number | null)} error={errors.overdue_count} />
          <MoneyInput label={step3("overdueAmount")} unit={egp} value={values.overdue_amount} onChange={(v) => set("overdue_amount", v)} error={errors.overdue_amount} />
          <Pills label={step3("settlementQ")} value={values.overdue_settlement} onChange={(v) => set("overdue_settlement", v)} options={reference.choices.overdue_settlements.map((o) => ({ value: o.code, label: o.label }))} error={errors.overdue_settlement} />
        </div>
      ) : null}
      {notice ? (
        <p role="status" className="text-small text-text-2">
          {notice}
        </p>
      ) : null}
      <button type="button" onClick={save} disabled={saving} className="inline-flex h-control-lg items-center justify-center self-start rounded-sm bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright disabled:opacity-50">
        {saving ? t("saving") : t("save")}
      </button>
    </section>
  );
}

function WithdrawForm() {
  const t = useTranslations("sell.manage");
  const { unit, setUnit, locale } = useWizard();
  const [reason, setReason] = useState("");
  const [sure, setSure] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, start] = useTransition();
  const reserved = unit.state.code === "reserved";

  return (
    <section className="flex flex-col gap-4 border-t border-line pt-6">
      <h2 className="text-h3">{reserved ? t("withdrawRequestTitle") : t("withdrawTitle")}</h2>
      <p className="text-small text-text-3">{reserved ? t("withdrawRequestNote") : t("withdrawNote")}</p>
      <TextArea label={t("reason")} max={500} value={reason} onChange={setReason} counter={`${reason.length} / 500`} />
      <label className="flex min-h-11 items-center gap-3 text-body">
        <input type="checkbox" checked={sure} onChange={(e) => setSure(e.target.checked)} className="size-4 accent-[var(--color-text-1)]" />
        {t("withdrawSure")}
      </label>
      {message ? (
        <p role="alert" className="text-small text-error">
          {message}
        </p>
      ) : null}
      <button
        type="button"
        disabled={!sure || busy}
        onClick={() =>
          start(async () => {
            const result = await withdrawAction(unit.id, reason, locale);
            if (result.ok && result.unit) {
              setUnit(result.unit);
            } else {
              setMessage(result.message ?? null);
            }
          })
        }
        className="inline-flex h-control-lg items-center justify-center self-start rounded-sm border border-error/60 px-6 text-error hover:bg-error-tint disabled:opacity-50"
      >
        {reserved ? t("withdrawRequest") : t("withdraw")}
      </button>
    </section>
  );
}
