/*
 * The public tools (Phase 6): the market values the calculator and the reports page show, and the calculator's
 * input rules. Pure functions only, so they run in tests without the app. The public site never receives a source,
 * a note or a developer name (J10; K6: the public calculator uses the market values only); each value shows its
 * range or rule and the month it was checked.
 */

export type MarketValueEntry = {
  key: "cancellation" | "refund" | "late_fee" | "transfer_fee" | "maintenance_deposit" | "discount_rate";
  checked: string; // YYYY-MM
  under_review: boolean;
  provisional: boolean;
  values: Record<string, number | boolean | string>;
};

export type MarketValues = { values: MarketValueEntry[] };

export type Indicator = { title: string; value: string; unit: "pct" | "egp" | "count" | "months" | "years"; note: string | null; as_of: string };

export type SellerResult = {
  cancel: {
    penalty_min: number;
    penalty_max: number;
    penalty_basis: "total" | "paid";
    late_fees: number;
    refund_min: number;
    refund_max: number;
    refund_rule: "lump" | "mirror" | "fixed";
    refund_years: number;
    refund_pv_min: number | null;
    zone: "wipeout" | "deep_loss" | "moderate_loss";
  };
  transfer: {
    receive: number;
    transfer_fee: number;
    transfer_fee_bp: number | null;
    arrears_to_developer: number;
    late_fees: number;
    lokta_fee: number;
    net: number;
  };
  advantage_min: number;
  advantage_max: number;
  provisional: string[];
  settings: Record<string, number | boolean | string>;
};

export type BuyerResult = { contract_price: number; price_today: number; fee_bp: number; fee: number; fee_vat: number; gain: number; gain_bp: number };

/** A share in basis points as a percentage: 1000 → "10%", 1250 → "12.5%". */
export function percent(bp: number): string {
  return `${Math.round(bp) / 100}%`;
}

/** "2026-11" → "November 2026" / "نوفمبر 2026" (Latin digits, as every figure on the site). */
export function monthYear(checked: string, locale: string): string {
  const match = /^(\d{4})-(\d{2})$/.exec(checked);
  if (!match) {
    return checked;
  }
  const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1));

  return new Intl.DateTimeFormat(locale === "en" ? "en-GB" : "ar-EG-u-nu-latn", { month: "long", year: "numeric", timeZone: "UTC" }).format(date);
}

/** One message key and its values, to be put into words by the page's translations. */
export type Phrase = { key: string; values: Record<string, string | number> };

function num(values: Record<string, number | boolean | string>, key: string): number {
  const value = values[key];

  return typeof value === "number" ? value : 0;
}

/**
 * How one value reads on the site: the value the client confirmed, or a range or rule (J1). The key names a message in the
 * `tools.values.text` group.
 */
export function describeValue(entry: MarketValueEntry): Phrase {
  const v = entry.values;
  switch (entry.key) {
    case "cancellation": {
      const min = num(v, "penalty_min_bp");
      const max = Math.max(min, num(v, "penalty_max_bp"));
      const basis = v.penalty_basis === "paid" ? "Paid" : "Total";

      return min === max ? { key: `cancellationOne${basis}`, values: { rate: percent(min) } } : { key: `cancellation${basis}`, values: { min: percent(min), max: percent(max) } };
    }
    case "refund":
      if (v.refund_rule === "lump") {
        return { key: "refundLump", values: {} };
      }

      return v.refund_rule === "fixed" ? { key: "refundFixed", values: { years: num(v, "refund_years_fixed") } } : { key: "refundMirror", values: {} };
    case "late_fee":
      return { key: "lateFee", values: { rate: percent(num(v, "late_fee_bp")) } };
    case "transfer_fee":
      return v.transfer_fee_mode === "fixed"
        ? { key: "transferFeeFixed", values: { amount: new Intl.NumberFormat("en-US").format(num(v, "transfer_fee_fixed")) } }
        : { key: "transferFee", values: { rate: percent(num(v, "transfer_fee_bp")) } };
    case "maintenance_deposit": {
      // K4: "usually 8% to 10% of the unit price, moves to the buyer with the transfer".
      const min = num(v, "maintenance_deposit_min_bp");
      const max = Math.max(min, num(v, "maintenance_deposit_max_bp"));
      const moves = v.maintenance_deposit_moves === false ? "Stays" : "Moves";
      if (max === 0) {
        return { key: `maintenance${moves}`, values: {} };
      }

      return min === max ? { key: `maintenanceOne${moves}`, values: { rate: percent(min) } } : { key: `maintenanceRange${moves}`, values: { min: percent(min), max: percent(max) } };
    }
    case "discount_rate":
      return { key: "discountRate", values: { rate: percent(num(v, "discount_rate_bp")) } };
  }
}

/**
 * A money amount as typed: Arabic-Indic or Persian digits, thousands separators and spaces are accepted; anything
 * else, a fraction or a negative number is refused (null). Core checks the limits again.
 */
export function parseAmount(text: string | null | undefined): number | null {
  if (typeof text !== "string") {
    return null;
  }
  const latin = text.replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660)).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06f0));
  const clean = latin.replace(/[\s,٬]/g, "");
  if (!/^\d{1,13}$/.test(clean)) {
    return null;
  }

  return Number(clean);
}

/** The seller tab's tab value from the address bar: anything but "buyer" is the seller tab. */
export function calculatorTab(value: string | string[] | undefined): "seller" | "buyer" {
  return value === "buyer" ? "buyer" : "seller";
}
