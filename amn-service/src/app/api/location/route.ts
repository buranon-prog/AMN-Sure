import { NextRequest, NextResponse } from "next/server";
import { z } from "zod";
import { getCurrentUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { ingestPoints } from "@/lib/tracking";

const Body = z.object({
  lat: z.number(),
  lng: z.number(),
  accuracy: z.number().nullish(),
  speedKmh: z.number().nullish(),
  heading: z.number().nullish(),
  battery: z.number().nullish(),
  at: z.string().datetime().nullish(),
});

/**
 * Real-time location from the AMN Service web app on a technician's phone.
 * Stored ONLY while the technician has an open shift and inside the
 * tracking window — never outside working time.
 */
export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ accepted: false, reason: "UNAUTHENTICATED" }, { status: 401 });
  if (!can.doFieldWork(user.role)) return NextResponse.json({ accepted: false, reason: "FORBIDDEN" }, { status: 403 });

  const parsed = Body.safeParse(await req.json().catch(() => null));
  if (!parsed.success) return NextResponse.json({ accepted: false, reason: "BAD_REQUEST" }, { status: 400 });
  const { at, ...p } = parsed.data;

  const res = await ingestPoints(user.id, [{ ...p, at: at ? new Date(at) : null }], "TRACKING");
  return NextResponse.json({ accepted: res.accepted > 0, reason: res.reason });
}
