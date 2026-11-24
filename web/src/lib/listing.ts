/*
 * Public listing data (PLAN 2.5): the unit card, the unit page, the compound pages and the opportunities filters.
 * Types and pure helpers only, safe in client components; the reads live in listingServer.ts.
 */

export type Choice = { code: string; label: string };

export type UnitCard = {
  code: string;
  slug: string | null;
  title: string;
  main_type: Choice | null;
  sub_type: Choice | null;
  size_m2: string | null;
  facade: string[] | null;
  place: string[];
  delivery: { status: string | null; month: number | null; year: number | null };
  total_price: number | null;
  market_price_estimate: number | null;
  paid_amount: number | null;
  remaining_amount: number | null;
  paid_percent: number | null;
  owner_from: number | null;
  accepts_less: boolean;
  installments: { count: number | null; amount: number | null; frequency: Choice | null };
  is_verified: boolean;
  deal_tag: boolean;
  is_reserved: boolean;
  cover: { src: string; width: number; height: number; is_project_image: boolean } | null;
};

export type ListMeta = { page: number; last_page: number; per_page: number; total: number };

export type UnitDetail = {
  code: string;
  slug: string | null;
  title: string;
  state: string;
  is_verified: boolean;
  deal_tag: boolean;
  published_at: string | null;
  main_type: Choice | null;
  sub_type: Choice | null;
  developer: { slug: string; name: string } | null;
  compound: { slug: string; name: string } | null;
  location: { governorate: { slug: string; name: string } | null; area: { slug: string; name: string } | null; district: { slug: string; name: string } | null } | null;
  contract_year: number | null;
  contract_month: number | null;
  size_m2: string | null;
  garden_m2: string | null;
  roof_m2: string | null;
  floor: string | null;
  bedrooms: number | null;
  bathrooms: number | null;
  finishing: Choice | null;
  delivery_status: Choice | null;
  delivery_year: number | null;
  delivery_month: number | null;
  facade: string[] | null;
  description: string | null;
  features: { code: string; group: string; name: string }[];
  photos: { kind: "photo" | "floor_plan"; src: string; width: number; height: number; is_project_image: boolean }[];
  money: {
    total_price: number | null;
    paid_amount: number | null;
    remaining_amount: number | null;
    market_price_estimate: number | null;
    installment_amount: number | null;
    installment_frequency: Choice | null;
    installments_per_year: number | null;
    next_due_date: string | null;
    years_left: number | null;
    annual_extra_amount: number | null;
    delivery_payment: number | null;
    accepts_less: boolean;
    lowest_accepted_amount: number | null;
    has_overdue: boolean;
    overdue_count: number | null;
    overdue_amount: number | null;
    overdue_settlement: Choice | null;
    to_developer_for_arrears: number | null;
    maintenance_due: number | null;
    owner_from: number | null;
    paid_percent: number | null;
    installments_left: number | null;
    buyer_fee: { bp: number; amount: number; vat_bp: number; vat: number };
  };
  schedule: { n: number; date: string; amount: number }[];
};

export type CompoundIndexItem = {
  slug: string;
  name: string;
  developer: { slug: string; name: string } | null;
  place: string[];
  units: number;
};

export type CompoundPage = CompoundIndexItem & { intro: string | null; intro_is_placeholder: boolean; units: UnitCard[] };

/** The opportunities filters, as the URL carries them (PLAN 2.5; Michael 2026-11-22). */
export type Filters = {
  type?: string;
  area?: string;
  owner_max?: number;
  installment_max?: number;
  verified?: boolean;
  /** Only units with the لقطة tag (home "لقطات الأسبوع"); not offered as a filter on the page. */
  deal?: boolean;
  sort?: string;
  view?: "grid" | "list";
  page?: number;
};

export const SORTS = ["default", "owner_low", "installment_low", "newest"] as const;
export const TYPES = ["residential", "commercial", "administrative", "land"] as const;
/** Ready-made amounts for the two money filters (EGP). */
export const OWNER_STEPS = [500_000, 750_000, 1_000_000, 1_500_000, 2_000_000, 3_000_000, 5_000_000];
export const INSTALLMENT_STEPS = [25_000, 50_000, 75_000, 100_000, 150_000, 250_000];

/** Only known values pass, so core never sees a bad query and a crafted URL changes nothing. */
export function parseFilters(params: Record<string, string | string[] | undefined>): Filters {
  const one = (key: string): string | undefined => {
    const value = params[key];
    return typeof value === "string" ? value : undefined;
  };
  const whole = (key: string, max: number): number | undefined => {
    const value = one(key);
    return value !== undefined && /^\d{1,10}$/.test(value) && Number(value) >= 1 && Number(value) <= max ? Number(value) : undefined;
  };
  const filters: Filters = {};
  const type = one("type");
  if (type && (TYPES as readonly string[]).includes(type)) filters.type = type;
  const area = one("area");
  if (area && /^[a-z0-9-]{1,96}$/.test(area)) filters.area = area;
  const owner = whole("owner_max", 1_000_000_000);
  if (owner) filters.owner_max = owner;
  const installment = whole("installment_max", 100_000_000);
  if (installment) filters.installment_max = installment;
  if (one("verified") === "1") filters.verified = true;
  const sort = one("sort");
  if (sort && (SORTS as readonly string[]).includes(sort) && sort !== "default") filters.sort = sort;
  if (one("view") === "list") filters.view = "list";
  const page = whole("page", 1000);
  if (page && page > 1) filters.page = page;

  return filters;
}

/** The query string of a filter set (only what is set), for links and the core call. */
export function filterQuery(filters: Filters, overrides: Partial<Filters> = {}): string {
  const merged: Filters = { ...filters, ...overrides };
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(merged)) {
    if (value === undefined || value === false || value === "" || (key === "page" && value === 1) || (key === "view" && value === "grid")) continue;
    params.set(key, value === true ? "1" : String(value));
  }

  return params.toString();
}

/** A filtered listing is a search result: never indexed (PLAN 1.2); plain pages 2..n are. */
export function isFiltered(filters: Filters): boolean {
  return Object.entries(filters).some(([key, value]) => key !== "page" && key !== "view" && value !== undefined);
}

/** The unit code at the end of a slug: "…-lk-nc-0124" (PLAN 5.2 point 7). */
export function codeFromSlug(slug: string): string | null {
  const match = /(lk-[a-z0-9]{1,4}-\d{4,6})$/i.exec(slug);

  return match ? match[1]!.toUpperCase() : null;
}

export function money(value: number | null | undefined): string {
  return value === null || value === undefined ? "—" : new Intl.NumberFormat("en-US").format(value);
}

/** "12.2027" as on the board. */
export function monthYear(month: number | null, year: number | null): string | null {
  return year ? `${month ? String(month).padStart(2, "0") + "." : ""}${year}` : null;
}

/**
 * Filters kept behind the phone "تصفية" button (approved proposal 2026-11-24): area, both amounts and verified. The
 * count beside the button tells how many are set; type and sort stay outside the panel.
 */
export function panelFilterCount(filters: Filters): number {
  return [filters.area, filters.owner_max, filters.installment_max, filters.verified].filter((v) => v !== undefined && v !== false).length;
}

/** The week's لقطة stands wide at the top of page 1 of the plain list only, never on a search or a later page. */
export function showsFeatured(filters: Filters): boolean {
  return !isFiltered(filters) && (filters.page ?? 1) === 1;
}

/** Where a place slug sits in the area tree, for the result line ("38 units in New Cairo"). */
export function placeName(tree: { slug: string; name: string; areas: { slug: string; name: string; districts: { slug: string; name: string }[] }[] }[], slug: string | undefined): string | null {
  if (!slug) return null;
  for (const gov of tree) {
    if (gov.slug === slug) return gov.name;
    for (const area of gov.areas) {
      if (area.slug === slug) return area.name;
      const district = area.districts.find((d) => d.slug === slug);
      if (district) return district.name;
    }
  }

  return null;
}

/** A filter field left out of the address: empty, the default sort, or an unchecked radio or box. */
export function omitted(el: { name: string; value: string; type?: string; checked?: boolean }): boolean {
  if (el.type === "radio" || el.type === "checkbox") return !el.checked || el.value === "";
  return el.value === "" || (el.name === "sort" && el.value === "default");
}
