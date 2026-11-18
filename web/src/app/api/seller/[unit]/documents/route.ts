import type { NextRequest } from "next/server";
import { forwardUpload } from "@/lib/sellerProxy";

/** Step 4 upload, forwarded to core (PLAN 2.2.15). */
export async function POST(request: NextRequest, { params }: RouteContext<"/api/seller/[unit]/documents">) {
  const { unit } = await params;

  return forwardUpload(request, unit, "documents", ["type"]);
}
