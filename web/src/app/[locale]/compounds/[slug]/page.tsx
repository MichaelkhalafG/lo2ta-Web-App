import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getTranslations } from "next-intl/server";
import { UnitCard } from "@/components/listing/UnitCard";
import { Link } from "@/i18n/navigation";
import { cardWords } from "@/lib/cardWords";
import { compoundPage } from "@/lib/listingServer";
import { pageLocale } from "@/lib/page";
import { alternates } from "@/lib/seo";

/**
 * A compound page (PLAN 1.2, 2.5 compound row): name, developer, place, intro, and its unit cards. Indexed from one
 * published unit, noindex at 0 (Q14). The intro is a client text: a placeholder is not shown to visitors.
 */
export async function generateMetadata({ params }: PageProps<"/[locale]/compounds/[slug]">): Promise<Metadata> {
  const locale = await pageLocale(params);
  const { slug } = await params;
  const compound = await compoundPage(slug, locale);
  if (!compound) {
    return { robots: { index: false, follow: false } };
  }
  const t = await getTranslations({ locale, namespace: "listing.compounds" });

  return {
    title: t("pageTitle", { name: compound.name }),
    description: [compound.developer?.name, compound.place.join("، ")].filter(Boolean).join(" · "),
    alternates: alternates(`/compounds/${compound.slug}`, locale),
    ...(compound.units.length === 0 ? { robots: { index: false, follow: true } } : {}),
  };
}

export default async function CompoundPage({ params }: PageProps<"/[locale]/compounds/[slug]">) {
  const locale = await pageLocale(params);
  const { slug } = await params;
  const compound = await compoundPage(slug, locale);
  if (!compound) {
    notFound();
  }
  const [t, words] = await Promise.all([getTranslations("listing.compounds"), cardWords(locale)]);

  return (
    <section className="px-gutter-mobile pt-12 pb-20 md:px-gutter md:pt-16 md:pb-24">
      <div className="mx-auto flex max-w-7xl flex-col gap-8">
        <nav aria-label={t("breadcrumb")} className="flex gap-2 text-small text-text-3">
          <Link href="/compounds" className="hover:text-text-1">
            {t("index")}
          </Link>
          <span aria-hidden="true">/</span>
          <span>{compound.name}</span>
        </nav>
        <header className="flex flex-col gap-3">
          <h1 className="text-h1-m md:text-h1">{compound.name}</h1>
          <p className="text-body text-text-2">{[compound.developer?.name, compound.place.join("، ")].filter(Boolean).join(" · ")}</p>
          {compound.intro && !compound.intro_is_placeholder ? <p className="max-w-3xl text-body whitespace-pre-line text-text-2">{compound.intro}</p> : null}
        </header>
        {compound.units.length === 0 ? (
          <p className="rounded-sm border border-line bg-surface-1 p-8 text-center text-body text-text-2">{t("empty")}</p>
        ) : (
          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            {compound.units.map((unit) => (
              <UnitCard key={unit.code} unit={unit} words={words} />
            ))}
          </div>
        )}
        <Link href={`/opportunities`} className="inline-flex min-h-control-md items-center self-start text-small text-text-1 underline underline-offset-4">
          {t("all")}
        </Link>
      </div>
    </section>
  );
}
