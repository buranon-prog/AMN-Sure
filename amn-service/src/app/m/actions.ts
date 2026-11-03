"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";
import { WORKABLE_JOB_STATUS, type JobStatus } from "@/lib/constants";
import { formatDateTime, parseLocalDateTime } from "@/lib/datetime";
import { distanceMeters, formatDistance, parseCoord } from "@/lib/geo";
import { jobBrief, logEvent } from "@/lib/jobs";
import { notify, supervisorIds } from "@/lib/notify";
import { shortName } from "@/lib/people";
import { oneOf, optNum, optStr, str } from "@/lib/form";

// The phone's location is recorded at exactly two moments, both pressed by
// the technician: "ถึงหน้างานแล้ว" and "เสร็จแล้ว / Hold". Nothing in between.

function coords(fd: FormData) {
  const lat = parseCoord(fd.get("lat"), "lat");
  const lng = parseCoord(fd.get("lng"), "lng");
  const ok = lat != null && lng != null;
  return { lat: ok ? lat : null, lng: ok ? lng : null, accuracy: ok ? optNum(fd, "accuracy") : null };
}

function distanceToSite(c: { lat: number | null; lng: number | null }, site: { latitude: number | null; longitude: number | null }) {
  return c.lat != null && c.lng != null && site.latitude != null && site.longitude != null
    ? distanceMeters(c.lat, c.lng, site.latitude, site.longitude)
    : null;
}

function touch(jobId?: string) {
  revalidatePath("/m", "layout");
  revalidatePath("/jobs");
  revalidatePath("/dashboard");
  revalidatePath("/tracking");
  revalidatePath("/technicians");
  if (jobId) revalidatePath(`/jobs/${jobId}`);
}

async function loadMyJob(jobId: string, employeeId: string) {
  const job = await prisma.job.findFirst({
    where: { id: jobId, assignments: { some: { employeeId } } },
    include: { machine: { include: { site: true } } },
  });
  if (!job) throw new UserError("ไม่พบงานนี้ในรายการงานของคุณ");
  return job;
}

/** ถึงหน้างานแล้ว — records arrival time + the phone's location, and tells the office. */
export async function checkInAction(jobId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    const job = await loadMyJob(jobId, user.id);
    if (!WORKABLE_JOB_STATUS.includes(job.status as JobStatus)) throw new UserError("งานนี้ปิดไปแล้ว");

    const open = await prisma.jobVisit.findFirst({
      where: { employeeId: user.id, outcome: null, checkOutAt: null },
      include: { job: true },
    });
    if (open) {
      throw new UserError(
        open.jobId === jobId ? "คุณกดถึงหน้างานนี้ไปแล้ว" : `คุณยังอยู่ที่งาน ${open.job.jobNo} — กดเสร็จ/Hold งานนั้นก่อน`
      );
    }

    const c = coords(fd);
    const distance = distanceToSite(c, job.machine.site);
    await prisma.$transaction(async (tx) => {
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
        c.lat == null ? "ถึงหน้างาน (ไม่มีพิกัด GPS)" : `ถึงหน้างาน${distance != null ? ` ห่างจากจุดหมาย ${formatDistance(distance)}` : ""}`
      );
    });

    const b = await jobBrief(jobId);
    await notify(
      b.officeIds,
      {
        type: "JOB_ARRIVED",
        title: `ช่างถึงหน้างานแล้ว ${b.jobNo}`,
        body: `${shortName(user)} · ${b.where}${
          c.lat == null ? " · ไม่มีพิกัด" : distance != null ? ` · ห่างจุดหมาย ${formatDistance(distance)}` : ""
        }`,
        url: `/jobs/${jobId}`,
        jobId,
      },
      user.id
    );
  });
  if (res.ok) touch(jobId);
  return res;
}

/**
 * เสร็จแล้ว / Hold — records the summary, finish time and the phone's location:
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
    if (!visit) throw new UserError("กรุณากด “ถึงหน้างานแล้ว” ก่อนเขียนสรุปงาน");

    const outcome = oneOf(str(fd, "outcome"), ["HOLD", "COMPLETED"] as const, "ผลการทำงาน");
    const summary = str(fd, "summary");
    if (summary.length < 5) throw new UserError("กรุณาเขียนสรุปงานที่ทำ (อย่างน้อย 5 ตัวอักษร)");
    const partsUsed = optStr(fd, "partsUsed");
    const holdReason = optStr(fd, "holdReason");
    const nextVisitAt = parseLocalDateTime(str(fd, "nextVisitAt"));
    if (outcome === "HOLD" && !holdReason) throw new UserError("กรุณาระบุเหตุที่ยังไม่เสร็จ (เช่น รออะไหล่)");
    if (outcome === "HOLD" && nextVisitAt && nextVisitAt < new Date()) throw new UserError("วันนัดครั้งถัดไปต้องเป็นเวลาในอนาคต");

    const c = coords(fd);
    const distance = distanceToSite(c, job.machine.site);
    const now = new Date();
    await prisma.$transaction(async (tx) => {
      await tx.jobVisit.update({
        where: { id: visit.id },
        data: {
          checkOutAt: now,
          checkOutLat: c.lat,
          checkOutLng: c.lng,
          checkOutAccuracy: c.accuracy,
          checkOutDistanceM: distance,
          summary,
          partsUsed,
          outcome,
          holdReason: outcome === "HOLD" ? holdReason : null,
          nextVisitAt: outcome === "HOLD" ? nextVisitAt : null,
        },
      });
      if (outcome === "COMPLETED") {
        await tx.job.update({ where: { id: jobId }, data: { status: "COMPLETED", completedAt: now, resolution: summary } });
        // Anyone else still on site for this job is closed off with it.
        await tx.jobVisit.updateMany({ where: { jobId, outcome: null, checkOutAt: null }, data: { checkOutAt: now } });
        await logEvent(tx, jobId, user.id, "COMPLETED", `ส่งงาน — งานเสร็จเรียบร้อย${c.lat == null ? " (ไม่มีพิกัด GPS)" : ""}`);
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
