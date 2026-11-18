"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import type { Step1 } from "@/lib/sell";
import { Pills, SelectInput, TextInput } from "./fields";
import { StepNav, WizardFrame } from "./WizardFrame";
import { useStep, useWizard } from "./WizardContext";

/** Step 1, the contract (PLAN 2.2.2): developer, compound and area from the lists or "other", type, numbers, date. */
export function Step1Contract() {
  const t = useTranslations("sell.step1");
  const common = useTranslations("sell.common");
  const { unit, reference } = useWizard();
  const { values, set, errors, message, next } = useStep<Step1>(1, unit.steps.step1);
  const [otherDeveloper, setOtherDeveloper] = useState(values.developer_other !== null);
  const [otherCompound, setOtherCompound] = useState(values.compound_other !== null);
  const [otherArea, setOtherArea] = useState(values.area_other !== null);

  const compounds = reference.compounds.filter((c) => !values.developer || c.developer === values.developer);
  const areaOptions = reference.areas.flatMap((gov) =>
    gov.areas.flatMap((area) => [
      { value: area.slug, label: area.name, group: gov.name },
      ...area.districts.map((d) => ({ value: d.slug, label: `${d.name}، ${area.name}`, group: gov.name })),
    ]),
  );
  const subTypes = reference.choices.sub_types.filter((s) => s.main_type === values.main_type);
  const thisYear = new Date().getFullYear();
  const years = Array.from({ length: thisYear - 1989 }, (_, i) => String(thisYear - i));
  const months = Array.from({ length: 12 }, (_, i) => String(i + 1));

  return (
    <WizardFrame step={1} title={t("title")} intro={t("intro")}>
      {message && Object.keys(errors).length === 0 ? <p className="text-small text-error">{message}</p> : null}

      <div className="grid gap-5 md:grid-cols-2">
        {otherDeveloper ? (
          <TextInput label={t("developer")} required value={values.developer_other} onChange={(v) => set("developer_other", v)} error={errors.developer ?? errors.developer_other} />
        ) : (
          <SelectInput
            label={t("developer")}
            required
            placeholder={t("developerPick")}
            value={values.developer}
            onChange={(v) => {
              set("developer", v);
              set("developer_other", null);
            }}
            options={reference.developers.map((d) => ({ value: d.slug, label: d.name }))}
            error={errors.developer}
          />
        )}
        <OtherToggle
          on={otherDeveloper}
          label={otherDeveloper ? common("backToList") : t("developerMissing")}
          onToggle={() => {
            setOtherDeveloper(!otherDeveloper);
            set("developer", null);
            set("developer_other", null);
          }}
        />

        {otherCompound ? (
          <TextInput label={t("compound")} required value={values.compound_other} onChange={(v) => set("compound_other", v)} error={errors.compound ?? errors.compound_other} />
        ) : (
          <SelectInput
            label={t("compound")}
            required
            placeholder={t("compoundPick")}
            value={values.compound}
            onChange={(v) => {
              set("compound", v);
              set("compound_other", null);
              const area = reference.compounds.find((c) => c.slug === v)?.area;
              if (area && !values.area && !values.area_other) {
                set("area", area);
              }
            }}
            options={compounds.map((c) => ({ value: c.slug, label: c.name }))}
            error={errors.compound}
          />
        )}
        <OtherToggle
          on={otherCompound}
          label={otherCompound ? common("backToList") : t("compoundMissing")}
          onToggle={() => {
            setOtherCompound(!otherCompound);
            set("compound", null);
            set("compound_other", null);
          }}
        />

        {otherArea ? (
          <TextInput label={t("area")} required value={values.area_other} onChange={(v) => set("area_other", v)} error={errors.area ?? errors.area_other} />
        ) : (
          <SelectInput label={t("area")} required placeholder={t("areaPick")} value={values.area} onChange={(v) => set("area", v)} options={areaOptions} hint={t("areaHint")} error={errors.area} />
        )}
        <OtherToggle
          on={otherArea}
          label={otherArea ? common("backToList") : t("areaMissing")}
          onToggle={() => {
            setOtherArea(!otherArea);
            set("area", null);
            set("area_other", null);
          }}
        />
      </div>

      <Pills
        label={t("mainType")}
        required
        value={values.main_type}
        onChange={(v) => {
          set("main_type", v);
          set("sub_type", null);
        }}
        options={reference.choices.main_types.map((m) => ({ value: m.code, label: m.label }))}
        error={errors.main_type}
      />
      {values.main_type ? (
        <Pills label={t("subType")} required value={values.sub_type} onChange={(v) => set("sub_type", v)} options={subTypes.map((s) => ({ value: s.code, label: s.label }))} error={errors.sub_type} />
      ) : null}

      <div className="grid gap-5 md:grid-cols-2">
        <TextInput label={t("contractNumber")} required dir="ltr" maxLength={64} value={values.contract_number} onChange={(v) => set("contract_number", v)} error={errors.contract_number} />
        <TextInput label={t("unitNumber")} required dir="ltr" maxLength={64} value={values.unit_number} onChange={(v) => set("unit_number", v)} error={errors.unit_number} />
        <SelectInput label={t("contractYear")} required placeholder={common("pick")} value={values.contract_year === null ? null : String(values.contract_year)} onChange={(v) => set("contract_year", v === null ? null : Number(v))} options={years.map((y) => ({ value: y, label: y }))} error={errors.contract_year} />
        <SelectInput label={t("contractMonth")} required placeholder={common("pick")} value={values.contract_month === null ? null : String(values.contract_month)} onChange={(v) => set("contract_month", v === null ? null : Number(v))} options={months.map((m) => ({ value: m, label: common(`month.${m}`) }))} error={errors.contract_month} />
      </div>
      <p className="text-small text-text-3">{t("private")}</p>

      <StepNav nextLabel={t("next")} onNext={() => void next(`/sell/${unit.id}/2`)} />
    </WizardFrame>
  );
}

function OtherToggle({ on, label, onToggle }: { on: boolean; label: string; onToggle: () => void }) {
  return (
    <div className="flex items-end pb-3">
      <button type="button" onClick={onToggle} aria-pressed={on} className="text-small text-text-2 underline underline-offset-4 hover:text-text-1">
        {label}
      </button>
    </div>
  );
}
