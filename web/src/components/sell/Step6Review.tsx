"use client";

import { useTranslations } from "next-intl";
import { useState, useTransition } from "react";
import { submitUnitAction } from "@/lib/actions/sell";
import { problemsOf } from "@/lib/sell";
import { ExitPanel } from "./ExitPanel";
import { formatMoney } from "./fields";
import { WizardFrame } from "./WizardFrame";
import { useWizard } from "./WizardContext";

/*
 * Step 6, review and submit (PLAN 2.2.2, 2.2.14; board 07.3). Each step's summary with its edit link, the exit
 * report preview, and the three declarations, accepted again on every submit. The declaration texts are the site
 * texts core versions; core refuses a submit with anything missing and says where.
 */

const DECLARATIONS = ["owner_not_broker", "data_matches", "terms_privacy"] as const;

export function Step6Review() {
  const t = useTranslations("sell.step6");
  const steps = useTranslations("sell.steps");
  const { unit, reference, locale } = useWizard();
  const [accepted, setAccepted] = useState<Record<string, boolean>>({});
  const [problems, setProblems] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [submitting, start] = useTransition();

  const s1 = unit.steps.step1;
  const s2 = unit.steps.step2;
  const s3 = unit.steps.step3;
  const compound = reference.compounds.find((c) => c.slug === s1.compound)?.name ?? s1.compound_other ?? "";
  const sub = reference.choices.sub_types.find((x) => x.code === s1.sub_type)?.label ?? "";
  const photos = unit.photos.filter((p) => p.kind === "photo").length;
  const rows = [
    { step: 1, text: [compound, sub].filter(Boolean).join(" · ") },
    { step: 2, text: [s2.size_m2 ? `${s2.size_m2} m²` : "", s2.floor ?? ""].filter(Boolean).join(" · ") },
    { step: 3, text: s3.paid_amount !== null ? t("paid", { amount: formatMoney(s3.paid_amount) }) : "" },
    { step: 4, text: t("files", { count: unit.documents.length }) },
    { step: 5, text: t("photos", { count: photos, chars: (unit.steps.step5.description ?? "").length }) },
  ] as const;
  const all = DECLARATIONS.every((d) => accepted[d]);

  function submit() {
    start(async () => {
      const result = await submitUnitAction(unit.id, Object.fromEntries(DECLARATIONS.map((d) => [d, accepted[d] === true])), locale);
      if (!result.ok) {
        setProblems(result.errors ?? {});
        setMessage(result.message ?? null);
      }
    });
  }

  return (
    <WizardFrame step={6} title={t("title")} intro={t("intro")} aside={<ExitPanel compact />}>
      <ol className="flex flex-col divide-y divide-line rounded-sm border border-line bg-surface-1">
        {rows.map((row) => {
          const open = Object.keys(problemsOf(unit, row.step)).length > 0;

          return (
            <li key={row.step} className="flex flex-wrap items-center gap-3 px-5 py-4">
              <span className="font-mono text-annotation text-text-3" dir="ltr">
                {String(row.step).padStart(2, "0")}
              </span>
              <span className="text-body font-semibold">{steps(String(row.step))}</span>
              <span className="text-small text-text-2">{row.text}</span>
              {open ? <span className="text-small text-warning">{t("incomplete")}</span> : <span className="text-small text-success">✓</span>}
              <a href={`./${row.step}`} className="ms-auto text-small text-text-1 underline underline-offset-4">
                {t("edit")}
              </a>
            </li>
          );
        })}
      </ol>

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-2 text-h3">{t("declarations")}</legend>
        {DECLARATIONS.map((d) => (
          <label key={d} className="flex min-h-11 items-start gap-3 text-body text-text-1">
            <input type="checkbox" checked={accepted[d] === true} onChange={(e) => setAccepted((a) => ({ ...a, [d]: e.target.checked }))} className="mt-1.5 size-4 accent-[var(--color-text-1)]" />
            {reference.texts[`seller.declaration.${d}`] ?? d}
          </label>
        ))}
      </fieldset>

      {message || Object.keys(problems).length > 0 ? (
        <div role="alert" className="flex flex-col gap-2 rounded-sm border border-error/40 bg-error-tint px-4 py-3 text-small text-error">
          {message ? <p>{message}</p> : null}
          <ul className="list-inside list-disc">
            {Object.entries(problems).map(([field, text]) => {
              const step = /^steps\.(\d)\./.exec(field)?.[1];

              return <li key={field}>{step ? `${steps(step)}: ${text}` : text}</li>;
            })}
          </ul>
        </div>
      ) : null}

      <div className="flex justify-end border-t border-line pt-6">
        <button
          type="button"
          onClick={submit}
          disabled={!all || submitting}
          className="inline-flex h-control-lg items-center justify-center gap-3 rounded-sm bg-gold px-8 text-[1rem] font-semibold text-on-gold transition-colors duration-150 hover:bg-gold-bright disabled:opacity-50"
        >
          {submitting ? t("submitting") : t("submit")}
          <span aria-hidden="true" className="rtl:rotate-180">
            →
          </span>
        </button>
      </div>
    </WizardFrame>
  );
}
