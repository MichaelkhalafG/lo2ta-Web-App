import { getLocale, getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import type { Locale } from "@/i18n/routing";
import { siteTexts, text } from "@/lib/core";
import { footerColumns, paths } from "@/lib/nav";
import { LanguageLink } from "./LanguageLink";
import { Wordmark } from "./Wordmark";

/** The site footer (board 06.1): a framed title block, four link columns and the legal strip. */
export async function SiteFooter() {
  const locale = (await getLocale()) as Locale;
  const t = await getTranslations("nav");
  const tf = await getTranslations("footer");
  const texts = await siteTexts("footer", locale);
  const email = text(texts, "footer.email");

  return (
    <footer className="border-t-[1.5px] border-text-3">
      <div className="grid grid-cols-2 gap-10 px-gutter-mobile py-12 md:grid-cols-[minmax(0,4fr)_repeat(4,minmax(0,2fr))] md:px-gutter md:pt-14">
        <div className="col-span-2 flex flex-col items-start gap-4.5 md:col-span-1">
          <Wordmark />
          <p className="max-w-[340px] text-[0.875rem] leading-[1.85] text-text-2">{text(texts, "footer.about")}</p>
          <span className="flex flex-col gap-1 font-mono text-[0.8125rem] text-text-2" dir="ltr">
            <span className="text-text-1">loktaeg.com</span>
            <a href={`mailto:${email}`} className="hover:text-text-1">
              {email}
            </a>
          </span>
        </div>
        {footerColumns.map((column) => (
          <div key={column.title} className="flex flex-col gap-3.5">
            <span className="border-b border-line pb-2.5 text-annotation text-text-3">{tf(column.title)}</span>
            {column.items.map((key) => (
              <Link key={key} href={paths[key]} className="text-body text-text-1 hover:text-text-2">
                {t(key)}
              </Link>
            ))}
          </div>
        ))}
      </div>
      <div className="grid border-t border-line text-[0.8125rem] text-text-2 md:grid-cols-[1fr_auto_auto_auto]">
        <span className="px-gutter-mobile py-4 md:px-gutter">
          {tf("rights", { year: new Date().getFullYear() })} · {text(texts, "footer.city")}
        </span>
        <Link href={paths.privacy} className="border-line px-gutter-mobile py-4 hover:text-text-1 md:border-s md:px-6">
          {t("privacy")}
        </Link>
        <Link href={paths.terms} className="border-line px-gutter-mobile py-4 hover:text-text-1 md:border-s md:px-6">
          {t("terms")}
        </Link>
        <LanguageLink
          label={t("switchLanguage")}
          title={t("switchLanguageLabel")}
          className="border-line px-gutter-mobile py-4 hover:text-text-1 md:border-s md:ps-6 md:pe-gutter"
        />
      </div>
    </footer>
  );
}
