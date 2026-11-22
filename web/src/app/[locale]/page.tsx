import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { ArrowButton } from "@/components/ArrowButton";
import { Arrow } from "@/components/Arrow";
import { HeroFigure } from "@/components/home/HeroFigure";
import { UnitCard, type CardWords } from "@/components/listing/UnitCard";
import { VerifiedStamp } from "@/components/VerifiedStamp";
import { cardWords } from "@/lib/cardWords";
import type { UnitCard as UnitCardData } from "@/lib/listing";
import { unitList } from "@/lib/listingServer";
import { Link } from "@/i18n/navigation";
import { siteTexts, text } from "@/lib/core";
import { paths } from "@/lib/nav";
import { pageLocale } from "@/lib/page";
import { alternates } from "@/lib/seo";

export const revalidate = 300;

/** Illustrative hero figures (board 06.1) until real units exist; labelled as an example on the page. */
const EXAMPLE = { contract: 4_848_000, today: 6_900_000 };

export async function generateMetadata({ params }: PageProps<"/[locale]">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const t = await getTranslations({ locale, namespace: "meta" });
  const texts = await siteTexts("home", locale);

  // The home page shares the layout's segment, so the title template does not reach it: the full title is set here.
  return {
    title: {
      absolute: `${t("siteName")} · ${["title_1", "title_2", "title_3"].map((k) => text(texts, `home.hero.${k}`)).join(" ")}`,
    },
    description: text(texts, "home.hero.lead"),
    alternates: alternates("/", locale),
  };
}

export default async function HomePage({ params }: PageProps<"/[locale]">) {
  const locale = await pageLocale(params);
  const texts = await siteTexts("home", locale);
  const common = await getTranslations("common");
  const tx = (key: string) => text(texts, `home.${key}`);
  // "لقطات الأسبوع": the units the team tagged; the newest three when none is tagged (PLAN D28).
  const [tagged, words] = await Promise.all([unitList({ deal: true }, locale, 3), cardWords(locale)]);
  const picks = tagged.data.length > 0 ? { cards: tagged.data, recent: false } : { cards: (await unitList({ sort: "newest" }, locale, 3)).data, recent: true };

  return (
    <>
      <Hero tx={tx} currency={common("currency")} />
      <Review tx={tx} />
      <SellerBand tx={tx} />
      <Picks tx={tx} cards={picks.cards} recent={picks.recent} words={words} />
    </>
  );
}

type Tx = (key: string) => string;

function Hero({ tx, currency }: { tx: Tx; currency: string }) {
  return (
    <section className="grid lg:min-h-[46rem] lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
      <div className="flex flex-col justify-between gap-12 px-gutter-mobile pt-12 pb-14 lg:ps-12 lg:pe-14 lg:pt-20">
        <h1 className="text-display-xl-m lg:text-hero">
          {tx("hero.title_1")}
          <br />
          {tx("hero.title_2")}
          <br />
          <span className="font-light text-text-2">{tx("hero.title_3")}</span>
        </h1>
        <div className="flex max-w-[620px] flex-col gap-7">
          <p className="text-lead text-text-2">{tx("hero.lead")}</p>
          <HeroFigure
            contract={EXAMPLE.contract}
            today={EXAMPLE.today}
            labels={{
              note: tx("hero.example_note"),
              contract: tx("hero.contract_label"),
              find: tx("hero.find_label"),
              today: tx("hero.today_label"),
              currency,
            }}
          />
          <div className="flex flex-wrap items-center gap-x-8 gap-y-4">
            <ArrowButton href={paths.opportunities}>{tx("hero.cta_primary")}</ArrowButton>
            <Link
              href={paths.sell}
              className="border-b border-gold pt-1.5 pb-1 text-[1rem] font-medium text-text-1 hover:text-gold-bright"
            >
              {tx("hero.cta_secondary")}
            </Link>
          </div>
        </div>
      </div>

      <div className="relative min-h-80 bg-[repeating-linear-gradient(135deg,#141A23_0_11px,#19212C_11px_22px)]">
        <span className="absolute start-6 end-6 top-6 text-annotation text-text-3">{tx("hero.photo_caption")}</span>
        <div className="absolute start-0 end-0 bottom-0 grid grid-cols-[auto_1fr_auto] border-t-[1.5px] border-text-3 bg-bg-0">
          <div className="flex flex-col gap-0.5 px-4.5 py-3.5">
            <span className="text-micro text-text-3">LK</span>
            <span className="font-mono text-[0.875rem] font-medium text-text-1" dir="ltr">
              LK-NC-0124
            </span>
          </div>
          <div className="flex items-center border-s border-line px-4.5 py-3.5 text-annotation text-text-3">
            {tx("hero.example_note")}
          </div>
          <Link href={paths.opportunities} className="flex items-center border-s border-line px-4.5 py-3.5 text-[1.125rem]">
            <Arrow />
            <span className="sr-only">{tx("hero.cta_primary")}</span>
          </Link>
        </div>
      </div>
    </section>
  );
}

function Review({ tx }: { tx: Tx }) {
  const rows: { mark: string; key: string; verified?: boolean }[] = [
    { mark: "01", key: "step1" },
    { mark: "02", key: "step2" },
    { mark: "03", key: "step3" },
    { mark: "✓", key: "verified", verified: true },
  ];

  return (
    <section className="grid gap-14 border-t border-line px-gutter-mobile py-16 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:px-gutter lg:py-24">
      <div className="flex flex-col gap-5.5">
        <h2 className="text-h2-m md:text-[2.5rem] md:leading-[1.3] md:font-bold">{tx("trust.title")}</h2>
        <p className="text-[1rem] leading-[1.85] text-text-2">{tx("trust.body")}</p>
        <div className="mt-2 grid grid-cols-2 gap-3">
          {(["partner_architect", "partner_lawyer"] as const).map((key, i) => (
            <div key={key} className={`flex flex-col gap-2 ${i === 1 ? "mt-10" : ""}`}>
              <span
                aria-hidden="true"
                className="aspect-[4/5] bg-[repeating-linear-gradient(135deg,#141A23_0_9px,#19212C_9px_18px)]"
              />
              <span className="text-[0.8125rem] text-text-1">{tx(`trust.${key}`)}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="relative self-start lg:mt-3 lg:me-36">
        <div className="border-[1.5px] border-text-3">
          <div className="hidden grid-cols-[4rem_1fr_1.4fr] border-b border-line text-annotation text-text-3 md:grid">
            <span className="px-4 py-3">{tx("trust.col_item")}</span>
            <span className="border-s border-line px-4 py-3">{tx("trust.col_how")}</span>
            <span className="border-s border-line px-4 py-3">{tx("trust.col_what")}</span>
          </div>
          {rows.map((row, i) => (
            <div
              key={row.key}
              className={`grid grid-cols-[3rem_1fr] md:grid-cols-[4rem_1fr_1.4fr] ${i < rows.length - 1 ? "border-b border-line" : ""}`}
            >
              <span className={`px-4 py-6 font-mono text-[0.8125rem] font-medium ${row.verified ? "text-verified" : "text-text-2"}`}>
                {row.mark}
              </span>
              <div className="flex flex-col gap-1 border-s border-line px-4 py-6">
                <span className={`text-[1.1875rem] font-semibold ${row.verified ? "text-verified" : ""}`}>
                  {tx(`trust.${row.key}_title`)}
                </span>
                <span className="text-[0.8125rem] text-text-3">{tx(`trust.${row.key}_sub`)}</span>
              </div>
              <span className="col-span-2 border-line px-4 pb-6 text-body text-text-2 md:col-span-1 md:border-s md:py-6">
                {tx(`trust.${row.key}_text`)}
              </span>
            </div>
          ))}
        </div>
        <span className="absolute -end-34 top-0 hidden lg:block">
          <VerifiedStamp label={tx("trust.stamp")} />
        </span>
      </div>
    </section>
  );
}

function SellerBand({ tx }: { tx: Tx }) {
  const steps = [1, 2, 3, 4, 5, 6];

  return (
    <section className="grid gap-12 bg-paper px-gutter-mobile py-16 text-paper-ink lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-18 lg:px-gutter lg:py-24">
      <div className="flex flex-col gap-6">
        <span className="font-mono text-annotation text-gold-ink">{tx("seller.kicker")}</span>
        <h2 className="text-h2-m md:text-[2.875rem] md:leading-[1.3] md:font-bold">{tx("seller.title")}</h2>
        <p className="text-lead leading-[1.9] text-paper-ink-2">{tx("seller.body")}</p>
        <div className="mt-2 flex flex-wrap items-center gap-5">
          <ArrowButton href={paths.sell} tone="paper">
            {tx("seller.cta")}
          </ArrowButton>
          <span className="text-[0.875rem] text-paper-ink-2">{tx("seller.note")}</span>
        </div>
      </div>
      <ol className="flex flex-col border-t-[1.5px] border-paper-ink">
        {steps.map((n) => (
          <li
            key={n}
            className="grid grid-cols-[3rem_1fr] items-baseline gap-x-4 gap-y-1 border-b border-paper-line py-4.5 md:grid-cols-[3.5rem_12.5rem_1fr]"
          >
            <span className="font-mono text-[0.8125rem] font-medium text-gold-ink">{String(n).padStart(2, "0")}</span>
            <span className="text-[1.125rem] font-semibold">{tx(`seller.step${n}`)}</span>
            <span className="col-start-2 text-body leading-[1.7] text-paper-ink-2 md:col-start-auto">{tx(`seller.step${n}_text`)}</span>
          </li>
        ))}
      </ol>
    </section>
  );
}

function Picks({ tx, cards, recent, words }: { tx: Tx; cards: UnitCardData[]; recent: boolean; words: CardWords }) {
  return (
    <section className="flex flex-col gap-9 px-gutter-mobile pt-16 pb-20 lg:px-gutter lg:pt-24">
      <div className="flex flex-wrap items-end justify-between gap-6">
        <div className="flex flex-col gap-2.5">
          <h2 className="text-h2-m md:text-[2.5rem] md:leading-[1.3] md:font-bold">{tx("picks.title")}</h2>
          <span className="text-body text-text-2">{recent ? tx("picks.recent") : tx("picks.subtitle")}</span>
        </div>
        <Link
          href={paths.opportunities}
          className="inline-flex items-center gap-2 border-b border-gold pb-1 text-body font-medium whitespace-nowrap"
        >
          {tx("picks.all")} <Arrow />
        </Link>
      </div>
      {cards.length === 0 ? (
        <p className="border-t-[1.5px] border-text-3 pt-6 text-body text-text-2">{tx("picks.empty")}</p>
      ) : (
        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          {cards.map((unit) => (
            <UnitCard key={unit.code} unit={unit} words={words} />
          ))}
        </div>
      )}
    </section>
  );
}

