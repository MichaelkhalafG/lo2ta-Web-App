import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { FavoriteButton } from "@/components/account/FavoriteButton";
import { Link } from "@/i18n/navigation";
import { core } from "@/lib/account";
import { accountMetadata, requireAccount } from "@/lib/guard";
import { pageLocale } from "@/lib/page";

type FavoriteRow =
  | { available: true; unit: { code: string; sub_type: { label: string } | null; location: { area: { name: string } | null; district: { name: string } | null } | null; compound: { name: string } | null } }
  | { available: false; unit: { code: string } };

export async function generateMetadata({ params }: PageProps<"/[locale]/account/favorites">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/account/favorites", "favorites.title");
}

/** My favorites (PLAN 2.3): units still on the market link to their page; units that left are marked. */
export default async function FavoritesPage({ params }: PageProps<"/[locale]/account/favorites">) {
  const locale = await pageLocale(params);
  await requireAccount(locale, "/account/favorites");
  const t = await getTranslations("account.favorites");
  const result = await core<{ data: FavoriteRow[] }>("GET", "me/favorites", locale, { auth: true });
  const rows = result.ok ? result.data.data : [];

  return (
    <AccountPage label="LK-F1" title={t("title")}>
      {rows.length === 0 ? (
        <p className="border-t-[1.5px] border-text-3 pt-6 text-body text-text-2">
          {t("empty")}{" "}
          <Link href="/opportunities" className="text-text-1 underline underline-offset-4">
            {t("browse")}
          </Link>
        </p>
      ) : (
        <ul className="flex flex-col border-t-[1.5px] border-text-3">
          {rows.map((row) => (
            <li key={row.unit.code} className="flex flex-wrap items-center justify-between gap-4 border-b border-line py-4">
              <div className="flex flex-col gap-1">
                <span className="font-mono text-annotation text-text-3" dir="ltr">
                  {row.unit.code}
                </span>
                {row.available ? (
                  <Link href={`/units/${row.unit.code.toLowerCase()}`} className="text-body text-text-1 hover:underline">
                    {[row.unit.sub_type?.label, row.unit.compound?.name, row.unit.location?.district?.name ?? row.unit.location?.area?.name].filter(Boolean).join(" · ")}
                  </Link>
                ) : (
                  <span className="text-body text-text-3">{t("unavailable")}</span>
                )}
              </div>
              <FavoriteButton code={row.unit.code} saved />
            </li>
          ))}
        </ul>
      )}
    </AccountPage>
  );
}
