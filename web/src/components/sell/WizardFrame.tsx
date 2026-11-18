"use client";

import { useTranslations } from "next-intl";
import type { ReactNode } from "react";
import { Link, useRouter } from "@/i18n/navigation";
import { useWizard } from "./WizardContext";

/*
 * The frame of every step (board 07): a bar with the draft's "saved" stamp and "save and exit", the owner-only notice
 * (PLAN 2.2.1, fixed on every step), the six steps on one dimension line (the finished length solid), then the step
 * with its side panel.
 */

export const STEP_COUNT = 6;

function SavedStamp() {
  const t = useTranslations("sell.frame");
  const { status } = useWizard();
  const text =
    status.kind === "saving"
      ? t("saving")
      : status.kind === "saved"
        ? t("saved", { time: status.at.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" }) })
        : status.kind === "error"
          ? t("saveFailed")
          : t("draft");

  return (
    <span role="status" aria-live="polite" className={`text-small ${status.kind === "error" ? "text-error" : "text-text-3"}`}>
      {text}
    </span>
  );
}

function Stepper({ current }: { current: number }) {
  const t = useTranslations("sell.steps");
  const { unit, flush } = useWizard();
  const router = useRouter();
  const reached = Math.min(unit.step_reached, STEP_COUNT);

  return (
    <nav aria-label={t("label")} className="relative">
      <div aria-hidden="true" className="absolute inset-x-0 top-4 h-px bg-line" />
      <div
        aria-hidden="true"
        className="absolute start-0 top-4 h-0.5 bg-text-1 transition-[width] duration-300"
        style={{ width: `${((Math.max(reached, current) - 1) / (STEP_COUNT - 1)) * 100}%` }}
      />
      <ol className="relative grid grid-cols-6 gap-1">
        {Array.from({ length: STEP_COUNT }, (_, i) => i + 1).map((n) => {
          const done = n < reached && n !== current;
          const open = n <= reached;
          const label = t(String(n));
          const body = (
            <span className="flex flex-col items-start gap-2">
              <span
                className={`grid size-8 place-items-center rounded-full border font-mono text-annotation ${n === current ? "border-text-1 bg-text-1 text-bg-0" : done ? "border-text-1 bg-bg-0 text-text-1" : "border-line-strong bg-bg-0 text-text-3"}`}
                dir="ltr"
              >
                {done ? "✓" : String(n).padStart(2, "0")}
              </span>
              <span className={`hidden text-small md:block ${n === current ? "text-text-1" : "text-text-3"}`}>{label}</span>
            </span>
          );

          return (
            <li key={n}>
              {open && n !== current ? (
                <a
                  href={`./${n}`}
                  onClick={async (e) => {
                    e.preventDefault();
                    await flush();
                    router.push(`/sell/${unit.id}/${n}`);
                  }}
                  className="block hover:opacity-80"
                  aria-label={label}
                >
                  {body}
                </a>
              ) : (
                <span aria-current={n === current ? "step" : undefined} aria-label={label}>
                  {body}
                </span>
              )}
            </li>
          );
        })}
      </ol>
      <p className="mt-3 font-mono text-annotation text-text-3 md:hidden" dir="ltr">
        {String(current).padStart(2, "0")} / {String(STEP_COUNT).padStart(2, "0")} · {t(String(current))}
      </p>
    </nav>
  );
}

export function WizardFrame({ step, title, intro, aside, children }: { step: number; title: string; intro?: string; aside?: ReactNode; children: ReactNode }) {
  const t = useTranslations("sell.frame");
  const { unit, flush, reference } = useWizard();
  const router = useRouter();

  return (
    <section className="px-gutter-mobile pt-8 pb-20 md:px-gutter md:pt-12 md:pb-24">
      <div className="mx-auto flex max-w-6xl flex-col gap-8">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
          <div className="flex items-baseline gap-3">
            <span className="text-h3">{t("title")}</span>
            {unit.reference ? (
              <span className="font-mono text-annotation text-text-3" dir="ltr">
                {unit.reference}
              </span>
            ) : null}
            <SavedStamp />
          </div>
          <Link
            href="/account/units"
            onClick={async (e) => {
              e.preventDefault();
              await flush();
              router.push("/account/units");
            }}
            className="text-small text-text-2 underline-offset-4 hover:text-text-1 hover:underline"
          >
            {t("saveExit")}
          </Link>
        </div>

        <div className="rounded-sm border border-line bg-surface-1 px-4 py-3 text-small text-text-2">
          <strong className="text-text-1">{t("ownerOnlyTitle")}</strong> {reference.texts["seller.notice.owner_only"] ?? ""}
        </div>

        <Stepper current={step} />

        <div className={`grid gap-10 ${aside ? "lg:grid-cols-[minmax(0,1fr)_22rem]" : ""}`}>
          <div className="flex min-w-0 flex-col gap-8">
            <header className="flex flex-col gap-3">
              <span className="hidden font-mono text-annotation text-text-3 md:block" dir="ltr">
                {String(step).padStart(2, "0")} / {String(STEP_COUNT).padStart(2, "0")}
              </span>
              <h1 className="text-h1-m md:text-h2">{title}</h1>
              {intro ? <p className="max-w-2xl text-body text-text-2">{intro}</p> : null}
            </header>
            {children}
          </div>
          {aside ? <aside className="flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">{aside}</aside> : null}
        </div>
      </div>
    </section>
  );
}

/** "Back" and "Next" under a step (board 07: "→ رجوع" and "التالي: ..."). */
export function StepNav({
  backHref,
  backLabel,
  nextLabel,
  onNext,
  disabled,
  waiting,
}: {
  backHref?: string;
  backLabel?: string;
  nextLabel: string;
  onNext: () => void;
  disabled?: boolean;
  waiting?: string | null;
}) {
  const { unit, flush } = useWizard();
  const router = useRouter();

  return (
    <div className="flex flex-col gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
      {backHref && backLabel ? (
        <a
          href={backHref}
          onClick={async (e) => {
            e.preventDefault();
            await flush();
            router.push(`/sell/${unit.id}/${backHref}`);
          }}
          className="text-body text-text-2 hover:text-text-1"
        >
          {backLabel}
        </a>
      ) : (
        <span />
      )}
      <div className="flex flex-col items-stretch gap-2 sm:items-end">
        {waiting ? <span className="text-small text-warning">{waiting}</span> : null}
        <button
          type="button"
          onClick={onNext}
          disabled={disabled}
          className="inline-flex h-control-lg items-center justify-center gap-3 rounded-sm bg-gold px-6 text-[1rem] font-semibold text-on-gold transition-colors duration-150 hover:bg-gold-bright disabled:opacity-50"
        >
          {nextLabel}
          <span aria-hidden="true" className="rtl:rotate-180">
            →
          </span>
        </button>
      </div>
    </div>
  );
}
