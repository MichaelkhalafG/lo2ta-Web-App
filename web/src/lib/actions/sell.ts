"use server";

import { redirect } from "@/i18n/navigation";
import { routing, type Locale } from "@/i18n/routing";
import { core, type FieldErrors } from "../account";
import { returnQuery } from "../intent";
import { endSession } from "../session";
import { UNIT_ID, type SellerUnit } from "../sell";

/*
 * The seller wizard's server actions (PLAN 2.2). Core does every rule; these only carry the values and the token.
 * IDs are checked against the ULID pattern before any call, so nothing else reaches core's URLs.
 */

export type SellResult = { ok: boolean; unit?: SellerUnit; errors?: FieldErrors; message?: string | null; status?: number };

function localeOf(value: unknown): Locale {
  return value === "en" ? "en" : routing.defaultLocale;
}

async function signedOut(locale: Locale, next: string): Promise<never> {
  await endSession();

  return redirect({ href: `/login?${returnQuery(next, "sell")}`, locale });
}

async function answer(result: Awaited<ReturnType<typeof core<{ data: SellerUnit }>>>, locale: Locale, next: string): Promise<SellResult> {
  if (result.status === 401) {
    return signedOut(locale, next);
  }
  if (result.ok) {
    return { ok: true, unit: result.data.data };
  }

  return { ok: false, errors: result.errors, message: result.message, status: result.status };
}

/** Open a new draft after the no-over confirmation, then go to step 1. */
export async function createDraftAction(formData: FormData): Promise<void> {
  const locale = localeOf(formData.get("locale"));
  const result = await core<{ data: SellerUnit }>("POST", "me/units", locale, { auth: true, body: { no_over: formData.get("no_over") === "on" } });
  if (result.status === 401) {
    await signedOut(locale, "/sell");
  }
  if (result.ok) {
    redirect({ href: `/sell/${result.data.data.id}/1`, locale });
  }
  redirect({ href: `/sell?error=${result.status === 429 ? "throttled" : "draft"}`, locale });
}

/** Autosave a step; with complete, check it and move the unit on. */
export async function saveStepAction(id: string, step: number, values: Record<string, unknown>, complete: boolean, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id) || ![1, 2, 3, 4, 5].includes(step)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }
  const result = await core<{ data: SellerUnit }>("PUT", `me/units/${id}/steps/${step}`, locale, { auth: true, body: { ...values, complete } });

  return answer(result, locale, `/sell/${id}/${step}`);
}

export async function submitUnitAction(id: string, declarations: Record<string, boolean>, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }
  const result = await core<{ data: SellerUnit }>("POST", `me/units/${id}/submit`, locale, { auth: true, body: { declarations } });
  if (result.ok) {
    redirect({ href: `/sell/${id}/done`, locale });
  }

  return answer(result, locale, `/sell/${id}/6`);
}

/** The unit as core has it now (after an upload through the file proxy). */
export async function refreshUnitAction(id: string, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }

  return answer(await core<{ data: SellerUnit }>("GET", `me/units/${id}`, locale, { auth: true }), locale, `/sell/${id}`);
}

export async function deleteDocumentAction(id: string, document: string, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id) || !UNIT_ID.test(document)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }
  const removed = await core("DELETE", `me/units/${id}/documents/${document}`, locale, { auth: true });
  if (!removed.ok) {
    return { ok: false, errors: removed.errors, message: removed.message, status: removed.status };
  }

  return answer(await core<{ data: SellerUnit }>("GET", `me/units/${id}`, locale, { auth: true }), locale, `/sell/${id}/4`);
}

export async function deletePhotoAction(id: string, photo: string, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id) || !UNIT_ID.test(photo)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }

  return answer(await core<{ data: SellerUnit }>("DELETE", `me/units/${id}/photos/${photo}`, locale, { auth: true }), locale, `/sell/${id}/5`);
}

export async function reorderPhotosAction(id: string, order: string[], projectImages: string[], locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id) || ![...order, ...projectImages].every((p) => UNIT_ID.test(p))) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }
  const result = await core<{ data: SellerUnit }>("PUT", `me/units/${id}/photos`, locale, {
    auth: true,
    body: { order, project_images: projectImages },
  });

  return answer(result, locale, `/sell/${id}/5`);
}

/** Edit a published unit (PLAN 2.2.7): free fields go live, review fields wait for the admin. */
export async function liveEditAction(id: string, values: Record<string, unknown>, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }

  return answer(await core<{ data: SellerUnit }>("PUT", `me/units/${id}/live`, locale, { auth: true, body: values }), locale, `/account/units/${id}`);
}

/** Withdraw, or ask to withdraw during an open deal (PLAN 2.2.7, 2.2.9 point 5). */
export async function withdrawAction(id: string, reason: string, locale: Locale): Promise<SellResult> {
  if (!UNIT_ID.test(id)) {
    return { ok: false, errors: {}, message: null, status: 404 };
  }

  return answer(await core<{ data: SellerUnit }>("POST", `me/units/${id}/withdraw`, locale, { auth: true, body: { reason: reason.trim() === "" ? null : reason.trim().slice(0, 500) } }), locale, `/account/units/${id}`);
}

/** Delete a draft that was never submitted, then back to the units list. */
export async function deleteDraftAction(formData: FormData): Promise<void> {
  const locale = localeOf(formData.get("locale"));
  const id = String(formData.get("id") ?? "");
  if (UNIT_ID.test(id)) {
    const result = await core("DELETE", `me/units/${id}`, locale, { auth: true });
    if (result.status === 401) {
      await signedOut(locale, "/account/units");
    }
  }
  redirect({ href: "/account/units", locale });
}
