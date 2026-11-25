"use server";

import { routing, type Locale } from "@/i18n/routing";
import { core, type FieldErrors } from "../account";
import { parseAmount, type BuyerResult, type SellerResult } from "../tools";

/*
 * The calculator's two tabs (PLAN 4.1, Phase 6). Core computes every figure and stores nothing (J12); these carry
 * the typed values and the visitor's address (for the hourly limit). They work without JavaScript: the form posts
 * to the action and the page renders the answer.
 */

export type CalcState<T> = { result?: T; message?: string | null; errors?: FieldErrors; values?: Record<string, string> };

function localeOf(value: unknown): Locale {
  return value === "en" ? "en" : routing.defaultLocale;
}

function field(formData: FormData, name: string): string {
  const value = formData.get(name);

  return typeof value === "string" ? value.trim().slice(0, 40) : "";
}

/** A typed amount, or the text as typed so core names the field when it is not a number. */
function amount(text: string): number | string | null {
  if (text === "") {
    return null;
  }

  return parseAmount(text) ?? text;
}

export async function sellerCalcAction(_prev: CalcState<SellerResult>, formData: FormData): Promise<CalcState<SellerResult>> {
  const locale = localeOf(formData.get("locale"));
  const values = {
    total_price: field(formData, "total_price"),
    paid_amount: field(formData, "paid_amount"),
    years_paid: field(formData, "years_paid"),
    overdue_amount: field(formData, "overdue_amount"),
    developer: field(formData, "developer"),
  };
  const result = await core<{ data: SellerResult }>("POST", "calculator/seller", locale, {
    body: {
      total_price: amount(values.total_price),
      paid_amount: amount(values.paid_amount),
      years_paid: amount(values.years_paid),
      overdue_amount: amount(values.overdue_amount),
      developer: values.developer === "" ? null : values.developer,
    },
  });

  return result.ok ? { result: result.data.data, values } : { message: result.message, errors: result.errors, values };
}

export async function buyerCalcAction(_prev: CalcState<BuyerResult>, formData: FormData): Promise<CalcState<BuyerResult>> {
  const locale = localeOf(formData.get("locale"));
  const values = { contract_price: field(formData, "contract_price"), price_today: field(formData, "price_today") };
  const result = await core<{ data: BuyerResult }>("POST", "calculator/buyer", locale, {
    body: { contract_price: amount(values.contract_price), price_today: amount(values.price_today) },
  });

  return result.ok ? { result: result.data.data, values } : { message: result.message, errors: result.errors, values };
}
