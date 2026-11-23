"use client";

import { useTranslations } from "next-intl";
import { primaryAction, secondaryAction, StatePage } from "@/components/StatePage";
import { Link } from "@/i18n/navigation";

/**
 * Any notFound() under a language (an unknown unit, compound or path), inside the site's header and footer. A client
 * component, so it never reads the request and the pages around it stay static.
 */
export default function NotFound() {
  const t = useTranslations("states.notFound");
  const unit = useTranslations("listing.unit");

  return (
    <StatePage sheet={unit("code")} label={t("label")} title={t("title")} body={t("body")}>
      <Link href="/opportunities" className={primaryAction}>
        {t("opportunities")}
      </Link>
      <Link href="/" className={secondaryAction}>
        {t("home")}
      </Link>
    </StatePage>
  );
}
