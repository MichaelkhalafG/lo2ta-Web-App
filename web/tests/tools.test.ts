// The public tools (Phase 6): how each confirmed value reads on the site, and the calculator's input rules.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { calculatorTab, describeValue, monthYear, parseAmount, percent, type MarketValueEntry } from "../src/lib/tools.ts";

const entry = (key: MarketValueEntry["key"], values: MarketValueEntry["values"]): MarketValueEntry => ({ key, checked: "2026-11", under_review: false, provisional: false, values });

test("the client's values of 2026-11-25 read as single values, never as a range", () => {
  assert.deepEqual(describeValue(entry("cancellation", { penalty_basis: "total", penalty_min_bp: 1500, penalty_max_bp: 1500 })), { key: "cancellationOneTotal", values: { rate: "15%" } });
  assert.deepEqual(describeValue(entry("refund", { refund_rule: "lump", refund_years_fixed: 0 })), { key: "refundLump", values: {} });
  assert.deepEqual(describeValue(entry("late_fee", { late_fee_bp: 100 })), { key: "lateFee", values: { rate: "1%" } });
  assert.deepEqual(describeValue(entry("transfer_fee", { transfer_fee_mode: "fixed", transfer_fee_bp: 0, transfer_fee_fixed: 50_000 })), { key: "transferFeeFixed", values: { amount: "50,000" } });
  // K4: "usually 8% to 10% of the unit price, moves to the buyer with the transfer".
  assert.deepEqual(describeValue(entry("maintenance_deposit", { maintenance_deposit_moves: true, maintenance_deposit_min_bp: 800, maintenance_deposit_max_bp: 1000 })), { key: "maintenanceRangeMoves", values: { min: "8%", max: "10%" } });
  assert.deepEqual(describeValue(entry("maintenance_deposit", { maintenance_deposit_moves: false, maintenance_deposit_min_bp: 1000, maintenance_deposit_max_bp: 1000 })), { key: "maintenanceOneStays", values: { rate: "10%" } });
  assert.deepEqual(describeValue(entry("maintenance_deposit", { maintenance_deposit_moves: true })), { key: "maintenanceMoves", values: {} });
  assert.deepEqual(describeValue(entry("maintenance_deposit", { maintenance_deposit_moves: false })), { key: "maintenanceStays", values: {} });
});

test("other settings still read correctly: a range, the paid basis, a share, years", () => {
  assert.deepEqual(describeValue(entry("cancellation", { penalty_basis: "paid", penalty_min_bp: 1000, penalty_max_bp: 1250 })), { key: "cancellationPaid", values: { min: "10%", max: "12.5%" } });
  // A high end below the low end never prints an upside-down range.
  assert.deepEqual(describeValue(entry("cancellation", { penalty_basis: "total", penalty_min_bp: 1500, penalty_max_bp: 900 })).key, "cancellationOneTotal");
  assert.deepEqual(describeValue(entry("transfer_fee", { transfer_fee_mode: "pct", transfer_fee_bp: 250, transfer_fee_fixed: 0 })), { key: "transferFee", values: { rate: "2.5%" } });
  assert.deepEqual(describeValue(entry("refund", { refund_rule: "fixed", refund_years_fixed: 3 })), { key: "refundFixed", values: { years: 3 } });
  assert.deepEqual(describeValue(entry("refund", { refund_rule: "mirror" })), { key: "refundMirror", values: {} });
  assert.deepEqual(describeValue(entry("discount_rate", { discount_rate_bp: 2000 })), { key: "discountRate", values: { rate: "20%" } });
});

test("every phrase has its words in both languages", () => {
  for (const lang of ["en", "ar"]) {
    const messages = JSON.parse(readFileSync(new URL(`../messages/${lang}.json`, import.meta.url), "utf-8"));
    const words = messages.tools.values.text as Record<string, string>;
    for (const key of ["cancellationOneTotal", "cancellationPaid", "refundLump", "refundFixed", "refundMirror", "lateFee", "transferFee", "transferFeeFixed", "maintenanceMoves", "maintenanceStays", "maintenanceRangeMoves", "maintenanceOneMoves", "maintenanceRangeStays", "maintenanceOneStays", "discountRate"]) {
      assert.equal(typeof words[key], "string", `${lang}: tools.values.text.${key}`);
    }
  }
});

test("parseAmount takes Latin, Arabic-Indic and grouped digits, and refuses anything else", () => {
  assert.equal(parseAmount("6,000,000"), 6_000_000);
  assert.equal(parseAmount("٦٠٠٠٠٠٠"), 6_000_000);
  assert.equal(parseAmount("۱ ۵۰۰ ۰۰۰"), 1_500_000);
  for (const bad of ["", "-5", "1.5", "1e9", "abc", "12345678901234", null, undefined]) {
    assert.equal(parseAmount(bad), null, String(bad));
  }
});

test("monthYear, percent and the tab from the address bar", () => {
  assert.equal(monthYear("2026-11", "en"), "November 2026");
  assert.match(monthYear("2026-11", "ar"), /2026/);
  assert.equal(monthYear("not-a-month", "en"), "not-a-month");
  assert.equal(percent(1500), "15%");
  assert.equal(calculatorTab("buyer"), "buyer");
  assert.equal(calculatorTab(["buyer"]), "seller");
  assert.equal(calculatorTab("<script>"), "seller");
});

test("the calculator messages no longer offer or name a developer (K6)", () => {
  for (const lang of ["en", "ar"]) {
    const messages = JSON.parse(readFileSync(new URL(`../messages/${lang}.json`, import.meta.url), "utf-8"));
    for (const key of ["developer", "developerMarket", "developerHint"]) {
      assert.equal(messages.tools.seller[key], undefined, `${lang}: tools.seller.${key}`);
    }
    assert.equal(messages.tools.result.developerTerms, undefined, `${lang}: tools.result.developerTerms`);
  }
});
