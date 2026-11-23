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
