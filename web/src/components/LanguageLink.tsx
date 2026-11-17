"use client";

import { useLocale } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";

/** A real link to the same page in the other language (PLAN 5.1 item 6), not a menu or a cookie. */
export function LanguageLink({ label, title, className = "" }: { label: string; title: string; className?: string }) {
  const locale = useLocale();
  const pathname = usePathname();
  const other = locale === "ar" ? "en" : "ar";

  return (
    <Link href={pathname} locale={other} lang={other} hrefLang={other} title={title} className={className}>
      {label}
    </Link>
  );
}
