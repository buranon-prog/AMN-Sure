import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getCurrentUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { dateKey, dayRange } from "@/lib/datetime";
import { toDTO } from "@/lib/tracking";

/** One employee's location points for one day (the trail on the map). */
export async function GET(req: NextRequest) {
  const user = await getCurrentUser();
  if (!user) return NextResponse.json({ error: "unauthenticated" }, { status: 401 });
  if (!can.viewTracking(user.role)) return NextResponse.json({ error: "forbidden" }, { status: 403 });

  const employeeId = req.nextUrl.searchParams.get("employee") ?? "";
  const date = req.nextUrl.searchParams.get("date") ?? "";
  const { start, end } = dayRange(/^\d{4}-\d{2}-\d{2}$/.test(date) ? date : dateKey());
  const pings = await prisma.locationPing.findMany({
    where: { employeeId, recordedAt: { gte: start, lt: end } },
    orderBy: { recordedAt: "asc" },
    include: { job: { select: { jobNo: true } } },
    take: 5000,
  });
  return NextResponse.json({ points: pings.map(toDTO) });
}
