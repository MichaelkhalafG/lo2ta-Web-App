"use client";

import { useTranslations } from "next-intl";
import { formatMoney } from "./fields";
import { useWizard } from "./WizardContext";

/*
 * The live exit report beside step 3 and on step 6 (board 07.1, PLAN 4.2). Every figure comes from core's
 * calculation after each autosave; nothing is computed here. Provisional settings are marked as estimates.
 */

export function ExitPanel({ compact = false }: { compact?: boolean }) {
  const t = useTranslations("sell.exit");
  const { unit } = useWizard();
  const report = unit.exit_report;

  if (!report) {
    return (
      <div className="rounded-sm border border-line bg-surface-1 p-5 text-small text-text-3">
        <p className="mb-2 font-mono text-annotation" dir="ltr">
          {t("label")}
        </p>
        {t("waiting")}
      </div>
    );
  }

  const { cancel, transfer } = report;
  const provisional = report.provisional.length > 0;
  const money = (n: number) => <span className="font-medium tabular" dir="ltr">{formatMoney(n)}</span>;

  return (
    <div className="flex flex-col gap-5 rounded-sm border border-line bg-surface-1 p-5">
      <header className="flex items-center justify-between gap-3">
        <h2 className="text-body font-semibold">{t("title")}</h2>
        <span className="font-mono text-micro text-text-3" dir="ltr">
          {provisional ? t("estimate") : t("live")}
        </span>
      </header>

      <div className="flex flex-col gap-1">
        <span className="text-small text-text-3">{t("cancelTitle")}</span>
        <span className="text-h3">
          {money(cancel.refund_min)}
          {cancel.refund_max !== cancel.refund_min ? <> – {money(cancel.refund_max)}</> : null}
        </span>
        <span className="text-small text-text-3">
          {cancel.refund_rule === "lump" ? t("cancelNoteLump") : t("cancelNote", { years: cancel.refund_years })}
          {cancel.zone === "wipeout" ? ` · ${t("zone.wipeout")}` : cancel.zone === "deep_loss" ? ` · ${t("zone.deep_loss")}` : ""}
        </span>
      </div>

      <div className="flex flex-col gap-1">
        <span className="text-small text-text-3">{t("sellTitle")}</span>
        <span className="text-h3 text-text-1">{money(transfer.net)}</span>
        <span className="text-small text-success">{t("advantage", { amount: formatMoney(report.advantage_min) })}</span>
      </div>

      {compact ? null : (
        <dl className="flex flex-col gap-2 border-t border-line pt-4 text-small">
          <div className="flex justify-between gap-3">
            <dt className="text-text-2">{t("receive")}</dt>
            <dd>{money(transfer.receive)}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-text-2">
              {t("transferFee")}
              <span className="block text-micro text-text-3">{t("transferFeeNote")}</span>
            </dt>
            <dd className="whitespace-nowrap">− {money(transfer.transfer_fee)}</dd>
          </div>
          {transfer.arrears_to_developer > 0 ? (
            <div className="flex justify-between gap-3">
              <dt className="text-text-2">{t("arrears")}</dt>
              <dd className="whitespace-nowrap">− {money(transfer.arrears_to_developer)}</dd>
            </div>
          ) : null}
          {(transfer.late_fees ?? 0) > 0 ? (
            <div className="flex justify-between gap-3">
              <dt className="text-text-2">{t("lateFees")}</dt>
              <dd className="whitespace-nowrap">− {money(transfer.late_fees ?? 0)}</dd>
            </div>
          ) : null}
          <div className="flex justify-between gap-3">
            <dt className="text-text-2">{t("loktaFee")}</dt>
            <dd>{money(0)}</dd>
          </div>
          <div className="flex justify-between gap-3 border-t border-line pt-2 text-body">
            <dt className="font-semibold">{t("net")}</dt>
            <dd className="font-semibold">{money(transfer.net)}</dd>
          </div>
          {transfer.arrears_self > 0 ? <p className="text-text-3">{t("arrearsSelf", { amount: formatMoney(transfer.arrears_self) })}</p> : null}
        </dl>
      )}

      {provisional ? <p className="text-micro text-text-3">{t("provisional")}</p> : null}
    </div>
  );
}

/**
 * The exit report folded into one line for the phone dock (approved proposal 2026-11-24): the difference in the
 * seller's favour; opening it shows the short report.
 */
export function ExitDock() {
  const t = useTranslations("sell.exit");
  const { unit } = useWizard();
  const report = unit.exit_report;

  if (!report) {
    return <p className="text-small text-text-3">{t("summaryWaiting")}</p>;
  }

  return (
    <details className="group">
      <summary className="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 text-small">
        <span>{t("summary")}</span>
        <span className="flex items-center gap-2">
          <span className="font-semibold text-success tabular" dir="ltr">
            +{formatMoney(report.advantage_min)}
          </span>
          <svg aria-hidden="true" viewBox="0 0 10 6" className="h-1.5 w-2.5 fill-none stroke-text-3 transition-transform group-open:rotate-180" strokeWidth="1.5">
            <path d="M1 5l4-4 4 4" />
          </svg>
        </span>
      </summary>
      <div className="max-h-[50dvh] overflow-y-auto pt-2">
        <ExitPanel compact />
      </div>
    </details>
  );
}
