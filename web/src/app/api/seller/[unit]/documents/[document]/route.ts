import type { NextRequest } from "next/server";
import { forwardFile } from "@/lib/sellerProxy";

/** The seller's own document, streamed from core (owner only; core answers 404 to anyone else). */
export async function GET(request: NextRequest, { params }: RouteContext<"/api/seller/[unit]/documents/[document]">) {
  const { unit, document } = await params;

  return forwardFile(request, unit, "documents", document);
}
