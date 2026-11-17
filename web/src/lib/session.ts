import { cookies } from "next/headers";

/*
 * The signed-in user's core token (RUN D10) lives in an httpOnly cookie on the public domain: browser JavaScript
 * never sees it, and only the Next server sends it to core (Authorization: Bearer). Secure in production; SameSite
 * Lax so a link from an e-mail lands signed in. 30 days, like the token itself.
 */

const COOKIE = "lk_session";
const MAX_AGE = 60 * 60 * 24 * 30;

export async function sessionToken(): Promise<string | null> {
  const value = (await cookies()).get(COOKIE)?.value ?? "";

  return /^\d+\|lk_[A-Za-z0-9]+$/.test(value) ? value : null;
}

export async function startSession(token: string): Promise<void> {
  (await cookies()).set(COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: MAX_AGE,
  });
}

export async function endSession(): Promise<void> {
  (await cookies()).delete(COOKIE);
}
