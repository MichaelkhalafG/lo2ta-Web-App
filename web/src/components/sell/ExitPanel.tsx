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
          {money(cancel.refund_min)} – {money(cancel.refund_max)}
        </span>
        <span className="text-small text-text-3">
          {t("cancelNote", { years: cancel.refund_years })}
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
            <dd>− {money(transfer.transfer_fee)}</dd>
          </div>
          {transfer.arrears_to_developer > 0 ? (
            <div className="flex justify-between gap-3">
              <dt className="text-text-2">{t("arrears")}</dt>
              <dd>− {money(transfer.arrears_to_developer)}</dd>
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
