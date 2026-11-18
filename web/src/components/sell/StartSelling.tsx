"use client";

import { useLocale, useTranslations } from "next-intl";
import { useRef, useState } from "react";
import { createDraftAction } from "@/lib/actions/sell";
import { Submit } from "../account/Form";

/*
 * "Start" on /sell (PLAN 2.2.1, board 07.3): the no-over rule opens once, before step 1, and must be accepted.
 * The acceptance is sent with the new draft; core stores it per unit with the text version.
 */
export function StartSelling({ title, body, confirm }: { title: string; body: string; confirm: string }) {
  const t = useTranslations("sell.start");
  const locale = useLocale();
  const dialog = useRef<HTMLDialogElement>(null);
  const [agreed, setAgreed] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => dialog.current?.showModal()}
        className="inline-flex h-control-lg items-center justify-center gap-3 self-start rounded-sm bg-gold px-8 text-[1rem] font-semibold text-on-gold hover:bg-gold-bright"
      >
        {t("start")}
        <span aria-hidden="true" className="rtl:rotate-180">
          →
        </span>
      </button>
      <dialog
        ref={dialog}
        aria-labelledby="no-over-title"
        className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-sm border border-line-strong bg-bg-1 p-0 text-text-1 backdrop:bg-black/70"
      >
        <form action={createDraftAction} className="flex flex-col gap-5 p-6">
          <input type="hidden" name="locale" value={locale} />
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            {t("rule")}
          </span>
          <h2 id="no-over-title" className="text-h2">
            {title}
          </h2>
          <p className="text-body text-text-2">{body}</p>
          <label className="flex min-h-11 items-start gap-3 text-body">
            <input type="checkbox" name="no_over" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} className="mt-1.5 size-4 accent-[var(--color-text-1)]" />
            {confirm}
          </label>
          <div className="flex flex-col gap-3 sm:flex-row-reverse sm:items-center sm:justify-between">
            <div className={agreed ? "" : "pointer-events-none opacity-50"} aria-disabled={!agreed}>
              <Submit pendingLabel={t("opening")}>{t("go")}</Submit>
            </div>
            <button type="button" onClick={() => dialog.current?.close()} className="text-small text-text-2 hover:text-text-1">
              {t("cancel")}
            </button>
          </div>
        </form>
      </dialog>
    </>
  );
}
