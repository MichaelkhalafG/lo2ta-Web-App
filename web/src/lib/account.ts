import { headers } from "next/headers";
import type { Locale } from "@/i18n/routing";
import { clientIpFrom } from "./clientIp";
import { coreConfig, coreUrl } from "./coreConfig";
import { sessionToken } from "./session";

/*
 * Account calls to the core API (PLAN 2.1), server-side only: the server key and the user's token never reach the
 * browser. Nothing here is cached: every answer belongs to one person. The visitor's IP (clientIp.ts) is forwarded
 * in X-Client-Ip for core's rate limits (core trusts it only on keyed requests).
 */

export type FieldErrors = Record<string, string>;

export type CoreResult<T> =
  | { ok: true; status: number; data: T }
  | { ok: false; status: number; message: string | null; errors: FieldErrors };

export type Account = {
  name: string;
  email: string;
  phone: string;
  locale: Locale;
  email_verified: boolean;
  has_budget: boolean;
  favorites_count: number;
};

export type Budget = {
  cash_available: number;
  max_installment: number;
  installment_frequency: string;
  main_types: string[];
  areas: string[];
  compounds: string[];
  target_price: number | null;
  min_bedrooms: number | null;
  delivery_pref: string;
  updated_at: string | null;
};

export type Choice = { code: string; label: string };

async function clientIp(): Promise<string | null> {
  return clientIpFrom((await headers()).get("x-forwarded-for"));
}

/** One call to core. `auth` sends the session token; a 401 then means the session is gone. */
export async function core<T>(
  method: "GET" | "POST" | "PUT" | "DELETE",
  path: string,
  locale: Locale,
  options: { body?: unknown; auth?: boolean; token?: string } = {},
): Promise<CoreResult<T>> {
  const { base, key } = coreConfig();
  const requestHeaders: Record<string, string> = { "X-Api-Key": key, Accept: "application/json" };
  const ip = await clientIp();
  if (ip) {
    requestHeaders["X-Client-Ip"] = ip;
  }
  const token = options.token ?? (options.auth ? await sessionToken() : null);
  if (options.auth || options.token) {
    if (!token) {
      return { ok: false, status: 401, message: null, errors: {} };
    }
    requestHeaders.Authorization = `Bearer ${token}`;
  }
  if (options.body !== undefined) {
    requestHeaders["Content-Type"] = "application/json";
  }

  const response = await fetch(coreUrl(base, path, locale), {
    method,
    headers: requestHeaders,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    cache: "no-store",
  });

  const json: unknown = response.status === 204 ? null : await response.json().catch(() => null);
  if (response.ok) {
    return { ok: true, status: response.status, data: json as T };
  }

  const body = (json ?? {}) as { message?: unknown; errors?: Record<string, unknown> };
  const errors: FieldErrors = {};
  for (const [field, messages] of Object.entries(body.errors ?? {})) {
    if (Array.isArray(messages) && typeof messages[0] === "string") {
      errors[field] = messages[0];
    }
  }

  return { ok: false, status: response.status, message: typeof body.message === "string" ? body.message : null, errors };
}

/** The signed-in account, or null when there is no valid session. */
export async function currentAccount(locale: Locale): Promise<Account | null> {
  const result = await core<{ data: Account }>("GET", "me", locale, { auth: true });

  return result.ok ? result.data.data : null;
}
