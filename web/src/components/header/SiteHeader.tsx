import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { directLinks, menus, paths } from "@/lib/nav";
import { LanguageLink } from "../LanguageLink";
import { Wordmark } from "../Wordmark";
import { MobileMenu } from "./MobileMenu";
import { NavLink } from "./NavLink";
import { NavMenu } from "./NavMenu";

/**
 * The site header (board 04 and 06.1): 80px, the wordmark and the grouped menu on the inline start, the audience
 * links, the language link and sign-in on the inline end. Below the xl breakpoint (1280 px) it becomes the phone menu:
 * the English labels need that width (the closing run of 2026-11-24 found the language link cut off at 1024 px).
 */
export async function SiteHeader() {
  const t = await getTranslations("nav");

  const menuItems = menus.map((menu) => ({
    key: menu.key,
    label: t(menu.key),
    items: menu.items.map((item) => ({
      href: paths[item.key],
      code: item.code,
      label: t(item.key),
      hint: item.hint ? t(`${item.hint}Hint`) : null,
    })),
  }));

  const mobileGroups = [
    { title: null, items: directLinks.map((key) => ({ href: paths[key], label: t(key) })) },
    ...menuItems.map((menu) => ({ title: menu.label, items: menu.items.map(({ href, label }) => ({ href, label })) })),
    { title: null, items: (["buyers", "sell"] as const).map((key) => ({ href: paths[key], label: t(key) })) },
  ];

  return (
    <header data-site-chrome className="relative z-30 border-b border-line bg-bg-0">
      <a
        href="#main"
        className="absolute start-4 top-2 z-50 -translate-y-20 bg-gold px-4 py-2 text-on-gold focus:translate-y-0"
      >
        {t("skip")}
      </a>
      <div className="flex h-header items-stretch justify-between ps-gutter-mobile pe-gutter-mobile xl:ps-12 xl:pe-10">
        <div className="flex items-center gap-7">
          <Link href="/" aria-label={t("main")} className="flex items-center">
            <Wordmark />
          </Link>
          <nav aria-label={t("main")} className="hidden self-stretch text-body whitespace-nowrap text-text-2 xl:flex">
            {directLinks.map((key) => (
              <NavLink key={key} href={paths[key]}>
                {t(key)}
              </NavLink>
            ))}
            {menuItems.map((menu) => (
              <NavMenu key={menu.key} label={menu.label} items={menu.items} />
            ))}
          </nav>
        </div>

        <div className="hidden items-center gap-4.5 text-[0.875rem] whitespace-nowrap xl:flex">
          <Link href={paths.buyers} className="text-text-2 transition-colors duration-150 hover:text-text-1">
            {t("buyers")}
          </Link>
          <Link
            href={paths.sell}
            className="inline-flex h-control-sm items-center rounded-sm border border-line-strong px-4 text-text-1 transition-colors duration-150 hover:bg-surface-2"
          >
            {t("sell")}
          </Link>
          <span aria-hidden="true" className="h-6 w-px bg-line" />
          <LanguageLink
            label={t("switchLanguage")}
            title={t("switchLanguageLabel")}
            className="text-text-2 transition-colors duration-150 hover:text-text-1"
          />
          <Link href={paths.login} className="text-text-1">
            {t("login")}
          </Link>
        </div>

        <div className="flex items-center xl:hidden">
          <MobileMenu
            groups={mobileGroups}
            labels={{
              open: t("menu"),
              close: t("closeMenu"),
              login: t("login"),
              language: t("switchLanguage"),
              languageTitle: t("switchLanguageLabel"),
              legal: [
                { href: paths.privacy, label: t("privacy") },
                { href: paths.terms, label: t("terms") },
              ],
            }}
          />
        </div>
      </div>
    </header>
  );
}
