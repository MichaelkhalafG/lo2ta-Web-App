"use client";

import { useEffect, useRef } from "react";
import { Link, usePathname } from "@/i18n/navigation";
import { LanguageLink } from "../LanguageLink";
import { Wordmark } from "../Wordmark";

export type MobileGroup = { title: string | null; items: { href: string; label: string }[] };

/**
 * The phone menu (board 06.4): a full-screen native <dialog>, which gives focus trapping and Esc for free.
 */
export function MobileMenu({
  groups,
  labels,
}: {
  groups: MobileGroup[];
  labels: { open: string; close: string; login: string; language: string; languageTitle: string; legal: { href: string; label: string }[] };
}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const pathname = usePathname();

  useEffect(() => {
    dialog.current?.close();
  }, [pathname]);

  return (
    <>
      <button
        type="button"
        onClick={() => dialog.current?.showModal()}
        className="flex h-control-md items-center gap-2 rounded-sm border border-line-strong px-3 text-small text-text-1"
        aria-haspopup="dialog"
      >
        <span aria-hidden="true" className="flex w-4 flex-col gap-1">
          <span className="h-px bg-text-1" />
          <span className="h-px bg-text-1" />
          <span className="h-px bg-text-1" />
        </span>
        {labels.open}
      </button>
      <dialog
        ref={dialog}
        aria-label={labels.open}
        className="m-0 h-dvh max-h-none w-screen max-w-none bg-bg-0 p-0 text-text-1 backdrop:bg-bg-0"
      >
        <div className="flex h-header items-center justify-between border-b border-line px-gutter-mobile">
          <Wordmark size="sm" />
          <button
            type="button"
            onClick={() => dialog.current?.close()}
            className="flex size-11 items-center justify-center text-[1.25rem] text-text-1"
            aria-label={labels.close}
          >
            <span aria-hidden="true">✕</span>
          </button>
        </div>
        <nav className="flex flex-col gap-8 px-gutter-mobile py-8">
          {groups.map((group, index) => (
            <div key={group.title ?? index} className="flex flex-col">
              {group.title ? (
                <span className="border-b border-line pb-2.5 text-small text-text-3">{group.title}</span>
              ) : null}
              <ul>
                {group.items.map((item) => (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      aria-current={pathname === item.href ? "page" : undefined}
                      className="flex min-h-11 items-center justify-between border-b border-line py-2 text-[1.0625rem]"
                    >
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          <div className="flex items-center gap-6 text-text-2">
            <Link href="/login" className="text-text-1">
              {labels.login}
            </Link>
            <LanguageLink label={labels.language} title={labels.languageTitle} />
          </div>
          <div className="flex gap-6 text-small text-text-3">
            {labels.legal.map((item) => (
              <Link key={item.href} href={item.href}>
                {item.label}
              </Link>
            ))}
          </div>
        </nav>
      </dialog>
    </>
  );
}
