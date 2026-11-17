/*
 * The public site's navigation (board 04, "Header · grouped menu"): three direct links, two dropdowns (Tools,
 * About), two audience links at the end; privacy and terms live in the footer. Paths are the same in both
 * languages (PLAN 1.1, option A); next-intl adds /en for English.
 */

export type NavKey =
  | "opportunities"
  | "compounds"
  | "developers"
  | "calculator"
  | "reports"
  | "how"
  | "aboutUs"
  | "faq"
  | "contact"
  | "buyers"
  | "sell"
  | "login"
  | "privacy"
  | "terms";

export const paths: Record<NavKey, string> = {
  opportunities: "/opportunities",
  compounds: "/compounds",
  developers: "/developers",
  calculator: "/calculator",
  reports: "/reports",
  how: "/how-it-works",
  aboutUs: "/about",
  faq: "/faq",
  contact: "/contact",
  buyers: "/buyers",
  sell: "/sell",
  login: "/login",
  privacy: "/privacy",
  terms: "/terms",
};

export const directLinks: NavKey[] = ["opportunities", "compounds", "developers"];

/** Dropdown groups, each item with its board code (T1, A1 …) and a one-line hint. */
export const menus: { key: "tools" | "about"; items: { key: NavKey; code: string; hint: NavKey | null }[] }[] = [
  {
    key: "tools",
    items: [
      { key: "calculator", code: "T1", hint: "calculator" },
      { key: "reports", code: "T2", hint: "reports" },
    ],
  },
  {
    key: "about",
    items: [
      { key: "how", code: "A1", hint: "how" },
      { key: "aboutUs", code: "A2", hint: "aboutUs" },
      { key: "faq", code: "A3", hint: "faq" },
      { key: "contact", code: "A4", hint: null },
    ],
  },
];

/** Footer columns (board 06.1). */
export const footerColumns: { title: "platform" | "tools" | "clients" | "brand"; items: NavKey[] }[] = [
  { title: "platform", items: ["opportunities", "compounds", "developers"] },
  { title: "tools", items: ["calculator", "reports"] },
  { title: "clients", items: ["buyers", "sell", "how"] },
  { title: "brand", items: ["aboutUs", "faq", "contact"] },
];
