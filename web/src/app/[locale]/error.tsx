"use client";

import { useTranslations } from "next-intl";
import { primaryAction, secondaryAction, StatePage } from "@/components/StatePage";
import { Link } from "@/i18n/navigation";

/**
 * A page that failed to render (core down, a failed read): the site's header and footer stay, the reader can try
 * again or go home (audit D6). Nothing about the cause is shown; the server log has it.
 */
export default function PageError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const t = useTranslations("states.error");
  const unit = useTranslations("listing.unit");

  return (
    <StatePage sheet={unit("code")} label={t("label")} title={t("title")} body={t("body")}>
      <button type="button" onClick={reset} className={primaryAction}>
        {t("retry")}
      </button>
      <Link href="/" className={secondaryAction}>
        {t("home")}
      </Link>
    </StatePage>
  );
}
