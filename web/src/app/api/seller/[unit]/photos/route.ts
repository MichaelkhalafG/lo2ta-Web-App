import type { NextRequest } from "next/server";
import { forwardUpload } from "@/lib/sellerProxy";

/** Step 5 upload, forwarded to core (PLAN 2.2.15). */
export async function POST(request: NextRequest, { params }: RouteContext<"/api/seller/[unit]/photos">) {
  const { unit } = await params;

  return forwardUpload(request, unit, "photos", ["kind", "is_project_image"]);
}
