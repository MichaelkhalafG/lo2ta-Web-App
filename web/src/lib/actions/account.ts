"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "@/i18n/navigation";
import { routing, type Locale } from "@/i18n/routing";
import { core, type FieldErrors } from "../account";
import { intentPath, parseIntent, returnQuery, safeNext, type Intent } from "../intent";
import { endSession, sessionToken, startSession } from "../session";

/*
 * Account server actions (PLAN 2.1, 2.3). They run on the Next server only; the core API does every rule and every
 * write. A form posts its language in a hidden field so core answers errors in that language.
 */

export type FormState = { ok?: boolean; message?: string | null; errors?: FieldErrors; values?: Record<string, string> };

function localeOf(formData: FormData): Locale {
  const value = formData.get("locale");

  return value === "en" ? "en" : routing.defaultLocale;
}

function text(formData: FormData, name: string): string {
  const value = formData.get(name);

  return typeof value === "string" ? value.trim() : "";
}

function failed(message: string | null, errors: FieldErrors, values: Record<string, string> = {}): FormState {
  return { ok: false, message, errors, values };
}

/** Complete a pending action after sign-in, then go where the visitor was heading. */
async function finishSignIn(token: string, locale: Locale, intent: Intent | null, next: string): Promise<never> {
  if (intent?.kind === "favorite") {
    await core("POST", "me/favorites", locale, { token, body: { code: intent.code } });
  }

  return redirect({ href: intentPath(intent, next), locale });
}

export async function loginAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const email = text(formData, "email");
  const result = await core<{ token: string }>("POST", "auth/login", locale, {
    body: { email, password: formData.get("password") ?? "" },
  });
  if (!result.ok) {
    return failed(result.message, result.errors, { email });
  }
  await startSession(result.data.token);

  return finishSignIn(result.data.token, locale, parseIntent(formData.get("intent")), safeNext(formData.get("next")));
}

export async function registerAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const values = { name: text(formData, "name"), email: text(formData, "email"), phone: text(formData, "phone") };
  const result = await core<{ token: string }>("POST", "auth/register", locale, {
    body: {
      ...values,
      password: formData.get("password") ?? "",
      password_confirmation: formData.get("password_confirmation") ?? "",
      terms: formData.get("terms") === "on",
      website: text(formData, "website"),
    },
  });
  if (!result.ok) {
    return failed(result.message, result.errors, values);
  }
  await startSession(result.data.token);

  return finishSignIn(result.data.token, locale, parseIntent(formData.get("intent")), safeNext(formData.get("next")));
}

export async function logoutAction(formData: FormData): Promise<never> {
  const locale = localeOf(formData);
  await core("POST", "auth/logout", locale, { auth: true });
  await endSession();

  return redirect({ href: "/", locale });
}

export async function forgotAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const email = text(formData, "email");
  const result = await core("POST", "auth/forgot-password", locale, { body: { email } });

  return result.ok ? { ok: true, values: { email } } : failed(result.message, result.errors, { email });
}

export async function resetAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const email = text(formData, "email");
  const result = await core("POST", "auth/reset-password", locale, {
    body: {
      token: text(formData, "token"),
      email,
      password: formData.get("password") ?? "",
      password_confirmation: formData.get("password_confirmation") ?? "",
    },
  });
  if (!result.ok) {
    return failed(result.message, result.errors, { email });
  }

  return redirect({ href: "/login?reset=1", locale });
}

export async function resendAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const result = await core<{ sent: boolean }>("POST", "auth/resend-verification", locale, { auth: true });
  if (result.status === 401) {
    return signedOut(locale, "/account");
  }

  return result.ok ? { ok: true } : failed(result.message, result.errors);
}

export async function profileAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const values = { name: text(formData, "name"), phone: text(formData, "phone"), locale: text(formData, "preferred_locale") };
  const result = await core("PUT", "me", locale, { auth: true, body: { ...values, locale: values.locale } });
  if (result.status === 401) {
    return signedOut(locale, "/account");
  }

  if (result.ok) {
    // React resets a form after its action: refresh the page so the reset shows the saved values.
    revalidatePath("/[locale]/account", "page");
    return { ok: true, values };
  }

  return failed(result.message, result.errors, values);
}

export async function budgetAction(_prev: FormState, formData: FormData): Promise<FormState> {
  const locale = localeOf(formData);
  const money = (name: string): number | null => {
    const digits = text(formData, name).replace(/[٠-٩]/g, (d) => String("٠١٢٣٤٥٦٧٨٩".indexOf(d))).replace(/[,\s]/g, "");
    return digits === "" ? null : Number(digits);
  };
  const list = (name: string): string[] => formData.getAll(name).filter((v): v is string => typeof v === "string" && v !== "");
  const body = {
    cash_available: money("cash_available"),
    max_installment: money("max_installment"),
    installment_frequency: text(formData, "installment_frequency"),
    main_types: list("main_types"),
    areas: list("areas"),
    compounds: list("compounds"),
    target_price: money("target_price"),
    min_bedrooms: text(formData, "min_bedrooms") === "" ? null : Number(text(formData, "min_bedrooms")),
    delivery_pref: text(formData, "delivery_pref"),
  };
  const result = await core("PUT", "me/budget", locale, { auth: true, body });
  // Typed values go back with an error: React resets the form after the action, and the reset uses them.
  const typed: Record<string, string> = {
    cash_available: text(formData, "cash_available"),
    max_installment: text(formData, "max_installment"),
    installment_frequency: body.installment_frequency,
    target_price: text(formData, "target_price"),
    min_bedrooms: text(formData, "min_bedrooms"),
    delivery_pref: body.delivery_pref,
    main_types: body.main_types.join(","),
    areas: body.areas.join(","),
    compounds: body.compounds.join(","),
  };
  if (result.status === 401) {
    return signedOut(locale, "/account/budget");
  }

  if (result.ok) {
    revalidatePath("/[locale]/account/budget", "page");
    return { ok: true };
  }

  return failed(result.message, result.errors, typed);
}

/**
 * The heart on a card or unit page. Signed out: go to sign-in and save the unit on the way back (return-to-action).
 */
export async function favoriteAction(formData: FormData): Promise<void> {
  const locale = localeOf(formData);
  const code = text(formData, "code").toUpperCase();
  const next = safeNext(formData.get("next"), "/");
  if (!/^[A-Z0-9-]{3,16}$/.test(code)) {
    return;
  }
  if (!(await sessionToken())) {
    redirect({ href: `/login?${returnQuery(next, `favorite:${code}`)}`, locale });
  }
  const saved = formData.get("saved") === "1";
  const result = saved
    ? await core("DELETE", `me/favorites/${code}`, locale, { auth: true })
    : await core("POST", "me/favorites", locale, { auth: true, body: { code } });
  if (result.status === 401) {
    await endSession();
    redirect({ href: `/login?${returnQuery(next, `favorite:${code}`)}`, locale });
  }
}

async function signedOut(locale: Locale, next: string): Promise<never> {
  await endSession();

  return redirect({ href: `/login?${returnQuery(next)}`, locale });
}
