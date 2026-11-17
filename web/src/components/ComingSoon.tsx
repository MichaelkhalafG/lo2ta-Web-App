import { getTranslations } from "next-intl/server";
import { ContentPage } from "./ContentPage";

/**
 * A page whose content comes in a later phase (PLAN 7). It keeps the header links working; the site stays
 * noindex until launch.
 */
export async function ComingSoon({ title, label }: { title: string; label: string }) {
  const t = await getTranslations("common");

  return (
    <ContentPage label={label} title={title}>
      <p>{t("comingSoon")}</p>
    </ContentPage>
  );
}
