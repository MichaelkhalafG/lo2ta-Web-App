import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { currentAccount } from "@/lib/account";
import { siteTexts, text } from "@/lib/core";
import { requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";
import { sellerUnit } from "@/lib/sellServer";

export async function generateMetadata({ params }: PageProps<"/[locale]/sell/[unit]/done">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const t = await getTranslations({ locale, namespace: "sell.done" });

  return { title: t("title"), robots: { index: false, follow: false } };
}

/** After submit (PLAN 2.2.2, board 07.3): the reference, the 48-hour promise, the next steps by e-mail. */
export default async function SellDonePage({ params }: PageProps<"/[locale]/sell/[unit]/done">) {
  const locale = await pageLocale(params);
  const { unit: id } = await params;
  await requireAccount(locale, `/sell/${id}/done`);
  const [unit, account, texts] = await Promise.all([sellerUnit(locale, id), currentAccount(locale), siteTexts("seller", locale)]);
  if (!unit || unit.reference === null) {
    notFound();
  }
  const t = await getTranslations("sell.done");

  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-20 md:pb-24">
      <div className="mx-auto flex max-w-xl flex-col gap-8">
        <span className="font-mono text-annotation text-text-3" dir="ltr">
          {unit.reference}
        </span>
        <h1 className="text-h1-m md:text-h1">{t("title")}</h1>
        <p className="text-body text-text-2">
          {text(texts, "seller.submitted.review_promise")}{" "}
          <span className="font-mono break-all" dir="ltr">
            {account?.email}
          </span>
        </p>
        <ol className="flex flex-col gap-4 border-s border-line ps-5">
          <li className="flex flex-col">
            <span className="text-body text-success">✓ {t("sent")}</span>
          </li>
          <li className="flex flex-col">
            <span className="text-body">{t("review")}</span>
            <span className="text-small text-text-3">{t("reviewWhen")}</span>
          </li>
          <li className="flex flex-col">
            <span className="text-body">{t("outcome")}</span>
            <span className="text-small text-text-3">{t("outcomeHow")}</span>
          </li>
        </ol>
        <div className="flex flex-col gap-3 sm:flex-row">
          <Link href={`/account/units/${unit.id}`} className="inline-flex h-control-lg items-center justify-center rounded-sm bg-gold px-8 text-[1rem] font-semibold text-on-gold hover:bg-gold-bright">
            {t("track")}
          </Link>
          <Link href="/" className="inline-flex h-control-lg items-center justify-center rounded-sm border border-line-strong px-8 text-[1rem] hover:bg-surface-2">
            {t("home")}
          </Link>
        </div>
      </div>
    </section>
  );
}
