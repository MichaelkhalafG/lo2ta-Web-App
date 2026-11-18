import type { NextRequest } from "next/server";
import { forwardFile } from "@/lib/sellerProxy";

/** The seller's own photo (the private re-encoded copy), streamed from core for the wizard's thumbnails. */
export async function GET(request: NextRequest, { params }: RouteContext<"/api/seller/[unit]/photos/[photo]">) {
  const { unit, photo } = await params;

  return forwardFile(request, unit, "photos", photo);
}
