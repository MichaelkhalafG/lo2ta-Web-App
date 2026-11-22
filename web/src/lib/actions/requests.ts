"use server";

import { redirect } from "@/i18n/navigation";
import { routing, type Locale } from "@/i18n/routing";
import { core, type FieldErrors } from "../account";
import { returnQuery, safeNext } from "../intent";
import { endSession, sessionToken } from "../session";

/*
 * "Request this unit" (PLAN 2.3) and the two public forms (PLAN 2.6). Core does every rule (confirmed e-mail, the
 * 5-open cap, the exact fee amount, masking, limits, honeypot); these only carry the values.
 */

export type FormResult = { ok: boolean; reference?: string; message?: string | null; errors?: FieldErrors; values?: Record<string, string> };

function localeOf(value: unknown): Locale {
  return value === "en" ? "en" : routing.defaultLocale;
}

function field(formData: FormData, name: string): string {
  const value = formData.get(name);

  return typeof value === "string" ? value.trim() : "";
}

export async function requestUnitAction(_prev: FormResult, formData: FormData): Promise<FormResult> {
  const locale = localeOf(formData.get("locale"));
  const next = safeNext(formData.get("next"), "/opportunities");
  if (!(await sessionToken())) {
    redirect({ href: `/login?${returnQuery(next, "request")}`, locale });
  }
  const values = { question: field(formData, "question").slice(0, 500), readiness: field(formData, "readiness") };
  const result = await core<{ data: { id: string; reference: string } }>("POST", "me/requests", locale, {
    auth: true,
    body: {
      code: field(formData, "code"),
      question: values.question,
      readiness: values.readiness,
      fee_consent: formData.get("fee_consent") === "on",
      fee_amount: Number(field(formData, "fee_amount")),
      website: field(formData, "website"),
    },
  });
  if (result.status === 401) {
    await endSession();
    redirect({ href: `/login?${returnQuery(next, "request")}`, locale });
  }
  if (result.ok) {
    return { ok: true, reference: result.data.data.reference };
  }

  return { ok: false, message: result.message, errors: result.errors, values };
}

export async function contactAction(_prev: FormResult, formData: FormData): Promise<FormResult> {
  const locale = localeOf(formData.get("locale"));
  const values = { name: field(formData, "name"), email: field(formData, "email"), phone: field(formData, "phone"), message: field(formData, "message") };
  const result = await core("POST", "contact", locale, { body: { ...values, website: field(formData, "website") } });

  return result.ok ? { ok: true } : { ok: false, message: result.message, errors: result.errors, values };
}

export async function partnershipAction(_prev: FormResult, formData: FormData): Promise<FormResult> {
  const locale = localeOf(formData.get("locale"));
  const values = {
    company: field(formData, "company"),
    contact_name: field(formData, "contact_name"),
    job_title: field(formData, "job_title"),
    email: field(formData, "email"),
    phone: field(formData, "phone"),
    projects_count: field(formData, "projects_count"),
    message: field(formData, "message"),
  };
  const result = await core("POST", "partnerships", locale, {
    body: { ...values, projects_count: values.projects_count === "" ? null : Number(values.projects_count), website: field(formData, "website") },
  });

  return result.ok ? { ok: true } : { ok: false, message: result.message, errors: result.errors, values };
}
