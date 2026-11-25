import { getTranslations } from "next-intl/server";
import type { Locale } from "@/i18n/routing";
import { describeValue, monthYear, percent, type MarketValueEntry } from "@/lib/tools";

/*
 * "The values we use" (Phase 6, J10): each value's range or rule and the month it was last checked, marked while
 * it is provisional or under review. No sources, links or names: those stay in the dashboard.
 */
export async function ValuesList({ values, locale, deepLossBp, headingLevel = 2 }: { values: MarketValueEntry[]; locale: Locale; deepLossBp: number | null; headingLevel?: 2 | 3 }) {
  const t = await getTranslations("tools.values");
  const Heading = headingLevel === 2 ? "h2" : "h3";

  return (
    <section aria-labelledby="values-title" className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <Heading id="values-title" className="text-h3">
          {t("title")}
        </Heading>
        <p className="text-small text-text-3">{t("intro")}</p>
      </div>
      <dl className="flex flex-col border-t-[1.5px] border-text-3">
        {values.map((entry) => {
          const phrase = describeValue(entry);

          return (
            <div key={entry.key} className="grid gap-x-6 gap-y-1 border-b border-line py-4 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
              <dt className="text-small font-semibold text-text-1">{t(`labels.${entry.key}`)}</dt>
              <dd className="flex flex-col gap-1 text-small text-text-2">
                <span>{t(`text.${phrase.key}`, phrase.values)}</span>
                <span className="flex flex-wrap items-center gap-x-3 gap-y-1 text-micro text-text-3">
                  <span>{t("checked", { month: monthYear(entry.checked, locale) })}</span>
                  {entry.under_review ? <span className="font-semibold text-warning">{t("underReview")}</span> : entry.provisional ? <span>{t("provisional")}</span> : null}
                </span>
              </dd>
            </div>
          );
        })}
      </dl>
      {deepLossBp !== null ? <p className="text-micro text-text-3">{t("deepLine", { line: percent(deepLossBp) })}</p> : null}
    </section>
  );
}
