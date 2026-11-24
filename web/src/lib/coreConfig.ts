/*
 * Where core lives and the server key, for the Next server only (no NEXT_PUBLIC_ prefix: neither reaches the
 * browser). A missing setting throws, so a build or a request fails loudly instead of calling nowhere.
 */
export function coreConfig(env: Record<string, string | undefined> = process.env): { base: string; key: string } {
  const base = (env.CORE_API_URL ?? "").trim().replace(/\/+$/, "");
  const key = (env.CORE_API_KEY ?? "").trim();
  if (base === "" || key === "") {
    throw new Error("CORE_API_URL and CORE_API_KEY must be set for the Next server.");
  }

  return { base, key };
}

/**
 * A core API address with the language (second security pass N2). Server actions receive the language as an
 * argument from the browser, so it is never trusted: anything but "en" is Arabic, and nothing can be added to
 * core's query string through it.
 */
export function coreUrl(base: string, path: string, locale: unknown): string {
  const separator = path.includes("?") ? "&" : "?";

  return `${base}/api/v1/${path}${separator}locale=${locale === "en" ? "en" : "ar"}`;
}
