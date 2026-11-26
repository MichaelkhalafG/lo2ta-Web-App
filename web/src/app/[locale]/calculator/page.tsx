import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { BuyerCalculator, SellerCalculator } from "@/components/tools/Calculator";
import { ValuesList } from "@/components/tools/ValuesList";
import { Link } from "@/i18n/navigation";
import { marketValues, publicSettings, siteTexts, text } from "@/lib/core";
import { pageLocale, pageMetadata } from "@/lib/page";
import { calculatorTab } from "@/lib/tools";

const PATH = "/calculator";

export async function generateMetadata({ params }: PageProps<"/[locale]/calculator">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const texts = await siteTexts("calculator", locale);

  return pageMetadata(PATH, locale, text(texts, "calculator.title"), text(texts, "calculator.intro"));
}

/**
 * The exit calculator (PLAN 1.2, 4.1; Phase 6): the seller tab compares cancelling with the developer against
 * selling through Lokta; the buyer tab shows the day-one difference. Figures come from core with the market values
 * (provisional until the client confirms them); the values list shows each range and the month it was checked,
 * never its sources (J10) and never a developer (K6). The tab is a link, so both tabs work without JavaScript.
 */
export default async function CalculatorPage({ params, searchParams }: PageProps<"/[locale]/calculator">) {
  const locale = await pageLocale(params);
  const tab = calculatorTab((await searchParams).tab);
  const [texts, sellerTexts, market, settings, t] = await Promise.all([siteTexts("calculator", locale), siteTexts("seller", locale), marketValues(locale), publicSettings(locale), getTranslations("tools")]);
  const deepLossBp = typeof settings.deep_loss_ratio_bp === "number" ? settings.deep_loss_ratio_bp : 2500;
  const tabs = [
    { key: "seller", href: PATH, label: t("tabs.seller") },
    { key: "buyer", href: `${PATH}?tab=buyer`, label: t("tabs.buyer") },
  ] as const;

  return (
    <article className="flex flex-col gap-12 px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <header className="flex max-w-3xl flex-col gap-5">
        <span className="font-mono text-annotation text-text-3" dir="ltr">
          LK-T1
        </span>
        <h1 className="text-h1-m md:text-h1">{text(texts, "calculator.title")}</h1>
        <p className="text-lead text-text-2">{text(texts, tab === "buyer" ? "calculator.buyer_intro" : "calculator.intro")}</p>
      </header>

      <div className="flex flex-col gap-8">
        <nav aria-label={t("tabs.label")} className="flex border-b border-line">
          {tabs.map((item) => {
            const current = item.key === tab;

            return (
              <Link
                key={item.key}
                href={item.href}
                aria-current={current ? "page" : undefined}
                className={`-mb-px inline-flex min-h-11 items-center border-b-2 px-4 text-body transition-colors duration-150 ${current ? "border-gold font-semibold text-text-1" : "border-transparent text-text-2 hover:text-text-1"}`}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>

        {tab === "buyer" ? <BuyerCalculator /> : <SellerCalculator deepLossBp={deepLossBp} ctaHref="/sell" askYears={settings.refund_rule === "mirror"} transferConditions={text(sellerTexts, "seller.transfer_conditions")} />}
      </div>

      <div className="grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-14">
        <p className="text-small text-text-3 lg:order-2">{text(texts, "calculator.disclaimer")}</p>
        {tab === "seller" ? (
          <div className="lg:order-1">
            <ValuesList values={market.values} locale={locale} deepLossBp={deepLossBp} />
          </div>
        ) : null}
      </div>
    </article>
  );
}
