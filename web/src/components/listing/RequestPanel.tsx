"use client";

import { useLocale, useTranslations } from "next-intl";
import { useActionState, useState } from "react";
import { requestUnitAction, type FormResult } from "@/lib/actions/requests";
import { money } from "@/lib/listing";
import { Honeypot, Notice, Submit } from "../account/Form";

const initial: FormResult = { ok: false };

/**
 * "Request this unit" (PLAN 2.3, board 06.3): an optional question (contact details are masked by core), readiness,
 * and the fee consent with the exact 1% amount. Signed out, the submit leads to sign-in and back here with the form
 * open (return-to-action).
 */
export function RequestPanel({
  code,
  path,
  feeAmount,
  consentText,
  note,
  startOpen,
}: {
  code: string;
  path: string;
  feeAmount: number;
  consentText: string;
  note: string;
  startOpen: boolean;
}) {
  const t = useTranslations("listing.request");
  const locale = useLocale();
  const [open, setOpen] = useState(startOpen);
  const [state, action] = useActionState(requestUnitAction, initial);
  const e = state.errors ?? {};

  if (state.ok) {
    return (
      <div className="flex flex-col gap-3">
        <Notice tone="success">{t("sent", { reference: state.reference ?? "" })}</Notice>
        <p className="text-small text-text-3">{note}</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {open ? (
        <form action={action} className="flex flex-col gap-4" noValidate>
          <input type="hidden" name="locale" value={locale} />
          <input type="hidden" name="code" value={code} />
          <input type="hidden" name="next" value={path} />
          <input type="hidden" name="fee_amount" value={feeAmount} />
          <Honeypot label="website" />
          {state.message && Object.keys(e).length === 0 ? <Notice tone="error">{state.message}</Notice> : null}
          {e.code || e.email ? <Notice tone="error">{e.code ?? e.email}</Notice> : null}

          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1 text-small text-text-2">{t("readiness")}</legend>
            {(["now", "within_3_months", "looking"] as const).map((r) => (
              <label key={r} className="flex min-h-10 items-center gap-3 text-body">
                <input type="radio" name="readiness" value={r} defaultChecked={(state.values?.readiness ?? "now") === r} className="size-4 accent-[var(--color-text-1)]" />
                {t(`readinessOptions.${r}`)}
              </label>
            ))}
            {e.readiness ? <p className="text-small text-error">{e.readiness}</p> : null}
          </fieldset>

          <label className="flex flex-col gap-2 text-small text-text-2">
            {t("question")}
            <textarea
              name="question"
              maxLength={500}
              rows={3}
              defaultValue={state.values?.question ?? ""}
              className="rounded-sm border border-line-strong bg-bg-1 p-3 text-body text-text-1 outline-none focus:border-text-1"
            />
            <span className="text-micro text-text-3">{t("questionHint")}</span>
          </label>

          <label className="flex items-start gap-3 rounded-sm border border-line bg-bg-1 p-3 text-small text-text-1">
            <input type="checkbox" name="fee_consent" className="mt-1 size-4 shrink-0 accent-[var(--color-text-1)]" />
            <span>{consentText.replace(":amount", money(feeAmount))}</span>
          </label>
          {e.fee_consent ? <p className="text-small text-error">{e.fee_consent}</p> : null}

          <Submit pendingLabel={t("sending")}>{t("send")}</Submit>
        </form>
      ) : (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="inline-flex h-control-lg w-full items-center justify-center gap-3 rounded-sm bg-gold px-6 text-[1rem] font-semibold text-on-gold hover:bg-gold-bright"
        >
          {t("open")}
          <span aria-hidden="true" className="rtl:rotate-180">
            →
          </span>
        </button>
      )}
      <p className="text-small text-text-3">{note}</p>
    </div>
  );
}
