import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { siteTexts, text } from "@/lib/core";
import { compoundIndex } from "@/lib/listingServer";
import { pageLocale, pageMetadata } from "@/lib/page";

const PATH = "/compounds";

export async function generateMetadata({ params }: PageProps<"/[locale]/compounds">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const texts = await siteTexts("compounds", locale);

  return pageMetadata(PATH, locale, text(texts, "compounds.title"), text(texts, "compounds.intro"));
}

/** The compounds index (PLAN 1.2, 5.1 point 4): every compound, linked, with its count of available units. */
export default async function CompoundsPage({ params }: PageProps<"/[locale]/compounds">) {
  const locale = await pageLocale(params);
  const [texts, t, compounds] = await Promise.all([siteTexts("compounds", locale), getTranslations("listing.compounds"), compoundIndex(locale)]);
  const withUnits = compounds.filter((c) => c.units > 0);
  const without = compounds.filter((c) => c.units === 0);

  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-16 md:pb-24">
      <div className="mx-auto flex max-w-6xl flex-col gap-10">
        <header className="flex flex-col gap-3">
          <span className="font-mono text-annotation text-text-3" dir="ltr">
            LK-P2
          </span>
          <h1 className="text-h1-m md:text-h1">{text(texts, "compounds.title")}</h1>
          <p className="max-w-3xl text-body text-text-2">{text(texts, "compounds.intro")}</p>
        </header>
        {[withUnits, without].map((group, i) =>
          group.length > 0 ? (
            <section key={i} className="flex flex-col gap-4">
              <h2 className="text-h3">{i === 0 ? t("withUnits") : t("noUnits")}</h2>
              <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {group.map((c) => (
                  <li key={c.slug}>
                    <Link href={`/compounds/${c.slug}`} className="flex h-full flex-col gap-1 rounded-sm border border-line bg-surface-1 p-4 hover:border-line-strong">
                      <span className="text-body font-semibold text-text-1">{c.name}</span>
                      <span className="text-small text-text-2">{[c.developer?.name, c.place.slice(1).join("، ")].filter(Boolean).join(" · ")}</span>
                      <span className="text-small text-text-3">{t("count", { count: c.units })}</span>
                    </Link>
                  </li>
                ))}
              </ul>
            </section>
          ) : null,
        )}
      </div>
    </section>
  );
}
