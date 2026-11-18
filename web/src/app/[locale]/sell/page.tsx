import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Notice } from "@/components/account/Form";
import { StartSelling } from "@/components/sell/StartSelling";
import { Link } from "@/i18n/navigation";
import { currentAccount } from "@/lib/account";
import { siteTexts, text } from "@/lib/core";
import { returnQuery } from "@/lib/intent";
import { pageLocale, pageMetadata } from "@/lib/page";
import { sellerUnits } from "@/lib/sellServer";

const PATH = "/sell";

export async function generateMetadata({ params }: PageProps<"/[locale]/sell">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const t = await getTranslations({ locale, namespace: "sell.landing" });

  return pageMetadata(PATH, locale, t("metaTitle"), t("metaDescription"));
}

/**
 * "Sell your unit" (PLAN 2.2.1): the process, the 0% seller fee and the no-over rule, then sign in or start.
 * Signed in: the seller's open drafts, and "start" with the no-over modal. Rendered per visitor, never cached.
 */
export default async function SellPage({ params, searchParams }: PageProps<"/[locale]/sell">) {
  const locale = await pageLocale(params);
  const t = await getTranslations("sell.landing");
  const [texts, account] = await Promise.all([siteTexts("seller", locale), currentAccount(locale)]);
  const drafts = account ? (await sellerUnits(locale)).filter((u) => u.state.code === "draft") : [];
  const error = (await searchParams).error;

  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <div className="mx-auto flex max-w-4xl flex-col gap-10">
        <header className="flex flex-col gap-4">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            LK-S0
          </span>
          <h1 className="text-h1-m md:text-h1">{t("title")}</h1>
          <p className="max-w-2xl text-lead text-text-2">{t("intro")}</p>
        </header>

        <div className="grid gap-4 md:grid-cols-3">
          {(["fee", "noOver", "review"] as const).map((key) => (
            <div key={key} className="flex flex-col gap-2 rounded-sm border border-line bg-surface-1 p-5">
              <span className="text-h3">{t(`points.${key}.title`)}</span>
              <span className="text-small text-text-2">{t(`points.${key}.body`)}</span>
            </div>
          ))}
        </div>

        <ol className="flex flex-col gap-3">
          {[1, 2, 3, 4, 5, 6].map((n) => (
            <li key={n} className="flex items-baseline gap-4 border-b border-line pb-3">
              <span className="font-mono text-annotation text-text-3" dir="ltr">
                {String(n).padStart(2, "0")}
              </span>
              <span className="text-body">{t(`steps.${n}`)}</span>
            </li>
          ))}
        </ol>

        <p className="rounded-sm border border-line bg-surface-1 px-4 py-3 text-small text-text-2">{text(texts, "seller.notice.owner_only")}</p>

        {error === "throttled" ? <Notice tone="error">{t("throttled")}</Notice> : null}
        {error === "draft" ? <Notice tone="error">{t("draftFailed")}</Notice> : null}

        {account ? (
          <div className="flex flex-col gap-6">
            {drafts.length > 0 ? (
              <div className="flex flex-col gap-3">
                <h2 className="text-h3">{t("drafts")}</h2>
                <ul className="flex flex-col gap-2">
                  {drafts.map((d) => (
                    <li key={d.id}>
                      <Link href={`/sell/${d.id}/${Math.min(d.step_reached, 6)}`} className="flex flex-wrap items-center gap-3 rounded-sm border border-line bg-surface-1 px-4 py-3 hover:bg-surface-2">
                        <span className="text-body">{[d.sub_type, d.compound].filter(Boolean).join(" · ") || t("untitled")}</span>
                        <span className="font-mono text-annotation text-text-3" dir="ltr">
                          {String(Math.min(d.step_reached, 6)).padStart(2, "0")} / 06
                        </span>
                        <span className="ms-auto text-small text-text-1">{t("continue")}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            <StartSelling title={text(texts, "seller.no_over.title")} body={text(texts, "seller.no_over.body")} confirm={text(texts, "seller.no_over.confirm")} />
          </div>
        ) : (
          <div className="flex flex-col gap-3 sm:flex-row">
            <Link href={`/login?${returnQuery(PATH, "sell")}`} className="inline-flex h-control-lg items-center justify-center rounded-sm bg-gold px-8 text-[1rem] font-semibold text-on-gold hover:bg-gold-bright">
              {t("login")}
            </Link>
            <Link href={`/register?${returnQuery(PATH, "sell")}`} className="inline-flex h-control-lg items-center justify-center rounded-sm border border-line-strong px-8 text-[1rem] text-text-1 hover:bg-surface-2">
              {t("register")}
            </Link>
          </div>
        )}
      </div>
    </section>
  );
}
