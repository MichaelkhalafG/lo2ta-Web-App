/*
 * The visitor's address for core's rate limits (audit H1). X-Forwarded-For is a list the visitor can start: any
 * value they send stays at the front, and every proxy on the way appends the address it saw. Only the entries the
 * trusted proxies appended are facts, so the address is read from the right: with one proxy in front of Next
 * (the host's web server) it is the last entry. Next itself fills the header with the socket address only when no
 * proxy set it (base-server.js), so a direct request in development still has exactly one entry.
 *
 * TRUSTED_PROXY_HOPS is the number of proxies in front of Next (default 1). A list shorter than that did not pass
 * every proxy, and a value that is not an IP address is refused: both give null, and core then counts the request
 * against the Next server's own address, which is stricter, never looser.
 */

const IP_SHAPE = /^[0-9a-fA-F:.]{3,45}$/;

/** The number of proxies in front of Next, from TRUSTED_PROXY_HOPS (1 to 5, default 1). */
export function trustedHops(value: string | undefined = process.env.TRUSTED_PROXY_HOPS): number {
  const hops = Number.parseInt((value ?? "").trim(), 10);

  return Number.isInteger(hops) && hops >= 1 && hops <= 5 ? hops : 1;
}

/** The address the outermost trusted proxy saw, or null when the header cannot be trusted. */
export function clientIpFrom(forwardedFor: string | null | undefined, hops = trustedHops()): string | null {
  if (typeof forwardedFor !== "string" || forwardedFor.length > 1000) {
    return null;
  }
  const entries = forwardedFor.split(",").map((entry) => entry.trim());
  if (entries.length < hops) {
    return null;
  }
  const address = entries[entries.length - hops];

  return IP_SHAPE.test(address) ? address : null;
}
