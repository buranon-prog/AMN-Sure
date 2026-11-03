import { createHash, randomBytes } from "crypto";
import { prisma } from "@/lib/prisma";
import { LOCATION_RETENTION_DAYS, PING_MIN_GAP_MS } from "@/lib/config";
import { dateKey } from "@/lib/datetime";
import { getOpenShift, withinTrackingWindow } from "@/lib/shift";

export type TrackPoint = {
  lat: number;
  lng: number;
  accuracy?: number | null;
  speedKmh?: number | null;
  heading?: number | null;
  battery?: number | null;
  /** When the phone took the fix; defaults to now. */
  at?: Date | null;
};

export type IngestResult = { accepted: number; reason?: "NO_SHIFT" | "OUTSIDE_HOURS" | "TOO_SOON" | "INVALID" };

const num = (v: unknown) => {
  if (v === null || v === undefined || v === "") return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

function clean(p: TrackPoint): TrackPoint | null {
  const lat = num(p.lat);
  const lng = num(p.lng);
  if (lat == null || lng == null || Math.abs(lat) > 90 || Math.abs(lng) > 180 || (lat === 0 && lng === 0)) return null;
  const accuracy = num(p.accuracy);
  if (accuracy != null && accuracy > 5000) return null; // cell-tower guesses are useless on a map
  const speed = num(p.speedKmh);
  const heading = num(p.heading);
  const battery = num(p.battery);
  return {
    lat,
    lng,
    accuracy: accuracy != null && accuracy >= 0 ? accuracy : null,
    speedKmh: speed != null && speed >= 0 && speed < 400 ? speed : null,
    heading: heading != null && heading >= 0 && heading <= 360 ? heading : null,
    battery: battery != null && battery >= 0 && battery <= 100 ? battery : null,
    at: p.at && !Number.isNaN(p.at.getTime()) ? p.at : null,
  };
}

/**
 * Stores location points for an employee — ONLY while they have an open
 * shift and inside the tracking window. Points are de-duplicated so a
 * burst of buffered points from a background app can't flood the table.
 */
export async function ingestPoints(employeeId: string, raw: TrackPoint[], source: string): Promise<IngestResult> {
  const points = raw.map(clean).filter((p): p is TrackPoint => !!p);
  if (points.length === 0) return { accepted: 0, reason: "INVALID" };

  const shift = await getOpenShift(employeeId);
  if (!shift) return { accepted: 0, reason: "NO_SHIFT" };

  const now = Date.now();
  const last = await prisma.locationPing.findFirst({
    where: { employeeId },
    orderBy: { recordedAt: "desc" },
    select: { recordedAt: true },
  });
  let lastAt = last?.recordedAt.getTime() ?? 0;

  const visit = await prisma.jobVisit.findFirst({
    where: { employeeId, outcome: null, checkOutAt: null },
    select: { jobId: true },
  });

  const rows = [];
  let outside = false;
  for (const p of points.sort((a, b) => (a.at?.getTime() ?? now) - (b.at?.getTime() ?? now))) {
    let at = p.at?.getTime() ?? now;
    if (at > now + 2 * 60_000) at = now; // phone clock ahead: trust the server
    if (at < shift.startAt.getTime() - 60_000) continue; // before this shift began
    if (!withinTrackingWindow(new Date(at)) || dateKey(new Date(at)) !== shift.workDate) {
      outside = true;
      continue;
    }
    if (at - lastAt < PING_MIN_GAP_MS) continue;
    lastAt = at;
    rows.push({
      employeeId,
      shiftId: shift.id,
      jobId: visit?.jobId ?? null,
      latitude: p.lat,
      longitude: p.lng,
      accuracy: p.accuracy ?? null,
      speedKmh: p.speedKmh ?? null,
      heading: p.heading ?? null,
      battery: p.battery ?? null,
      source,
      recordedAt: new Date(at),
    });
  }

  if (rows.length) await prisma.locationPing.createMany({ data: rows });
  return rows.length ? { accepted: rows.length } : { accepted: 0, reason: outside ? "OUTSIDE_HOURS" : "TOO_SOON" };
}

/** Deletes location history older than the retention period. */
export async function purgeOldLocations() {
  const cutoff = new Date(Date.now() - LOCATION_RETENTION_DAYS * 24 * 60 * 60 * 1000);
  await prisma.locationPing.deleteMany({ where: { recordedAt: { lt: cutoff } } });
}

// ---------- Background app tokens ----------

export function hashToken(token: string) {
  return createHash("sha256").update(token).digest("hex");
}

export function newToken() {
  return randomBytes(24).toString("base64url");
}

// ---------- Live view data ----------

export type PingDTO = {
  id: string;
  employeeId: string;
  lat: number;
  lng: number;
  accuracy: number | null;
  speedKmh: number | null;
  heading: number | null;
  battery: number | null;
  source: string;
  recordedAt: string;
  jobNo: string | null;
};

export function toDTO(p: {
  id: string;
  employeeId: string;
  latitude: number;
  longitude: number;
  accuracy: number | null;
  speedKmh: number | null;
  heading: number | null;
  battery: number | null;
  source: string;
  recordedAt: Date;
  job?: { jobNo: string } | null;
}): PingDTO {
  return {
    id: p.id,
    employeeId: p.employeeId,
    lat: p.latitude,
    lng: p.longitude,
    accuracy: p.accuracy,
    speedKmh: p.speedKmh,
    heading: p.heading,
    battery: p.battery,
    source: p.source,
    recordedAt: p.recordedAt.toISOString(),
    jobNo: p.job?.jobNo ?? null,
  };
}

export type FieldStatus = {
  id: string;
  shiftOpen: boolean;
  shiftStart: string | null;
  shiftEnd: string | null;
  visit: { jobId: string; jobNo: string; customer: string; lat: number | null; lng: number | null } | null;
};

/** Shift + on-site status of every field employee for a day (for the live list). */
export async function fieldStatus(date: string, includeVisits: boolean): Promise<FieldStatus[]> {
  const [people, shifts, visits] = await Promise.all([
    prisma.employee.findMany({ where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } }, select: { id: true } }),
    prisma.shift.findMany({ where: { workDate: date }, orderBy: { startAt: "asc" } }),
    includeVisits
      ? prisma.jobVisit.findMany({
          where: { outcome: null, checkOutAt: null },
          include: { job: { include: { machine: { include: { site: { include: { customer: true } } } } } } },
        })
      : [],
  ]);
  return people.map((p) => {
    const mine = shifts.filter((s) => s.employeeId === p.id);
    const v = visits.find((x) => x.employeeId === p.id);
    return {
      id: p.id,
      shiftOpen: mine.some((s) => !s.endAt),
      shiftStart: mine[0]?.startAt.toISOString() ?? null,
      shiftEnd: mine.length && mine.every((s) => s.endAt) ? mine[mine.length - 1].endAt!.toISOString() : null,
      visit: v
        ? {
            jobId: v.jobId,
            jobNo: v.job.jobNo,
            customer: v.job.machine.site.customer.name,
            lat: v.job.machine.site.latitude,
            lng: v.job.machine.site.longitude,
          }
        : null,
    };
  });
}
