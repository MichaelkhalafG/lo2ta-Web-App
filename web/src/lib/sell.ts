/*
 * The seller wizard's data (PLAN 2.2), as core's SellerUnitResource answers it: types and pure helpers only, safe in
 * client components. The calls to core live in sellServer.ts (server only).
 */

export type ProblemMap = Record<string, string>;

export type ExitReport = {
  cancel: {
    penalty_min: number;
    penalty_max: number;
    penalty_basis: "total" | "paid";
    late_fees: number;
    refund_min: number;
    refund_max: number;
    refund_years: number;
    refund_pv_min: number | null;
    zone: "wipeout" | "deep_loss" | "moderate_loss";
  };
  transfer: {
    receive: number;
    given_up: number;
    transfer_fee: number;
    transfer_fee_bp: number | null;
    transfer_fee_source: "unit" | "setting";
    transfer_fee_payer: "seller" | "buyer";
    arrears_to_developer: number;
    arrears_settlement: string | null;
    arrears_self: number;
    lokta_fee: number;
    net: number;
  };
  advantage_min: number;
  advantage_max: number;
  provisional: string[];
};

export type SellerDocument = { id: string; type: string; status: string; name: string; mime: string; bytes: number; uploaded_at: string | null };
export type SellerPhoto = { id: string; kind: "photo" | "floor_plan"; is_cover: boolean; is_project_image: boolean; is_hidden: boolean; width: number; height: number };

export type Step1 = {
  developer: string | null;
  developer_other: string | null;
  compound: string | null;
  compound_other: string | null;
  area: string | null;
  area_other: string | null;
  main_type: string | null;
  sub_type: string | null;
  contract_number: string | null;
  unit_number: string | null;
  contract_year: number | null;
  contract_month: number | null;
};
export type Step2 = {
  size_m2: string | null;
  garden_m2: string | null;
  roof_m2: string | null;
  floor: string | null;
  bedrooms: number | null;
  bathrooms: number | null;
  finishing: string | null;
  delivery_status: string | null;
  delivery_year: number | null;
  delivery_month: number | null;
  facade: string[];
  features: string[];
};
export type Step3 = {
  total_price: number | null;
  paid_amount: number | null;
  market_price_estimate: number | null;
  installment_amount: number | null;
  installment_frequency: string | null;
  next_due_date: string | null;
  years_left: number | null;
  annual_extra_amount: number | null;
  delivery_payment: number | null;
  accepts_less: boolean;
  lowest_accepted_amount: number | null;
  has_overdue: boolean;
  overdue_count: number | null;
  overdue_amount: number | null;
  overdue_settlement: string | null;
  maintenance_due: number | null;
  transfer_fee_bp: number | null;
};

export type SellerUnit = {
  id: string;
  state: { code: string; label: string };
  editable: boolean;
  step_reached: number;
  reference: string | null;
  code: string | null;
  submitted_at: string | null;
  updated_at: string | null;
  steps: { step1: Step1; step2: Step2; step3: Step3; step5: { description: string | null } };
  documents: SellerDocument[];
  photos: SellerPhoto[];
  readiness: Record<"step1" | "step2" | "step3" | "step4" | "step5", ProblemMap | []>;
  warnings: { money: string[] };
  exit_report: ExitReport | null;
  pending_change: Record<string, [unknown, unknown]> | null;
  withdraw_requested: boolean;
  can_edit_live: boolean;
  can_withdraw: boolean;
  has_exit_report: boolean;
};

export type SellerUnitSummary = {
  id: string;
  state: { code: string; label: string };
  reference: string | null;
  code: string | null;
  sub_type: string | null;
  compound: string | null;
  step_reached: number;
  updated_at: string | null;
};

export type SubTypeChoice = {
  code: string;
  label: string;
  main_type: string;
  has_floor: boolean;
  has_rooms: boolean;
  fixed_bedrooms: number | null;
  has_garden: boolean;
  has_roof: boolean;
  has_finishing: boolean;
};

export type SellChoices = {
  main_types: { code: string; label: string }[];
  installment_frequencies: { code: string; label: string }[];
  sub_types: SubTypeChoice[];
  finishings: { code: string; label: string }[];
  delivery_statuses: { code: string; label: string }[];
  overdue_settlements: { code: string; label: string }[];
  document_types: { code: string; label: string }[];
  document_statuses: { code: string; label: string }[];
  unit_states: { code: string; label: string }[];
};

export type FeatureItem = { code: string; group: string; name: string; applies_to: string[] };
export type DeveloperItem = { slug: string; name: string };

/** A unit ID as core issues it (ULID); anything else never reaches core. */
export const UNIT_ID = /^[0-9A-HJKMNP-TV-Z]{26}$/;

/** A draft never sent (no reference yet) can be deleted by its seller; core refuses anything else (audit L8). */
export function draftDeletable(unit: Pick<SellerUnitSummary, "state" | "reference">): boolean {
  return unit.state.code === "draft" && unit.reference === null;
}

/** Problems of one step from the readiness map ([] in JSON when there are none). */
export function problemsOf(unit: SellerUnit, step: 1 | 2 | 3 | 4 | 5): ProblemMap {
  const value = unit.readiness[`step${step}`];

  return Array.isArray(value) ? {} : value;
}
