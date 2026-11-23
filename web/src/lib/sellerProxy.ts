import { NextResponse, type NextRequest } from "next/server";
import { clientIpFrom } from "./clientIp";
import { coreConfig } from "./coreConfig";
import { sessionToken } from "./session";
import { UNIT_ID } from "./sell";

/*
 * The browser's only path to core for files (PLAN 2.2.15). An upload or a download is forwarded to core with the
 * server key and the seller's token from the httpOnly cookie; core decides type, size, ownership and limits.
 * Checks here are a first gate only: same-origin requests, IDs in the ULID shape, one file part of at most the core
 * limit plus margin. Responses pass through with core's safety headers; nothing is cached.
 */

const MAX_BODY = 12 * 1024 * 1024;

function json(status: number, body: unknown): NextResponse {
  return NextResponse.json(body, { status, headers: { "Cache-Control": "no-store" } });
}

/** Same-origin only: a page on another site cannot make the seller's browser upload or fetch through us. */
function sameOrigin(request: NextRequest): boolean {
  const site = request.headers.get("sec-fetch-site");
  if (site !== null) {
    return site === "same-origin";
  }
  const origin = request.headers.get("origin");

  return origin === null || origin === request.nextUrl.origin;
}

function localeOf(request: NextRequest): "ar" | "en" {
  return request.nextUrl.searchParams.get("locale") === "en" ? "en" : "ar";
}

async function headersFor(request: NextRequest): Promise<Record<string, string> | null> {
  const token = await sessionToken();
  if (!token) {
    return null;
  }
  const { key } = coreConfig();
  const headers: Record<string, string> = { "X-Api-Key": key, Accept: "application/json", Authorization: `Bearer ${token}` };
  const ip = clientIpFrom(request.headers.get("x-forwarded-for"));
  if (ip) {
    headers["X-Client-Ip"] = ip;
  }

  return headers;
}

/** POST a file to core: documents take a type, photos a kind and the project-image tick. */
export async function forwardUpload(request: NextRequest, unit: string, target: "documents" | "photos", fields: string[]): Promise<NextResponse> {
  if (!sameOrigin(request)) {
    return json(403, { message: null });
  }
  if (!UNIT_ID.test(unit)) {
    return json(404, { message: null });
  }
  const length = Number(request.headers.get("content-length") ?? "0");
  if (!Number.isFinite(length) || length <= 0 || length > MAX_BODY) {
    return json(413, { message: null, reason: "file_too_large" });
  }
  const headers = await headersFor(request);
  if (!headers) {
    return json(401, { message: null });
  }

  let incoming: FormData;
  try {
    incoming = await request.formData();
  } catch {
    return json(400, { message: null, reason: "upload_failed" });
  }
  const file = incoming.get("file");
  if (!(file instanceof File)) {
    return json(422, { message: null, reason: "upload_failed" });
  }
  const outgoing = new FormData();
  for (const field of fields) {
    const value = incoming.get(field);
    if (typeof value === "string" && /^[a-z0-9_]{1,32}$/.test(value)) {
      outgoing.set(field, value);
    }
  }
  // The file name is only for display in core; it never becomes a path.
  outgoing.set("file", file, file.name.slice(0, 120) || "file");

  const { base } = coreConfig();
  const response = await fetch(`${base}/api/v1/me/units/${unit}/${target}?locale=${localeOf(request)}`, {
    method: "POST",
    headers,
    body: outgoing,
    cache: "no-store",
  });
  const body: unknown = await response.json().catch(() => ({ message: null }));

  return json(response.status, body);
}

/** GET a private file from core and stream it back with core's safety headers. */
export async function forwardFile(request: NextRequest, unit: string, target: "documents" | "photos" | "exit-report", id: string | null): Promise<NextResponse> {
  if (!sameOrigin(request)) {
    return new NextResponse(null, { status: 403 });
  }
  if (!UNIT_ID.test(unit) || (id !== null && !UNIT_ID.test(id))) {
    return new NextResponse(null, { status: 404 });
  }
  const headers = await headersFor(request);
  if (!headers) {
    return new NextResponse(null, { status: 401 });
  }
  const { base } = coreConfig();
  const path = id === null ? `${target}?locale=${localeOf(request)}` : `${target}/${id}`;
  const response = await fetch(`${base}/api/v1/me/units/${unit}/${path}`, { headers, cache: "no-store" });
  if (!response.ok || response.body === null) {
    return new NextResponse(null, { status: response.status === 401 ? 401 : 404 });
  }

  const pass: Record<string, string> = {};
  for (const name of ["content-type", "content-length", "content-disposition", "x-content-type-options", "content-security-policy", "cross-origin-resource-policy", "referrer-policy", "x-frame-options"]) {
    const value = response.headers.get(name);
    if (value !== null) {
      pass[name] = value;
    }
  }
  pass["cache-control"] = "private, no-store, max-age=0";

  return new NextResponse(response.body, { status: 200, headers: pass });
}
