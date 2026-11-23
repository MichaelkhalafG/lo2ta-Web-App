"use client";

import { useTranslations } from "next-intl";

/**
 * While a page's data loads: a hairline that draws across under the header, like a line being ruled. Quiet, and still
 * for readers who ask for reduced motion (audit D6). Only here, on the one slow dynamic list: a loading boundary makes
 * a page stream at once, so a page below it could no longer answer 404 or redirect with a real status.
 */
export default function Loading() {
  const t = useTranslations("states");

  return (
    <div role="status" aria-live="polite" className="px-gutter-mobile py-10 md:px-gutter">
      <span className="sr-only">{t("loading")}</span>
      <div aria-hidden="true" className="relative h-px w-full overflow-hidden bg-line">
        {/* Placed from the physical left; the keyframes run it along the reading direction. */}
        <span className="lk-rule absolute inset-y-0 w-1/3 bg-text-2" style={{ left: 0 }} />
      </div>
    </div>
  );
}
