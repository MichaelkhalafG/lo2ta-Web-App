import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { Link } from "@/i18n/navigation";
import { accountMetadata, requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";
import type { SellerUnitSummary } from "@/lib/sell";
import { sellerUnits } from "@/lib/sellServer";

export async function generateMetadata({ params }: PageProps<"/[locale]/account/units">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/account/units", "units.title");
}

/** Where a unit's seller goes next, by its state (PLAN 2.2.7). */
function nextHref(unit: SellerUnitSummary): string {
  return unit.state.code === "draft" || unit.state.code === "changes_requested" ? `/sell/${unit.id}/${Math.min(Math.max(unit.step_reached, 1), 6)}` : `/account/units/${unit.id}`;
}

/** My units (PLAN 2.2.7): every unit of the seller with its state and the next step. */
export default async function AccountUnitsPage({ params }: PageProps<"/[locale]/account/units">) {
  const locale = await pageLocale(params);
  await requireAccount(locale, "/account/units");
  const t = await getTranslations("account.units");
  const units = await sellerUnits(locale);

  return (
    <AccountPage label="LK-U2" title={t("title")} intro={t("intro")}>
      {units.length === 0 ? (
        <div className="flex flex-col items-start gap-4 rounded-sm border border-line bg-surface-1 p-6">
          <p className="text-body text-text-2">{t("empty")}</p>
          <Link href="/sell" className="inline-flex h-control-lg items-center rounded-sm bg-gold px-6 font-semibold text-on-gold hover:bg-gold-bright">
            {t("sell")}
          </Link>
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {units.map((unit) => (
            <li key={unit.id}>
              <Link href={nextHref(unit)} className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-sm border border-line bg-surface-1 px-5 py-4 hover:bg-surface-2">
                <span className="font-mono text-annotation text-text-3" dir="ltr">
                  {unit.code ?? unit.reference ?? "—"}
                </span>
                <span className="text-body">{[unit.sub_type, unit.compound].filter(Boolean).join(" · ") || t("untitled")}</span>
                <span className="rounded-sm border border-line px-2 py-0.5 text-small text-text-2">{unit.state.label}</span>
                <span className="ms-auto text-small text-text-1">{t(`next.${unit.state.code}`)}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
      <Link href="/sell" className="self-start text-small text-text-1 underline underline-offset-4">
        {t("another")}
      </Link>
    </AccountPage>
  );
}
