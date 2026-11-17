import type { ReactNode } from "react";
import { AccountNav } from "./AccountParts";

/** The frame of the signed-in pages: label, title, the account sub-navigation, then the page. */
export function AccountPage({ label, title, intro, children }: { label: string; title: string; intro?: string; children: ReactNode }) {
  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-16 md:pb-24">
      <div className="mx-auto flex max-w-5xl flex-col gap-8">
        <header className="flex flex-col gap-4">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            {label}
          </span>
          <h1 className="text-h1-m md:text-h1">{title}</h1>
          {intro ? <p className="max-w-2xl text-body text-text-2">{intro}</p> : null}
        </header>
        <AccountNav />
        {children}
      </div>
    </section>
  );
}
