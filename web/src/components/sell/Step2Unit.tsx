"use client";

import { useTranslations } from "next-intl";
import type { Step2 } from "@/lib/sell";
import { CheckList, NumberInput, Pills, SelectInput } from "./fields";
import { StepNav, WizardFrame } from "./WizardFrame";
import { useStep, useWizard } from "./WizardContext";

/** Step 2, the unit (PLAN 2.2.2, 2.2.3): only the fields the sub-type has are shown; core clears the rest too. */
export function Step2Unit() {
  const t = useTranslations("sell.step2");
  const common = useTranslations("sell.common");
  const { unit, reference } = useWizard();
  const { values, set, errors, next } = useStep<Step2>(2, unit.steps.step2);
  const sub = reference.choices.sub_types.find((s) => s.code === unit.steps.step1.sub_type) ?? null;
  const main = unit.steps.step1.main_type;
  const land = main === "land";

  const floors = ["basement", "ground", "mezzanine", ...Array.from({ length: 40 }, (_, i) => String(i + 1))];
  const thisYear = new Date().getFullYear();
  const deliveryYears = Array.from({ length: 21 }, (_, i) => String(thisYear - 5 + i));
  const groups = [...new Set(reference.features.map((f) => f.group))];

  if (!sub) {
    return (
      <WizardFrame step={2} title={t("title")}>
        <p className="text-body text-text-2">{t("needStep1")}</p>
        <StepNav backHref="1" backLabel={common("back1")} nextLabel={t("next")} onNext={() => undefined} disabled />
      </WizardFrame>
    );
  }

  return (
    <WizardFrame step={2} title={t("title")} intro={t("intro", { type: sub.label })}>
      <div className="grid gap-5 md:grid-cols-3">
        <NumberInput decimal label={land ? t("plot") : t("size")} required suffix="m²" value={values.size_m2} onChange={(v) => set("size_m2", v as string | null)} error={errors.size_m2} />
        {sub.has_garden ? <NumberInput decimal label={t("garden")} suffix="m²" value={values.garden_m2} onChange={(v) => set("garden_m2", v as string | null)} error={errors.garden_m2} /> : null}
        {sub.has_roof ? <NumberInput decimal label={t("roof")} suffix="m²" value={values.roof_m2} onChange={(v) => set("roof_m2", v as string | null)} error={errors.roof_m2} /> : null}
        {sub.has_floor ? (
          <SelectInput label={t("floor")} required placeholder={common("pick")} value={values.floor} onChange={(v) => set("floor", v)} options={floors.map((f) => ({ value: f, label: /^\d+$/.test(f) ? f : t(`floors.${f}`) }))} error={errors.floor} />
        ) : null}
        {sub.has_rooms && sub.fixed_bedrooms === null ? <NumberInput label={t("bedrooms")} required value={values.bedrooms} onChange={(v) => set("bedrooms", v as number | null)} error={errors.bedrooms} /> : null}
        {sub.has_rooms ? <NumberInput label={t("bathrooms")} required value={values.bathrooms} onChange={(v) => set("bathrooms", v as number | null)} error={errors.bathrooms} /> : null}
      </div>

      {sub.has_finishing ? (
        <Pills label={t("finishing")} required value={values.finishing} onChange={(v) => set("finishing", v)} options={reference.choices.finishings.map((f) => ({ value: f.code, label: f.label }))} error={errors.finishing} />
      ) : null}

      <Pills
        label={land ? t("deliveryLand") : t("delivery")}
        required
        value={values.delivery_status}
        onChange={(v) => set("delivery_status", v)}
        options={reference.choices.delivery_statuses.map((d) => ({ value: d.code, label: land ? t(`landStatus.${d.code}`) : d.label }))}
        error={errors.delivery_status}
      />
      {values.delivery_status === "under_construction" ? (
        <div className="grid gap-5 md:grid-cols-2">
          <SelectInput label={t("deliveryYear")} required placeholder={common("pick")} value={values.delivery_year === null ? null : String(values.delivery_year)} onChange={(v) => set("delivery_year", v === null ? null : Number(v))} options={deliveryYears.map((y) => ({ value: y, label: y }))} error={errors.delivery_year} />
          <SelectInput label={t("deliveryMonth")} required placeholder={common("pick")} value={values.delivery_month === null ? null : String(values.delivery_month)} onChange={(v) => set("delivery_month", v === null ? null : Number(v))} options={Array.from({ length: 12 }, (_, i) => ({ value: String(i + 1), label: common(`month.${i + 1}`) }))} error={errors.delivery_month} />
        </div>
      ) : null}

      {land ? null : (
        <CheckList label={t("facade")} values={values.facade} onChange={(v) => set("facade", v)} options={["north", "east", "south", "west"].map((s) => ({ value: s, label: t(`sides.${s}`) }))} />
      )}

      <div className="flex flex-col gap-6">
        {groups.map((group) => {
          const options = reference.features.filter((f) => f.group === group && main !== null && f.applies_to.includes(main));
          if (options.length === 0) {
            return null;
          }

          return (
            <CheckList
              key={group}
              label={t(`featureGroups.${group}`)}
              values={values.features}
              onChange={(v) => set("features", v)}
              options={options.map((f) => ({ value: f.code, label: f.name }))}
            />
          );
        })}
        {errors.features ? <p className="text-small text-error">{errors.features}</p> : null}
      </div>

      <StepNav backHref="1" backLabel={common("back1")} nextLabel={t("next")} onNext={() => void next(`/sell/${unit.id}/3`)} />
    </WizardFrame>
  );
}
