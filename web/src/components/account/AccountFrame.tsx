import type { ReactNode } from "react";

/** The frame of the sign-in pages: a drawing-number label, the title and intro, and a narrow form column. */
export function AuthFrame({ label, title, intro, children }: { label: string; title: string; intro?: string; children: ReactNode }) {
  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <div className="mx-auto flex max-w-md flex-col gap-8">
        <header className="flex flex-col gap-4">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            {label}
          </span>
          <h1 className="text-h1-m md:text-h2">{title}</h1>
          {intro ? <p className="text-body text-text-2">{intro}</p> : null}
        </header>
        {children}
      </div>
    </section>
  );
}
