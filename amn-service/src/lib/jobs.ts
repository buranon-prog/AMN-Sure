import type { Prisma } from "@prisma/client";
import { prisma } from "@/lib/prisma";
import { OPEN_JOB_STATUS } from "@/lib/constants";
import { dateKey, dayRange, monthKey } from "@/lib/datetime";

type Tx = Prisma.TransactionClient;

/** JOB-YYMM-0001, numbered per Bangkok calendar month. */
export async function nextJobNo(tx: Tx, at: Date = new Date()) {
  const prefix = `JOB-${monthKey(at).slice(2).replace("-", "")}-`;
  const last = await tx.job.findFirst({
    where: { jobNo: { startsWith: prefix } },
    orderBy: { jobNo: "desc" },
    select: { jobNo: true },
  });
  const n = last ? Number(last.jobNo.slice(prefix.length)) + 1 : 1;
  return `${prefix}${String(n).padStart(4, "0")}`;
}

export function logEvent(tx: Tx, jobId: string, actorId: string | null, type: string, message: string) {
  return tx.jobEvent.create({ data: { jobId, actorId, type, message } });
}

export type Candidate = Awaited<ReturnType<typeof rankCandidates>>[number];

/**
 * Technicians ranked for a job — "จ่ายงานตามความถนัด":
 *   1. skill level on this exact machine model (highest first)
 *   2. best skill level on any model in the same category
 *   3. fewest other open jobs on the same day, then fewest open jobs overall
 */
export async function rankCandidates(job: {
  id: string;
  scheduledAt: Date;
  machine: { machineModelId: string; machineModel: { category: string } };
}) {
  const { start, end } = dayRange(dateKey(job.scheduledAt));

  const [people, openAssignments] = await Promise.all([
    prisma.employee.findMany({
      where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } },
      include: { skills: { include: { machineModel: true } } },
      orderBy: { firstName: "asc" },
    }),
    prisma.jobAssignment.findMany({
      where: { jobId: { not: job.id }, job: { status: { in: OPEN_JOB_STATUS } } },
      select: { employeeId: true, job: { select: { jobNo: true, scheduledAt: true } } },
    }),
  ]);

  return people
    .map((p) => {
      const exact = p.skills.find((s) => s.machineModelId === job.machine.machineModelId);
      const sameCategory = p.skills
        .filter((s) => s.machineModel.category === job.machine.machineModel.category)
        .reduce((best, s) => Math.max(best, s.level), 0);
      const mine = openAssignments.filter((a) => a.employeeId === p.id);
      const sameDay = mine
        .filter((a) => a.job.scheduledAt >= start && a.job.scheduledAt < end)
        .map((a) => a.job);
      return {
        employee: p,
        exactLevel: exact?.level ?? 0,
        categoryLevel: sameCategory,
        sameDay,
        openCount: mine.length,
      };
    })
    .sort(
      (a, b) =>
        b.exactLevel - a.exactLevel ||
        b.categoryLevel - a.categoryLevel ||
        a.sameDay.length - b.sameDay.length ||
        a.openCount - b.openCount
    );
}

/** Include shape used by job lists everywhere. */
export const jobListInclude = {
  machine: { include: { machineModel: true, site: { include: { customer: true } } } },
  assignments: { include: { employee: true }, orderBy: [{ isLead: "desc" }, { assignedAt: "asc" }] },
} satisfies Prisma.JobInclude;

export type JobListItem = Prisma.JobGetPayload<{ include: typeof jobListInclude }>;

/** Everything a notification about a job needs to say. */
export async function jobBrief(jobId: string) {
  const job = await prisma.job.findUniqueOrThrow({
    where: { id: jobId },
    include: {
      machine: { include: { machineModel: true, site: { include: { customer: true } } } },
      assignments: { select: { employeeId: true } },
    },
  });
  return {
    jobNo: job.jobNo,
    where: `${job.machine.site.customer.name} (${job.machine.site.name})`,
    model: `${job.machine.machineModel.brand} ${job.machine.machineModel.model}`,
    scheduledAt: job.scheduledAt,
    techIds: job.assignments.map((a) => a.employeeId),
    officeIds: [job.createdById, job.assignedById],
  };
}
