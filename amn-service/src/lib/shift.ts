import type { Prisma } from "@prisma/client";
import { prisma } from "@/lib/prisma";
import { TRACKING_END, TRACKING_START } from "@/lib/config";
import { dateKey, parseLocalDateTime, timeKey } from "@/lib/datetime";

type Db = Prisma.TransactionClient | typeof prisma;

export function withinTrackingWindow(at: Date = new Date()) {
  const t = timeKey(at);
  return t >= TRACKING_START && t < TRACKING_END;
}

/**
 * The employee's open shift for today. A shift left open from an earlier
 * day (forgot to press "เลิกงาน") is closed automatically at the end of
 * that day's tracking window, so it can never keep tracking overnight.
 */
export async function getOpenShift(employeeId: string, db: Db = prisma) {
  const open = await db.shift.findFirst({
    where: { employeeId, endAt: null },
    orderBy: { startAt: "desc" },
  });
  if (!open) return null;
  if (open.workDate === dateKey()) return open;

  const closeAt = parseLocalDateTime(`${open.workDate}T${TRACKING_END}`) ?? new Date();
  await db.shift.update({
    where: { id: open.id },
    data: { endAt: closeAt < open.startAt ? open.startAt : closeAt, autoClosed: true },
  });
  return null;
}

export async function recordPing(
  db: Db,
  data: {
    employeeId: string;
    latitude: number | null;
    longitude: number | null;
    accuracy?: number | null;
    source: string;
    shiftId?: string | null;
    jobId?: string | null;
  }
) {
  if (data.latitude == null || data.longitude == null) return null;
  return db.locationPing.create({
    data: {
      employeeId: data.employeeId,
      latitude: data.latitude,
      longitude: data.longitude,
      accuracy: data.accuracy ?? null,
      source: data.source,
      shiftId: data.shiftId ?? null,
      jobId: data.jobId ?? null,
    },
  });
}
