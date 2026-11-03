"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";
import { JOB_TYPE, PRIORITY } from "@/lib/constants";
import { formatDateTime, parseLocalDateTime } from "@/lib/datetime";
import { jobBrief, logEvent, nextJobNo } from "@/lib/jobs";
import { notify, supervisorIds } from "@/lib/notify";
import { fullName } from "@/lib/people";
import { oneOf, optNum, optStr, required, str } from "@/lib/form";

function readJobFields(fd: FormData) {
  const scheduledAt = parseLocalDateTime(str(fd, "scheduledAt"));
  if (!scheduledAt) throw new UserError("กรุณาระบุวันและเวลานัดเข้าหน้างาน");
  const estimatedHours = optNum(fd, "estimatedHours");
  if (estimatedHours != null && (estimatedHours < 0 || estimatedHours > 200)) {
    throw new UserError("ชั่วโมงงานโดยประมาณไม่ถูกต้อง");
  }
  return {
    type: oneOf(str(fd, "type") || "REPAIR", JOB_TYPE, "ประเภทงาน"),
    priority: oneOf(str(fd, "priority") || "NORMAL", PRIORITY, "ความเร่งด่วน"),
    problem: required(fd, "problem", "อาการ / งานที่ต้องทำ"),
    reporterName: optStr(fd, "reporterName"),
    reporterPhone: optStr(fd, "reporterPhone"),
    estimatedHours,
    officeNotes: optStr(fd, "officeNotes"),
    scheduledAt,
  };
}

function touch(jobId?: string) {
  revalidatePath("/jobs");
  revalidatePath("/dashboard");
  revalidatePath("/m", "layout");
  if (jobId) revalidatePath(`/jobs/${jobId}`);
}

/** แอดมินลงงาน: ลูกค้า/เครื่อง + เข้าที่ไหน กี่โมง */
export async function createJobAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  let createdId: string | null = null;
  let createdBy = "";
  const res = await run(async () => {
    const user = await actionUser(can.manageJobs);
    createdBy = user.id;
    const machineId = required(fd, "machineId", "เครื่องของลูกค้า");
    const fields = readJobFields(fd);

    const machine = await prisma.machine.findUnique({ where: { id: machineId } });
    if (!machine) throw new UserError("ไม่พบเครื่องที่เลือก");
    if (machine.status !== "ACTIVE") throw new UserError("เครื่องนี้ถูกตั้งเป็นเลิกใช้งานแล้ว");

    // Job numbers are sequential per month; retry if two people save at once.
    for (let attempt = 0; attempt < 3 && !createdId; attempt++) {
      try {
        const job = await prisma.$transaction(async (tx) => {
          const jobNo = await nextJobNo(tx);
          const created = await tx.job.create({ data: { ...fields, jobNo, machineId, createdById: user.id } });
          await logEvent(tx, created.id, user.id, "CREATED", `ลงงาน นัดเข้า ${formatDateTime(fields.scheduledAt)}`);
          return created;
        });
        createdId = job.id;
      } catch (e) {
        if ((e as { code?: string }).code !== "P2002" || attempt === 2) throw e;
      }
    }
  });
  if (res.error || !createdId) return res;
  touch();
  const b = await jobBrief(createdId);
  await notify(
    await supervisorIds(),
    {
      type: "JOB_NEW",
      title: `งานใหม่รอจ่ายงาน ${b.jobNo}`,
      body: `${b.where} · ${b.model} · นัด ${formatDateTime(b.scheduledAt)}`,
      url: `/jobs/${createdId}`,
      jobId: createdId,
    },
    createdBy
  );
  redirect(`/jobs/${createdId}?created=1`);
}

async function loadEditableJob(jobId: string) {
  const job = await prisma.job.findUnique({ where: { id: jobId } });
  if (!job) throw new UserError("ไม่พบงานนี้");
  if (job.status === "COMPLETED" || job.status === "CANCELLED") {
    throw new UserError("งานนี้ปิดแล้ว ต้องเปิดงานใหม่ก่อนจึงจะแก้ไขได้");
  }
  return job;
}

export async function updateJobAction(jobId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  let movedTo: Date | null = null;
  let actor = "";
  const res = await run(async () => {
    const user = await actionUser(can.manageJobs);
    const job = await loadEditableJob(jobId);
    const fields = readJobFields(fd);
    const moved = fields.scheduledAt.getTime() !== job.scheduledAt.getTime();
    if (moved) movedTo = fields.scheduledAt;
    actor = user.id;

    await prisma.$transaction(async (tx) => {
      await tx.job.update({ where: { id: jobId }, data: fields });
      await logEvent(
        tx,
        jobId,
        user.id,
        moved ? "RESCHEDULED" : "UPDATED",
        moved
          ? `เลื่อนนัดจาก ${formatDateTime(job.scheduledAt)} เป็น ${formatDateTime(fields.scheduledAt)}`
          : "แก้ไขรายละเอียดงาน"
      );
    });
    return { ok: true, message: "บันทึกการแก้ไขแล้ว" };
  });
  if (res.ok) {
    touch(jobId);
    if (movedTo) {
      const b = await jobBrief(jobId);
      await notify(
        b.techIds,
        {
          type: "JOB_RESCHEDULED",
          title: `เลื่อนนัด ${b.jobNo}`,
          body: `${b.where} · นัดใหม่ ${formatDateTime(movedTo)}`,
          url: `/m/jobs/${jobId}`,
          jobId,
        },
        actor
      );
    }
  }
  return res;
}

export async function cancelJobAction(jobId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.manageJobs);
    await loadEditableJob(jobId);
    const reason = required(fd, "cancelReason", "เหตุผลที่ยกเลิก");
    const now = new Date();
    await prisma.$transaction(async (tx) => {
      await tx.job.update({ where: { id: jobId }, data: { status: "CANCELLED", cancelReason: reason } });
      await tx.jobVisit.updateMany({ where: { jobId, outcome: null, checkOutAt: null }, data: { checkOutAt: now } });
      await logEvent(tx, jobId, user.id, "CANCELLED", `ยกเลิกงาน: ${reason}`);
    });
    const b = await jobBrief(jobId);
    await notify(
      b.techIds,
      { type: "JOB_CANCELLED", title: `ยกเลิกงาน ${b.jobNo}`, body: `${b.where} — ${reason}`, url: `/m/jobs/${jobId}`, jobId },
      user.id
    );
  });
  if (res.ok) touch(jobId);
  return res;
}

export async function reopenJobAction(jobId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.manageJobs);
    const job = await prisma.job.findUnique({ where: { id: jobId }, include: { _count: { select: { assignments: true } } } });
    if (!job) throw new UserError("ไม่พบงานนี้");
    if (job.status !== "COMPLETED" && job.status !== "CANCELLED") throw new UserError("งานนี้ยังไม่ได้ปิด");
    const reason = required(fd, "reason", "เหตุผลที่เปิดงานใหม่");
    const scheduledAt = parseLocalDateTime(str(fd, "scheduledAt")) ?? job.scheduledAt;
    await prisma.$transaction(async (tx) => {
      await tx.job.update({
        where: { id: jobId },
        data: {
          status: job._count.assignments > 0 ? "ASSIGNED" : "NEW",
          completedAt: null,
          cancelReason: null,
          scheduledAt,
        },
      });
      await logEvent(tx, jobId, user.id, "REOPENED", `เปิดงานใหม่: ${reason} (นัด ${formatDateTime(scheduledAt)})`);
    });
    const b = await jobBrief(jobId);
    await notify(
      b.techIds,
      {
        type: "JOB_ASSIGNED",
        title: `เปิดงานใหม่ ${b.jobNo}`,
        body: `${b.where} · ${reason} · นัด ${formatDateTime(scheduledAt)}`,
        url: `/m/jobs/${jobId}`,
        jobId,
      },
      user.id
    );
  });
  if (res.ok) touch(jobId);
  return res;
}

// ---------- จ่ายงาน (หัวหน้าช่าง) ----------

export async function assignAction(jobId: string, employeeId: string, _prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.assignJobs);
    const job = await loadEditableJob(jobId);
    const tech = await prisma.employee.findUnique({ where: { id: employeeId } });
    if (!tech || !tech.active || !can.doFieldWork(tech.role)) throw new UserError("พนักงานคนนี้รับงานภาคสนามไม่ได้");

    await prisma.$transaction(async (tx) => {
      const existing = await tx.jobAssignment.count({ where: { jobId } });
      await tx.jobAssignment.create({
        data: { jobId, employeeId, assignedById: user.id, isLead: existing === 0 },
      });
      await tx.job.update({
        where: { id: jobId },
        data: {
          status: job.status === "NEW" ? "ASSIGNED" : job.status,
          assignedById: user.id,
          assignedAt: new Date(),
        },
      });
      await logEvent(tx, jobId, user.id, "ASSIGNED", `จ่ายงานให้ ${fullName(tech)}${existing === 0 ? " (หัวหน้างาน)" : ""}`);
    });
    const b = await jobBrief(jobId);
    await notify([employeeId], {
      type: "JOB_ASSIGNED",
      title: `ได้รับงานใหม่ ${b.jobNo}`,
      body: `${b.where} · ${b.model} · นัด ${formatDateTime(b.scheduledAt)}`,
      url: `/m/jobs/${jobId}`,
      jobId,
    });
  });
  if (res.ok) touch(jobId);
  return res;
}

export async function unassignAction(jobId: string, employeeId: string, _prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.assignJobs);
    const job = await loadEditableJob(jobId);
    const assignment = await prisma.jobAssignment.findUnique({
      where: { jobId_employeeId: { jobId, employeeId } },
      include: { employee: true },
    });
    if (!assignment) throw new UserError("ช่างคนนี้ไม่ได้อยู่ในงานนี้");
    const onSite = await prisma.jobVisit.count({ where: { jobId, employeeId, outcome: null, checkOutAt: null } });
    if (onSite > 0) throw new UserError("ช่างคนนี้กำลังอยู่หน้างาน (เช็คอินแล้ว) ถอนออกไม่ได้จนกว่าจะส่งรายงาน");

    await prisma.$transaction(async (tx) => {
      await tx.jobAssignment.delete({ where: { id: assignment.id } });
      const remaining = await tx.jobAssignment.findMany({ where: { jobId }, orderBy: { assignedAt: "asc" } });
      if (assignment.isLead && remaining.length > 0) {
        await tx.jobAssignment.update({ where: { id: remaining[0].id }, data: { isLead: true } });
      }
      if (remaining.length === 0 && job.status === "ASSIGNED") {
        await tx.job.update({ where: { id: jobId }, data: { status: "NEW" } });
      }
      await logEvent(tx, jobId, user.id, "UNASSIGNED", `ถอน ${fullName(assignment.employee)} ออกจากงาน`);
    });
    await notify([employeeId], {
      type: "JOB_UNASSIGNED",
      title: `ถอนออกจากงาน ${job.jobNo}`,
      body: "หัวหน้าช่างได้ถอนคุณออกจากงานนี้แล้ว",
      url: "/m",
    });
  });
  if (res.ok) touch(jobId);
  return res;
}

export async function setLeadAction(jobId: string, employeeId: string, _prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.assignJobs);
    await loadEditableJob(jobId);
    await prisma.$transaction([
      prisma.jobAssignment.updateMany({ where: { jobId }, data: { isLead: false } }),
      prisma.jobAssignment.update({ where: { jobId_employeeId: { jobId, employeeId } }, data: { isLead: true } }),
    ]);
  });
  if (res.ok) touch(jobId);
  return res;
}
