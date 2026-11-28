"use client";

import { useTranslations } from "next-intl";
import { createContext, useContext, type ReactNode } from "react";
import { Link, useRouter } from "@/i18n/navigation";
import { stepProgress, stepState, STEPS, type SellerReview } from "@/lib/sell";
import { Wordmark } from "../Wordmark";
import { useWizard } from "./WizardContext";

/*
 * The frame of every step (board 07.1, layout approved 2026-11-24). The form has its own quiet chrome: the site's
 * header and footer step aside (CSS, see [data-wizard] in the theme), and a bar with the wordmark, the draft's "saved"
 * stamp and "save and exit" takes their place. Then the owner-only notice (PLAN 2.2.1, on every step), and on wide
 * screens three columns: the six steps as a rail with the 0% box, the step, and its side panel. Phones show the step
 * name with a progress dimension line at the top and keep Next in a dock at the bottom; a step can put a one-line
 * summary of its side panel in that dock (step 3: the exit report).
 */

export const STEP_COUNT = STEPS;

/** What a step puts in the phone dock above its Next button. */
const DockContext = createContext<ReactNode>(null);

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

/** Leaves the step after the pending autosave is written. */
function useLeave() {
  const { flush } = useWizard();
  const router = useRouter();

  return async (href: string) => {
    await flush();
    router.push(href);
  };
}

function Rail({ current }: { current: number }) {
  const t = useTranslations("sell.steps");
  const f = useTranslations("sell.frame");
  const { unit } = useWizard();
  const leave = useLeave();

  return (
    <nav aria-label={t("label")} className="hidden border-e border-line py-8 lg:flex lg:flex-col">
      <ol className="flex flex-col">
        {Array.from({ length: STEPS }, (_, i) => i + 1).map((n) => {
          const state = stepState(n, current, unit.step_reached);
          const body = (
            <>
              <span className="font-mono text-annotation text-text-3" dir="ltr">
                {String(n).padStart(2, "0")}
              </span>
              <span>{t(String(n))}</span>
              {state === "done" ? (
                <span aria-hidden="true" className="text-success">
                  ✓
                </span>
              ) : state === "current" ? (
                <span className="text-micro text-text-3">{f("current")}</span>
              ) : (
                <span />
              )}
            </>
          );
          const row = "grid min-h-12 grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-2 px-6 py-2";

          return (
            <li key={n}>
              {state === "done" || state === "open" ? (
                <a
                  href={`./${n}`}
                  onClick={(e) => {
                    e.preventDefault();
                    void leave(`/sell/${unit.id}/${n}`);
                  }}
                  className={`${row} text-text-2 hover:bg-surface-1 hover:text-text-1`}
                >
                  {body}
                </a>
              ) : state === "current" ? (
                <span aria-current="step" className={`${row} bg-surface-1 font-semibold text-text-1 shadow-[inset_-2px_0_0_var(--color-text-1)] ltr:shadow-[inset_2px_0_0_var(--color-text-1)]`}>
                  {body}
                </span>
              ) : (
                <span className={`${row} text-text-3`}>{body}</span>
              )}
            </li>
          );
        })}
      </ol>
      <div className="mx-5 mt-auto flex flex-col gap-2 border border-line-strong p-4">
        <div className="flex items-baseline justify-between gap-3">
          <span className="text-small">{f("commission")}</span>
          <span className="text-h3 tabular" dir="ltr">
            0%
          </span>
        </div>
        <p className="text-micro text-text-2">{f("noOver")}</p>
      </div>
    </nav>
  );
}

/** Phones and tablets: the step's name, 03 / 06 and the progress as a dimension line. */
function TopProgress({ current }: { current: number }) {
  const t = useTranslations("sell.steps");
  const { unit } = useWizard();
  const width = stepProgress(current, unit.step_reached);

  return (
    <div className="flex flex-col gap-2 px-gutter-mobile pt-4 md:px-gutter lg:hidden">
      <div className="flex items-baseline justify-between gap-3">
        <span className="font-semibold">{t(String(current))}</span>
        <span className="font-mono text-annotation text-text-3" dir="ltr">
          {String(current).padStart(2, "0")} / {String(STEPS).padStart(2, "0")}
        </span>
      </div>
      <div aria-hidden="true" className="relative h-3.5">
        <span className="absolute inset-x-0 top-1/2 h-px bg-line-strong" />
        <span className="absolute start-0 top-[6px] h-[3px] bg-text-1" style={{ width: `${width}%` }} />
        <span className="absolute start-0 top-0 h-3.5 w-px bg-text-1" />
        <span className="absolute top-0 h-3.5 w-px bg-text-1" style={{ insetInlineStart: `${width}%` }} />
        <span className="absolute end-0 top-0 h-3.5 w-px bg-line-strong" />
      </div>
    </div>
  );
}

/** What the team asked for when it sent the unit back (part 7.2): the reason, notes per field, documents to replace. */
export function ReviewNotice({ review }: { review: SellerReview }) {
  const t = useTranslations("sell.frame");

  return (
    <div role="status" className="flex flex-col gap-3 rounded-sm border border-warning/60 bg-surface-1 p-5">
      <p className="font-semibold text-text-1">{review.outcome === "rejected" ? t("reviewRejected") : t("reviewTitle")}</p>
      {review.reason ? <p className="whitespace-pre-line text-body text-text-2">{review.reason}</p> : null}
      {review.fields.length > 0 ? (
        <div className="flex flex-col gap-1">
          <p className="text-small font-semibold text-text-1">{t("reviewFields")}</p>
          <ul className="list-disc ps-5 text-small text-text-2">
            {review.fields.map((f) => (
              <li key={f.field}>
                {f.label}: {f.note}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {review.documents.length > 0 ? (
        <div className="flex flex-col gap-1">
          <p className="text-small font-semibold text-text-1">{t("reviewDocuments")}</p>
          <ul className="list-disc ps-5 text-small text-text-2">
            {review.documents.map((d) => (
              <li key={d.type}>
                {d.label}
                {d.note ? `: ${d.note}` : ""}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}

export function WizardFrame({
  step,
  title,
  intro,
  aside,
  dock,
  children,
}: {
  step: number;
  title: string;
  intro?: string;
  aside?: ReactNode;
  /** A one-line summary of the side panel for the phone dock; when given, the panel itself is shown there. */
  dock?: ReactNode;
  children: ReactNode;
}) {
  const t = useTranslations("sell.frame");
  const nav = useTranslations("nav");
  const { unit, reference } = useWizard();
  const leave = useLeave();

  return (
    <DockContext.Provider value={dock ?? null}>
      <div data-wizard className="flex min-h-dvh flex-col">
        <header className="relative flex min-h-18 items-center justify-between gap-4 border-b border-line px-gutter-mobile md:px-gutter">
          <a href="#step" className="absolute start-4 top-2 z-50 -translate-y-20 bg-gold px-4 py-2 text-on-gold focus:translate-y-0">
            {nav("skip")}
          </a>
          <div className="flex min-w-0 items-center gap-4 md:gap-5">
            <Link href="/" aria-label={t("home")} className="inline-flex min-h-control-md items-center">
              <Wordmark size="sm" />
            </Link>
            <span aria-hidden="true" className="hidden h-6 w-px bg-line md:block" />
            <span className="hidden truncate font-semibold sm:inline">{t("title")}</span>
            {unit.reference ? (
              <span className="hidden font-mono text-annotation text-text-3 md:inline" dir="ltr">
                {unit.reference}
              </span>
            ) : null}
            <span className="hidden md:inline">
              <SavedStamp />
            </span>
          </div>
          <Link
            href="/account/units"
            onClick={(e) => {
              e.preventDefault();
              void leave("/account/units");
            }}
            className="inline-flex min-h-control-md shrink-0 items-center text-small text-text-2 underline underline-offset-4 hover:text-text-1"
          >
            {t("saveExit")}
          </Link>
        </header>

        <p className="border-b border-line bg-bg-1 px-gutter-mobile py-2.5 text-small text-text-2 md:px-gutter">
          <span aria-hidden="true" className="me-2 inline-block size-1.5 bg-gold align-middle" />
          <strong className="text-text-1">{t("ownerOnlyTitle")}</strong> {reference.texts["seller.notice.owner_only"] ?? ""}
        </p>

        <TopProgress current={step} />

        <div className={`grid flex-1 ${aside ? "lg:grid-cols-[15rem_minmax(0,1fr)_21rem]" : "lg:grid-cols-[15rem_minmax(0,1fr)]"}`}>
          <Rail current={step} />
          <section id="step" tabIndex={-1} aria-labelledby="step-title" className="flex min-w-0 flex-col gap-8 px-gutter-mobile pt-6 pb-10 outline-none md:px-gutter lg:max-w-[52rem] lg:py-10">
            <header className="flex flex-col gap-3">
              <div className="flex items-center gap-4">
                <span aria-hidden="true" className="grid size-12 shrink-0 grid-rows-2 rounded-full border border-line-strong font-mono text-annotation" dir="ltr">
                  <span className="grid place-items-end justify-center border-b border-line-strong pb-0.5">{String(step).padStart(2, "0")}</span>
                  <span className="grid place-items-start justify-center pt-0.5 text-text-3">{String(STEPS).padStart(2, "0")}</span>
                </span>
                <h1 id="step-title" className="text-h1-m md:text-h1">{title}</h1>
              </div>
              {intro ? <p className="max-w-2xl text-body text-text-2">{intro}</p> : null}
            </header>
            {unit.review ? <ReviewNotice review={unit.review} /> : null}
            {children}
          </section>
          {aside ? (
            <aside className={`flex-col gap-4 px-gutter-mobile pb-10 md:px-gutter lg:border-s lg:border-line lg:px-6 lg:py-10 ${dock ? "hidden lg:flex" : "flex"}`}>
              <div className="flex flex-col gap-4 lg:sticky lg:top-6">{aside}</div>
            </aside>
          ) : null}
        </div>
      </div>
    </DockContext.Provider>
  );
}

/**
 * "Back" and "Next" under a step (board 07: "→ رجوع" and "التالي: ..."). On phones and tablets they sit in a dock at
 * the bottom of the screen, with the step's summary line above them when it has one.
 */
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
  const { unit } = useWizard();
  const leave = useLeave();
  const dock = useContext(DockContext);

  return (
    <div
      data-sticky-bar
      className="fixed inset-x-0 bottom-0 z-30 flex flex-col gap-2 border-t border-line-strong bg-bg-1/95 px-gutter-mobile py-3 backdrop-blur md:px-gutter lg:static lg:z-auto lg:border-line lg:bg-transparent lg:px-0 lg:pt-6 lg:pb-0 lg:backdrop-blur-none"
    >
      {dock ? <div className="lg:hidden">{dock}</div> : null}
      {waiting ? <span className="text-small text-warning">{waiting}</span> : null}
      <div className="flex items-center gap-3 lg:justify-between">
        {backHref && backLabel ? (
          <a
            href={backHref}
            onClick={(e) => {
              e.preventDefault();
              void leave(`/sell/${unit.id}/${backHref}`);
            }}
            aria-label={backLabel}
            className="inline-flex size-12 shrink-0 items-center justify-center rounded-sm border border-line-strong text-text-2 hover:text-text-1 lg:size-auto lg:min-h-control-md lg:justify-start lg:border-0 lg:text-body"
          >
            <span aria-hidden="true" className="lg:hidden">
              <span className="inline-block rtl:rotate-180">←</span>
            </span>
            <span className="hidden lg:inline">{backLabel}</span>
          </a>
        ) : (
          <span className="hidden lg:block" />
        )}
        <button
          type="button"
          onClick={onNext}
          disabled={disabled}
          className="inline-flex h-control-lg flex-1 items-center justify-center gap-3 rounded-sm bg-gold px-6 text-[1rem] font-semibold text-on-gold transition-colors duration-150 hover:bg-gold-bright disabled:opacity-50 lg:flex-none"
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
