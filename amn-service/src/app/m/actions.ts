"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";
import { WORKABLE_JOB_STATUS, type JobStatus } from "@/lib/constants";
import { dateKey, formatDateTime, parseLocalDateTime, timeKey } from "@/lib/datetime";
import { distanceMeters, parseCoord } from "@/lib/geo";
import { jobBrief, logEvent } from "@/lib/jobs";
import { notify, supervisorIds } from "@/lib/notify";
import { shortName } from "@/lib/people";
import { getOpenShift, recordPing } from "@/lib/shift";
import { purgeOldLocations } from "@/lib/tracking";
import { oneOf, optNum, optStr, str } from "@/lib/form";

function coords(fd: FormData) {
  const lat = parseCoord(fd.get("lat"), "lat");
  const lng = parseCoord(fd.get("lng"), "lng");
  const ok = lat != null && lng != null;
  return { lat: ok ? lat : null, lng: ok ? lng : null, accuracy: ok ? optNum(fd, "accuracy") : null };
}

function touch(jobId?: string) {
  revalidatePath("/m", "layout");
  revalidatePath("/jobs");
  revalidatePath("/dashboard");
  revalidatePath("/tracking");
  if (jobId) revalidatePath(`/jobs/${jobId}`);
}

async function myOpenVisit(employeeId: string) {
  return prisma.jobVisit.findFirst({
    where: { employeeId, outcome: null, checkOutAt: null },
    include: { job: true },
    orderBy: { checkInAt: "desc" },
  });
}

/** ลงเวลาเริ่มงาน — starts location sharing for the rest of the shift. */
export async function startShiftAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    if (await getOpenShift(user.id)) throw new UserError("คุณเริ่มงานวันนี้ไปแล้ว");
    const c = coords(fd);
    await prisma.$transaction(async (tx) => {
      const shift = await tx.shift.create({
        data: { employeeId: user.id, workDate: dateKey(), startLat: c.lat, startLng: c.lng },
      });
      await recordPing(tx, { employeeId: user.id, latitude: c.lat, longitude: c.lng, accuracy: c.accuracy, source: "SHIFT_START", shiftId: shift.id });
    });
    await purgeOldLocations().catch((e) => console.error("purge locations", e));
    return { ok: true, message: `เริ่มงานเวลา ${timeKey()} น.` };
  });
  if (res.ok) touch();
  return res;
}

export async function endShiftAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    const shift = await getOpenShift(user.id);
    if (!shift) throw new UserError("ยังไม่ได้เริ่มงาน");
    const visit = await myOpenVisit(user.id);
    if (visit) throw new UserError(`ยังเช็คอินอยู่ที่งาน ${visit.job.jobNo} — กรุณาเขียนสรุปงานก่อนเลิกงาน`);
    const c = coords(fd);
    await prisma.$transaction(async (tx) => {
      await tx.shift.update({ where: { id: shift.id }, data: { endAt: new Date(), endLat: c.lat, endLng: c.lng } });
      await recordPing(tx, { employeeId: user.id, latitude: c.lat, longitude: c.lng, accuracy: c.accuracy, source: "SHIFT_END", shiftId: shift.id });
    });
    return { ok: true, message: `เลิกงานเวลา ${timeKey()} น. — หยุดแชร์ตำแหน่งแล้ว` };
  });
  if (res.ok) touch();
  return res;
}

async function loadMyJob(jobId: string, employeeId: string) {
  const job = await prisma.job.findFirst({
    where: { id: jobId, assignments: { some: { employeeId } } },
    include: { machine: { include: { site: true } } },
  });
  if (!job) throw new UserError("ไม่พบงานนี้ในรายการงานของคุณ");
  return job;
}

/** เช็คอินถึงหน้างาน (with GPS). Starts the shift automatically if needed. */
export async function checkInAction(jobId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    const job = await loadMyJob(jobId, user.id);
    if (!WORKABLE_JOB_STATUS.includes(job.status as JobStatus)) throw new UserError("งานนี้ปิดไปแล้ว เช็คอินไม่ได้");

    const open = await myOpenVisit(user.id);
    if (open) {
      throw new UserError(
        open.jobId === jobId ? "คุณเช็คอินงานนี้อยู่แล้ว" : `คุณยังเช็คอินอยู่ที่งาน ${open.job.jobNo} — ส่งรายงานงานนั้นก่อน`
      );
    }

    const c = coords(fd);
    const { site } = job.machine;
    const distance =
      c.lat != null && c.lng != null && site.latitude != null && site.longitude != null
        ? distanceMeters(c.lat, c.lng, site.latitude, site.longitude)
        : null;

    await prisma.$transaction(async (tx) => {
      let shift = await getOpenShift(user.id, tx);
      if (!shift) {
        shift = await tx.shift.create({
          data: { employeeId: user.id, workDate: dateKey(), startLat: c.lat, startLng: c.lng },
        });
      }
      await tx.jobVisit.create({
        data: {
          jobId,
          employeeId: user.id,
          checkInLat: c.lat,
          checkInLng: c.lng,
          checkInAccuracy: c.accuracy,
          checkInDistanceM: distance,
        },
      });
      await tx.job.update({ where: { id: jobId }, data: { status: "IN_PROGRESS" } });
      await logEvent(
        tx,
        jobId,
        user.id,
        "CHECK_IN",
        c.lat == null ? "เช็คอินหน้างาน (ไม่มีพิกัด GPS)" : `เช็คอินหน้างาน${distance != null ? ` ห่างจากจุดหมาย ${Math.round(distance)} ม.` : ""}`
      );
      await recordPing(tx, { employeeId: user.id, latitude: c.lat, longitude: c.lng, accuracy: c.accuracy, source: "CHECK_IN", shiftId: shift.id, jobId });
    });
  });
  if (res.ok) touch(jobId);
  return res;
}

/**
 * สรุปงานหลังทำงาน:
 *   HOLD      — ยังไม่เสร็จ: job goes ON_HOLD (and moves to the next planned visit, if given)
 *   COMPLETED — ส่งงาน: job is closed with this summary as the resolution
 */
export async function submitReportAction(jobId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  let doneMsg = "";
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    const job = await loadMyJob(jobId, user.id);
    if (!WORKABLE_JOB_STATUS.includes(job.status as JobStatus)) throw new UserError("งานนี้ปิดไปแล้ว");

    const visit = await prisma.jobVisit.findFirst({
      where: { jobId, employeeId: user.id, outcome: null, checkOutAt: null },
      orderBy: { checkInAt: "desc" },
    });
    if (!visit) throw new UserError("กรุณากดเช็คอินถึงหน้างานก่อนเขียนสรุปงาน");

    const outcome = oneOf(str(fd, "outcome"), ["HOLD", "COMPLETED"] as const, "ผลการทำงาน");
    const summary = str(fd, "summary");
    if (summary.length < 5) throw new UserError("กรุณาเขียนสรุปงานที่ทำ (อย่างน้อย 5 ตัวอักษร)");
    const partsUsed = optStr(fd, "partsUsed");
    const holdReason = optStr(fd, "holdReason");
    const nextVisitAt = parseLocalDateTime(str(fd, "nextVisitAt"));
    if (outcome === "HOLD" && !holdReason) throw new UserError("กรุณาระบุเหตุที่ยังไม่เสร็จ (เช่น รออะไหล่)");
    if (outcome === "HOLD" && nextVisitAt && nextVisitAt < new Date()) throw new UserError("วันนัดครั้งถัดไปต้องเป็นเวลาในอนาคต");

    const c = coords(fd);
    const now = new Date();
    await prisma.$transaction(async (tx) => {
      await tx.jobVisit.update({
        where: { id: visit.id },
        data: {
          checkOutAt: now,
          checkOutLat: c.lat,
          checkOutLng: c.lng,
          summary,
          partsUsed,
          outcome,
          holdReason: outcome === "HOLD" ? holdReason : null,
          nextVisitAt: outcome === "HOLD" ? nextVisitAt : null,
        },
      });
      if (outcome === "COMPLETED") {
        await tx.job.update({ where: { id: jobId }, data: { status: "COMPLETED", completedAt: now, resolution: summary } });
        // Anyone else still checked in on this job is closed off with it.
        await tx.jobVisit.updateMany({ where: { jobId, outcome: null, checkOutAt: null }, data: { checkOutAt: now } });
        await logEvent(tx, jobId, user.id, "COMPLETED", "ส่งงาน — งานเสร็จเรียบร้อย");
      } else {
        await tx.job.update({
          where: { id: jobId },
          data: { status: "ON_HOLD", ...(nextVisitAt ? { scheduledAt: nextVisitAt } : {}) },
        });
        await logEvent(
          tx,
          jobId,
          user.id,
          "HOLD",
          `${holdReason}${nextVisitAt ? ` — นัดเข้าครั้งถัดไป ${formatDateTime(nextVisitAt)}` : ""}`
        );
      }
      const shift = await getOpenShift(user.id, tx);
      await recordPing(tx, { employeeId: user.id, latitude: c.lat, longitude: c.lng, accuracy: c.accuracy, source: "CHECK_OUT", shiftId: shift?.id, jobId });
    });
    doneMsg = outcome === "COMPLETED" ? `ส่งงาน ${job.jobNo} เรียบร้อย` : `บันทึก Hold งาน ${job.jobNo} แล้ว`;

    // Tell the office: whoever laid the job, whoever assigned it, and (for holds) the supervisors.
    const b = await jobBrief(jobId);
    await notify(
      [...b.officeIds, ...(outcome === "HOLD" ? await supervisorIds() : [])],
      outcome === "COMPLETED"
        ? { type: "JOB_COMPLETED", title: `ส่งงานแล้ว ${b.jobNo}`, body: `${shortName(user)} · ${b.where}: ${summary.slice(0, 120)}`, url: `/jobs/${jobId}`, jobId }
        : { type: "JOB_HOLD", title: `Hold ${b.jobNo}`, body: `${shortName(user)} · ${b.where}: ${holdReason}`, url: `/jobs/${jobId}`, jobId },
      user.id
    );
  });
  if (res.error) return res;
  touch(jobId);
  redirect(`/m?done=${encodeURIComponent(doneMsg)}`);
}
