import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Paragraphs } from "@/components/ContentPage";
import { ValuesList } from "@/components/tools/ValuesList";
import { indicators, marketValues, paragraphs, publicSettings, siteTexts, text } from "@/lib/core";
import { money } from "@/lib/listing";
import { pageLocale, pageMetadata } from "@/lib/page";
import { monthYear, type Indicator } from "@/lib/tools";

export const revalidate = 300;

const PATH = "/reports";

export async function generateMetadata({ params }: PageProps<"/[locale]/reports">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const texts = await siteTexts("reports", locale);

  return pageMetadata(PATH, locale, text(texts, "reports.title"), text(texts, "reports.intro"));
}

function indicatorValue(item: Indicator): string {
  const value = Number(item.value);
  const plain = Number.isInteger(value) ? money(value) : new Intl.NumberFormat("en-US", { maximumFractionDigits: 2 }).format(value);

  return item.unit === "pct" ? `${plain}%` : plain;
}

/**
 * Reports and indicators (PLAN 1.2; Phase 6, J9, J10): the values the calculator uses, each with its range and the
 * month it was checked, without sources; then the client's indicators, hidden until the first one is published;
 * then the methodology (client text, PLAN 6 row 13).
 */
export default async function ReportsPage({ params }: PageProps<"/[locale]/reports">) {
  const locale = await pageLocale(params);
  const [texts, market, items, settings, t] = await Promise.all([siteTexts("reports", locale), marketValues(locale), indicators(locale), publicSettings(locale), getTranslations("tools")]);
  const deepLossBp = typeof settings.deep_loss_ratio_bp === "number" ? settings.deep_loss_ratio_bp : null;

  return (
    <article className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <div className="grid gap-10 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-14">
        <header className="flex flex-col gap-5">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            LK-T2
          </span>
          <h1 className="text-h1-m md:text-h1">{text(texts, "reports.title")}</h1>
          <p className="text-lead text-text-2">{text(texts, "reports.intro")}</p>
        </header>

        <div className="flex max-w-[44rem] flex-col gap-14">
          {items.length > 0 ? (
            <section aria-labelledby="indicators-title" className="flex flex-col gap-4">
              <h2 id="indicators-title" className="text-h3">
                {t("indicators.title")}
              </h2>
              <ul className="grid gap-px overflow-hidden rounded-sm border border-line bg-line sm:grid-cols-2">
                {items.map((item) => (
                  <li key={`${item.title}-${item.as_of}`} className="flex flex-col gap-2 bg-bg-0 p-5">
                    <span className="text-small text-text-2">{item.title}</span>
                    <span className="flex flex-wrap items-baseline gap-x-2">
                      <span className="text-h2-m font-semibold text-text-1 tabular md:text-h2" dir="ltr">
                        {indicatorValue(item)}
                      </span>
                      {item.unit === "egp" || item.unit === "months" || item.unit === "years" ? <span className="text-small text-text-2">{t(`indicators.units.${item.unit}`)}</span> : null}
                    </span>
                    {item.note ? <span className="text-small text-text-3">{item.note}</span> : null}
                    <span className="text-micro text-text-3">{t("indicators.asOf", { month: monthYear(item.as_of, locale) })}</span>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          <ValuesList values={market.values} locale={locale} deepLossBp={deepLossBp} />

          <section aria-labelledby="method-title" className="lk-content flex flex-col gap-3 text-text-2">
            <h2 id="method-title" className="text-h3 text-text-1">
              {t("methodTitle")}
            </h2>
            <Paragraphs items={paragraphs(texts, "reports.methodology")} />
          </section>
        </div>
      </div>
    </article>
  );
}
