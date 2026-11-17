"use client";

import { useLocale, useTranslations } from "next-intl";
import { usePathname } from "@/i18n/navigation";
import { favoriteAction } from "@/lib/actions/account";

/**
 * The heart on a unit card or page (PLAN 2.3). Signed out, it leads to sign-in and the unit is saved on the way
 * back (return-to-action). Works without JavaScript: a plain form post to a server action.
 */
export function FavoriteButton({ code, saved }: { code: string; saved: boolean }) {
  const t = useTranslations("account.favorite");
  const locale = useLocale();
  const pathname = usePathname();

  return (
    <form action={favoriteAction}>
      <input type="hidden" name="locale" value={locale} />
      <input type="hidden" name="code" value={code} />
      <input type="hidden" name="next" value={pathname} />
      <input type="hidden" name="saved" value={saved ? "1" : "0"} />
      <button
        type="submit"
        aria-pressed={saved}
        aria-label={saved ? t("remove", { code }) : t("save", { code })}
        className="inline-flex h-control-md items-center gap-2 rounded-sm border border-line-strong px-3 text-small text-text-1 hover:bg-surface-2"
      >
        <span aria-hidden="true" className={saved ? "text-text-1" : "text-text-2"}>
          {saved ? "♥" : "♡"}
        </span>
        {saved ? t("saved") : t("label")}
      </button>
    </form>
  );
}
