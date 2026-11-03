import { NextRequest, NextResponse } from "next/server";
import { z } from "zod";
import { prisma } from "@/lib/prisma";
import { getCurrentUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { PING_MIN_GAP_MS } from "@/lib/config";
import { getOpenShift, recordPing, withinTrackingWindow } from "@/lib/shift";

const Body = z.object({
  lat: z.number().min(-90).max(90),
  lng: z.number().min(-180).max(180),
  accuracy: z.number().min(0).max(100000).nullish(),
});

/**
 * Periodic location from a technician's phone. Accepted ONLY while the
 * technician has an open shift and inside the tracking window — so the
 * company never stores location outside working time.
 */
export async function POST(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ accepted: false, reason: "UNAUTHENTICATED" }, { status: 401 });
  if (!can.doFieldWork(user.role)) return NextResponse.json({ accepted: false, reason: "FORBIDDEN" }, { status: 403 });

  const parsed = Body.safeParse(await req.json().catch(() => null));
  if (!parsed.success) return NextResponse.json({ accepted: false, reason: "BAD_REQUEST" }, { status: 400 });
  const { lat, lng, accuracy } = parsed.data;

  const shift = await getOpenShift(user.id);
  if (!shift) return NextResponse.json({ accepted: false, reason: "NO_SHIFT" });
  if (!withinTrackingWindow()) return NextResponse.json({ accepted: false, reason: "OUTSIDE_HOURS" });
  if (accuracy != null && accuracy > 5000) return NextResponse.json({ accepted: false, reason: "LOW_ACCURACY" });

  const last = await prisma.locationPing.findFirst({
    where: { employeeId: user.id },
    orderBy: { recordedAt: "desc" },
    select: { recordedAt: true },
  });
  if (last && Date.now() - last.recordedAt.getTime() < PING_MIN_GAP_MS) {
    return NextResponse.json({ accepted: false, reason: "TOO_SOON" });
  }

  const visit = await prisma.jobVisit.findFirst({
    where: { employeeId: user.id, outcome: null, checkOutAt: null },
    select: { jobId: true },
  });
  await recordPing(prisma, {
    employeeId: user.id,
    latitude: lat,
    longitude: lng,
    accuracy: accuracy ?? null,
    source: "TRACKING",
    shiftId: shift.id,
    jobId: visit?.jobId ?? null,
  });
  return NextResponse.json({ accepted: true });
}
