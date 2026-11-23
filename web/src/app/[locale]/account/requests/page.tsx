import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { AccountPage } from "@/components/account/AccountPage";
import { Link } from "@/i18n/navigation";
import { core } from "@/lib/account";
import { accountMetadata, requireAccount } from "@/lib/guard";
import { money } from "@/lib/listing";
import { pageLocale } from "@/lib/page";

type MyRequest = {
  id: string;
  reference: string;
  state: { code: string; label: string };
  readiness: { code: string; label: string };
  fee_amount: number;
  created_at: string | null;
  unit: { code: string | null; slug: string | null; title: string | null };
};

export async function generateMetadata({ params }: PageProps<"/[locale]/account/requests">): Promise<Metadata> {
  return accountMetadata(await pageLocale(params), "/account/requests", "requests.title");
}

/** My requests (PLAN 1.2 "my requests", 2.3): the buyer's unit requests and their state. */
export default async function AccountRequestsPage({ params }: PageProps<"/[locale]/account/requests">) {
  const locale = await pageLocale(params);
  await requireAccount(locale, "/account/requests");
  const t = await getTranslations("account.requests");
  const result = await core<{ data: MyRequest[] }>("GET", "me/requests", locale, { auth: true });
  const rows = result.ok ? result.data.data : [];

  return (
    <AccountPage label="LK-U4" title={t("title")} intro={t("intro")}>
      {rows.length === 0 ? (
        <div className="flex flex-col items-start gap-4 rounded-sm border border-line bg-surface-1 p-6">
          <p className="text-body text-text-2">{t("empty")}</p>
          <Link href="/opportunities" className="inline-flex min-h-control-md items-center text-small text-text-1 underline underline-offset-4">
            {t("browse")}
          </Link>
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {rows.map((r) => (
            <li key={r.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-sm border border-line bg-surface-1 px-5 py-4">
              <span className="font-mono text-annotation text-text-3" dir="ltr">
                {r.reference}
              </span>
              {r.unit.slug && r.unit.title ? (
                <Link href={`/units/${r.unit.slug}`} className="text-body text-text-1 hover:underline">
                  {r.unit.title}
                </Link>
              ) : (
                <span className="text-body text-text-3">{t("unitGone")}</span>
              )}
              <span className="rounded-sm border border-line px-2 py-0.5 text-small text-text-2">{r.state.label}</span>
              <span className="ms-auto text-small text-text-3">
                {t("fee")} <span dir="ltr">{money(r.fee_amount)}</span>
              </span>
            </li>
          ))}
        </ul>
      )}
    </AccountPage>
  );
}
