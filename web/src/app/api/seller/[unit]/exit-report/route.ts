import type { NextRequest } from "next/server";
import { forwardFile } from "@/lib/sellerProxy";

/** The unit's exit report PDF in ?locale=, streamed from core (owner only). */
export async function GET(request: NextRequest, { params }: RouteContext<"/api/seller/[unit]/exit-report">) {
  const { unit } = await params;

  return forwardFile(request, unit, "exit-report", null);
}
