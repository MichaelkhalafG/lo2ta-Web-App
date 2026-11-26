"use client";

import { useLocale, useTranslations } from "next-intl";
import { useActionState, useId } from "react";
import { Link } from "@/i18n/navigation";
import { buyerCalcAction, sellerCalcAction, type CalcState } from "@/lib/actions/tools";
import { money } from "@/lib/listing";
import { percent, type BuyerResult, type SellerResult } from "@/lib/tools";
import { Field, Notice, Submit } from "../account/Form";
import { ExitDrawing } from "./ExitDrawing";

/*
 * The calculator's two tabs (PLAN 4.1, Phase 6). Each form posts to a server action that asks core; the answer is
 * drawn under the form. Without JavaScript the form still posts and the page comes back with the answer.
 * Nothing is stored and no sign-in is asked for (J12).
 */

const fieldClass =
  "h-control-lg w-full rounded-sm border border-line-strong bg-bg-1 px-4 text-text-1 outline-none transition-shadow duration-150 focus:border-text-1 focus:shadow-[0_0_0_4px_var(--color-gold-tint)] aria-[invalid=true]:border-error";

function SelectField({ label, name, hint, error, defaultValue, children }: { label: string; name: string; hint?: string; error?: string; defaultValue: string; children: React.ReactNode }) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={id} className="text-small text-text-2">
        {label}
      </label>
      <select id={id} name={name} defaultValue={defaultValue} aria-invalid={error ? true : undefined} aria-describedby={[hintId, errorId].filter(Boolean).join(" ") || undefined} className={fieldClass}>
        {children}
      </select>
      {hint ? (
        <p id={hintId} className="text-small text-text-3">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={errorId} role="alert" className="text-small text-error">
          {error}
        </p>
      ) : null}
    </div>
  );
}

const sellerInitial: CalcState<SellerResult> = {};
const buyerInitial: CalcState<BuyerResult> = {};

/**
 * `askYears`: the years paid matter only while the refund follows the payment period (refund_rule "mirror").
 * Market values only (K6): no developer is offered or named. `transferConditions` is the site text under the
 * transfer fee (K5).
 */
export function SellerCalculator({ deepLossBp, ctaHref, askYears, transferConditions }: { deepLossBp: number; ctaHref: string; askYears: boolean; transferConditions: string }) {
  const t = useTranslations("tools");
  const locale = useLocale();
  const [state, action] = useActionState(sellerCalcAction, sellerInitial);
  const e = state.errors ?? {};
  const v = state.values ?? {};
  const years = Array.from({ length: 30 }, (_, i) => i + 1);

  return (
    <div className="grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-14">
      <form action={action} className="flex flex-col gap-5" noValidate>
        <input type="hidden" name="locale" value={locale} />
        {state.message && Object.keys(e).length === 0 ? <Notice tone="error">{state.message}</Notice> : null}
        <Field label={t("seller.total")} name="total_price" inputMode="numeric" dir="ltr" required defaultValue={v.total_price} error={e.total_price} hint={t("seller.totalHint")} autoComplete="off" />
        <Field label={t("seller.paid")} name="paid_amount" inputMode="numeric" dir="ltr" required defaultValue={v.paid_amount} error={e.paid_amount} hint={t("seller.paidHint")} autoComplete="off" />
        {askYears ? (
          <SelectField label={t("seller.years")} name="years_paid" defaultValue={v.years_paid ?? "3"} hint={t("seller.yearsHint")} error={e.years_paid}>
            {years.map((n) => (
              <option key={n} value={n}>
                {t("seller.yearsOption", { count: n })}
              </option>
            ))}
          </SelectField>
        ) : (
          <input type="hidden" name="years_paid" value="1" />
        )}
        <Field label={t("seller.overdue")} name="overdue_amount" inputMode="numeric" dir="ltr" defaultValue={v.overdue_amount} error={e.overdue_amount} hint={t("seller.overdueHint")} autoComplete="off" />
        <div className="max-w-xs">
          <Submit pendingLabel={t("pending")}>{t("submit")}</Submit>
        </div>
      </form>

      <div aria-live="polite">{state.result ? <SellerAnswer result={state.result} deepLossBp={deepLossBp} ctaHref={ctaHref} transferConditions={transferConditions} /> : <EmptyAnswer text={t("seller.waiting")} />}</div>
    </div>
  );
}

function EmptyAnswer({ text }: { text: string }) {
  return (
    <div className="flex h-full min-h-48 items-center rounded-sm border border-dashed border-line-strong p-6 text-small text-text-3">
      <p>{text}</p>
    </div>
  );
}

function Line({ label, value, note, strong = false }: { label: string; value: string; note?: string | undefined; strong?: boolean }) {
  return (
    <div className="flex items-baseline justify-between gap-4 border-t border-line py-3">
      <dt className={strong ? "font-semibold text-text-1" : "text-text-2"}>
        {label}
        {note ? <span className="mt-0.5 block text-micro font-normal text-text-3">{note}</span> : null}
      </dt>
      <dd className={`shrink-0 tabular ${strong ? "text-lead font-semibold text-text-1" : "font-medium text-text-1"}`} dir="ltr">
        {value}
      </dd>
    </div>
  );
}

function SellerAnswer({ result, deepLossBp, ctaHref, transferConditions }: { result: SellerResult; deepLossBp: number; ctaHref: string; transferConditions: string }) {
  const t = useTranslations("tools");
  const { cancel, transfer } = result;
  const currency = t("currency");
  const paid = transfer.receive;
  const range = (a: number, b: number) => (a === b ? money(a) : `${money(a)} – ${money(b)}`);
  const zone = cancel.zone === "deep_loss" ? t("result.zone.deep_loss", { line: percent(deepLossBp) }) : t(`result.zone.${cancel.zone}`);

  return (
    <section className="flex flex-col gap-6 rounded-sm border border-line-strong bg-surface-1 p-5 sm:p-6" aria-labelledby="seller-answer">
      <header className="flex flex-col gap-2">
        <h2 id="seller-answer" className="text-h3">
          {t("result.title")}
        </h2>
        <p className={`text-small font-semibold ${cancel.zone === "moderate_loss" ? "text-text-2" : "text-error"}`}>{zone}</p>
      </header>

      <ExitDrawing paid={paid} refundMin={cancel.refund_min} refundMax={cancel.refund_max} net={transfer.net} currency={currency} labels={{ paid: t("result.paid"), cancel: t("result.cancelTitle"), sell: t("result.sellTitle") }} />

      <div className="grid gap-6 md:grid-cols-2">
        <div className="flex flex-col text-small">
          <h3 className="pb-2 text-body font-semibold">{t("result.cancelTitle")}</h3>
          <dl className="flex flex-col">
            <Line
              label={t("result.deduction")}
              value={`− ${range(cancel.penalty_min, cancel.penalty_max)}`}
              note={t(cancel.penalty_basis === "paid" ? "result.basisPaid" : "result.basisTotal")}
            />
            {cancel.late_fees > 0 ? <Line label={t("result.lateFees")} value={`− ${money(cancel.late_fees)}`} /> : null}
            <Line label={t("result.refund")} value={range(cancel.refund_min, cancel.refund_max)} note={cancel.refund_rule === "lump" ? t("result.refundLump") : t("result.refundYears", { years: cancel.refund_years })} strong />
            {cancel.refund_pv_min !== null ? <Line label={t("result.refundToday")} value={money(cancel.refund_pv_min)} note={t("result.refundTodayNote")} /> : null}
          </dl>
        </div>
        <div className="flex flex-col text-small">
          <h3 className="pb-2 text-body font-semibold">{t("result.sellTitle")}</h3>
          <dl className="flex flex-col">
            <Line label={t("result.receive")} value={money(transfer.receive)} />
            <Line label={t("result.transferFee")} value={`− ${money(transfer.transfer_fee)}`} note={t("result.transferFeeNote")} />
            {transfer.arrears_to_developer > 0 ? <Line label={t("result.arrears")} value={`− ${money(transfer.arrears_to_developer)}`} /> : null}
            {transfer.late_fees > 0 ? <Line label={t("result.lateFees")} value={`− ${money(transfer.late_fees)}`} note={t("result.lateFeesNote")} /> : null}
            <Line label={t("result.loktaFee")} value={money(transfer.lokta_fee)} />
            <Line label={t("result.net")} value={money(transfer.net)} strong />
          </dl>
          <p className="border-t border-line pt-3 text-micro text-text-3">{transferConditions}</p>
        </div>
      </div>

      <div className="flex flex-col gap-1 border-t-[1.5px] border-text-3 pt-4">
        <span className="text-small text-text-2">{t("result.difference")}</span>
        <span className="text-h3 font-semibold text-success tabular" dir="ltr">
          {range(result.advantage_min, result.advantage_max)} {currency}
        </span>
      </div>

      {result.provisional.length > 0 ? <p className="text-micro text-text-3">{t("result.provisional")}</p> : null}

      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
        <Link href={ctaHref} className="inline-flex h-control-lg items-center justify-center rounded-sm bg-gold px-6 font-semibold text-on-gold transition-colors duration-150 hover:bg-gold-bright">
          {t("result.cta")}
        </Link>
        <span className="text-small text-text-3">{t("result.ctaHint")}</span>
      </div>
    </section>
  );
}

export function BuyerCalculator() {
  const t = useTranslations("tools");
  const locale = useLocale();
  const [state, action] = useActionState(buyerCalcAction, buyerInitial);
  const e = state.errors ?? {};
  const v = state.values ?? {};

  return (
    <div className="grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-14">
      <form action={action} className="flex flex-col gap-5" noValidate>
        <input type="hidden" name="locale" value={locale} />
        {state.message && Object.keys(e).length === 0 ? <Notice tone="error">{state.message}</Notice> : null}
        <Field label={t("buyer.contract")} name="contract_price" inputMode="numeric" dir="ltr" required defaultValue={v.contract_price} error={e.contract_price} hint={t("buyer.contractHint")} autoComplete="off" />
        <Field label={t("buyer.today")} name="price_today" inputMode="numeric" dir="ltr" required defaultValue={v.price_today} error={e.price_today} hint={t("buyer.todayHint")} autoComplete="off" />
        <div className="max-w-xs">
          <Submit pendingLabel={t("pending")}>{t("submit")}</Submit>
        </div>
      </form>

      <div aria-live="polite">{state.result ? <BuyerAnswer result={state.result} /> : <EmptyAnswer text={t("buyer.waiting")} />}</div>
    </div>
  );
}

function BuyerAnswer({ result }: { result: BuyerResult }) {
  const t = useTranslations("tools");
  const currency = t("currency");
  const ahead = result.gain >= 0;

  return (
    <section className="flex flex-col gap-5 rounded-sm border border-line-strong bg-surface-1 p-5 sm:p-6" aria-labelledby="buyer-answer">
      <h2 id="buyer-answer" className="text-h3">
        {t("buyer.result.title")}
      </h2>
      <dl className="flex flex-col text-small">
        <Line label={t("buyer.result.today")} value={money(result.price_today)} />
        <Line label={t("buyer.result.contract")} value={`− ${money(result.contract_price)}`} />
        <Line label={t("buyer.result.fee", { rate: percent(result.fee_bp) })} value={`− ${money(result.fee + result.fee_vat)}`} note={t("buyer.result.feeNote")} />
      </dl>
      <div className="flex flex-col gap-1 border-t-[1.5px] border-text-3 pt-4">
        <span className="text-small text-text-2">{t(ahead ? "buyer.result.gain" : "buyer.result.loss")}</span>
        <span className={`text-h3 font-semibold tabular ${ahead ? "text-success" : "text-error"}`} dir="ltr">
          {money(Math.abs(result.gain))} {currency}
        </span>
        {ahead ? <span className="text-small text-text-2">{t("buyer.result.gainShare", { share: percent(result.gain_bp) })}</span> : null}
      </div>
      <p className="text-micro text-text-3">{t("buyer.result.noTransferFee")}</p>
      <div>
        <Link href="/opportunities" className="inline-flex h-control-lg items-center justify-center rounded-sm bg-gold px-6 font-semibold text-on-gold transition-colors duration-150 hover:bg-gold-bright">
          {t("buyer.result.cta")}
        </Link>
      </div>
    </section>
  );
}
