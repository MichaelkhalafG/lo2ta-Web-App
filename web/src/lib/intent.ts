/*
 * Return-to-action (PLAN 2.1): a visitor who must sign in for an action (save a favorite, register a budget, start
 * selling) comes back to where they were, and the action completes. `next` is a path on this site; `intent` names
 * the action. Both arrive from the address bar, so both are checked here and nothing else is accepted.
 */

export type Intent = { kind: "favorite"; code: string } | { kind: "budget" } | { kind: "sell" };

/** A path on this site, never another origin ("//evil", "https://…", "\\…"). Defaults to the account page. */
export function safeNext(value: unknown, fallback = "/account"): string {
  if (typeof value !== "string" || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) {
    return fallback;
  }
  if (value.length > 300 || /[\u0000-\u001f]/.test(value)) {
    return fallback;
  }

  return value;
}

export function parseIntent(value: unknown): Intent | null {
  if (typeof value !== "string") {
    return null;
  }
  if (value === "budget" || value === "sell") {
    return { kind: value };
  }
  const favorite = /^favorite:([A-Za-z0-9-]{3,16})$/.exec(value);

  return favorite ? { kind: "favorite", code: favorite[1]!.toUpperCase() } : null;
}

/** Where an intent leads after sign-in when it has its own page. */
export function intentPath(intent: Intent | null, next: string): string {
  if (intent?.kind === "budget") {
    return "/account/budget";
  }
  if (intent?.kind === "sell") {
    return "/sell";
  }

  return next;
}

/** Query string carrying next and intent to the sign-in pages. */
export function returnQuery(next: string, intent?: string | null): string {
  const params = new URLSearchParams({ next: safeNext(next) });
  if (intent) {
    params.set("intent", intent);
  }

  return params.toString();
}
