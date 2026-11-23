import type { ReactNode } from "react";

/**
 * A missing page or a failure (audit D6), drawn as a sheet that is not in the set: the sheet number in the title
 * block, a plain statement of what happened, and a way on. No apology, no mood: direction.
 */
export function StatePage({ sheet, label, title, body, children }: { sheet: string; label: string; title: string; body: string; children: ReactNode }) {
  return (
    <section className="px-gutter-mobile py-16 md:px-gutter md:py-24">
      <div className="mx-auto flex max-w-3xl flex-col gap-8">
        <dl className="grid w-fit grid-cols-[auto_auto] border border-line-strong text-small">
          <dt className="border-e border-line-strong px-3 py-2 text-text-3">{sheet}</dt>
          <dd className="px-3 py-2 font-mono font-medium" dir="ltr">
            {label}
          </dd>
        </dl>
        <h1 className="text-h1-m md:text-h1">{title}</h1>
        <p className="max-w-[36rem] text-lead text-text-2">{body}</p>
        <div className="flex flex-wrap items-center gap-4">{children}</div>
      </div>
    </section>
  );
}

export const primaryAction = "inline-flex h-control-lg items-center rounded-sm bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright";
export const secondaryAction = "inline-flex h-control-lg items-center rounded-sm border border-line-strong px-6 text-text-1 hover:bg-surface-2";
