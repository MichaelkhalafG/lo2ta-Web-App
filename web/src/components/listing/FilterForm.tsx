"use client";

import { useEffect, useRef, type ReactNode } from "react";
import { omitted } from "@/lib/listing";

/**
 * The opportunities filter form (approved proposal 2026-11-24). A plain GET form, so the page works without
 * JavaScript (a submit button is shown then). With JavaScript, a change applies at once on wide screens; on phones
 * the controls behind "تصفية" wait for "اعرض النتائج", while the type tabs and the sort still apply at once.
 */
export function FilterForm({ action, className, children }: { action: string; className: string; children: ReactNode }) {
  const form = useRef<HTMLFormElement>(null);

  useEffect(() => {
    const el = form.current;
    el?.setAttribute("data-js", "");
    // Coming back to the page from the history: the fields left out on submit are usable again.
    const enable = () => el?.querySelectorAll<HTMLInputElement | HTMLSelectElement>("input, select").forEach((f) => (f.disabled = false));
    window.addEventListener("pageshow", enable);
    return () => window.removeEventListener("pageshow", enable);
  }, []);

  return (
    <form
      ref={form}
      method="get"
      action={action}
      className={className}
      onSubmit={(e) => {
        // Only what is set goes in the address: no "type=&area=" noise, no default sort.
        for (const el of Array.from(e.currentTarget.elements)) {
          if ((el instanceof HTMLSelectElement || el instanceof HTMLInputElement) && el.name && omitted(el)) {
            el.disabled = true;
          }
        }
      }}
      onChange={(e) => {
        const target = e.target as HTMLElement;
        if (target.id === "filters-toggle") return;
        const wide = window.matchMedia("(min-width: 48rem)").matches;
        if (wide || !target.closest("[data-panel]")) {
          e.currentTarget.requestSubmit();
        }
      }}
    >
      {children}
    </form>
  );
}
