import type { ReactNode } from "react";

/**
 * A text page (about, how it works, FAQ, buyers, contact, legal): a drawing-number style label, the title, the
 * intro, then the body inside .lk-content (the only place links turn gold on hover).
 */
export function ContentPage({
  label,
  title,
  intro,
  children,
}: {
  label: string;
  title: string;
  intro?: string;
  children: ReactNode;
}) {
  return (
    <article className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <div className="grid gap-10 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-14">
        <header className="flex flex-col gap-5">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            {label}
          </span>
          <h1 className="text-h1-m md:text-h1">{title}</h1>
          {intro ? <p className="text-lead text-text-2">{intro}</p> : null}
        </header>
        <div className="lk-content flex max-w-[44rem] flex-col gap-10 text-text-2">{children}</div>
      </div>
    </article>
  );
}

/** Paragraphs of one text block. */
export function Paragraphs({ items }: { items: string[] }) {
  return (
    <div>
      {items.map((p, i) => (
        <p key={i}>{p}</p>
      ))}
    </div>
  );
}
