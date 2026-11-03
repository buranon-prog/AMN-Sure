import Link from "next/link";
import type { Prisma } from "@prisma/client";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { JOB_STATUS, JOB_STATUS_LABEL, OPEN_JOB_STATUS, type JobStatus } from "@/lib/constants";
import { dayRange } from "@/lib/datetime";
import { jobListInclude } from "@/lib/jobs";
import { fullName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import JobList from "@/components/JobList";

type Search = { status?: string; q?: string; tech?: string; date?: string };

export default async function JobsPage({ searchParams }: { searchParams: Search }) {
  const user = await requireUser(can.useOffice);
  const status = searchParams.status ?? "OPEN";
  const q = (searchParams.q ?? "").trim();

  const where: Prisma.JobWhereInput = {};
  if (status === "OPEN") where.status = { in: OPEN_JOB_STATUS };
  else if ((JOB_STATUS as readonly string[]).includes(status)) where.status = status;
  if (searchParams.tech) where.assignments = { some: { employeeId: searchParams.tech } };
  if (searchParams.date) {
    const { start, end } = dayRange(searchParams.date);
    where.scheduledAt = { gte: start, lt: end };
  }
  if (q) {
    where.OR = [
      { jobNo: { contains: q } },
      { problem: { contains: q } },
      { machine: { machineCode: { contains: q } } },
      { machine: { serialNumber: { contains: q } } },
      { machine: { site: { customer: { name: { contains: q } } } } },
    ];
  }

  const closed = status === "COMPLETED" || status === "CANCELLED" || status === "ALL";
  const [jobs, counts, techs] = await Promise.all([
    prisma.job.findMany({
      where,
      include: jobListInclude,
      orderBy: closed ? [{ scheduledAt: "desc" }] : [{ scheduledAt: "asc" }],
      take: 300,
    }),
    prisma.job.groupBy({ by: ["status"], _count: true }),
    prisma.employee.findMany({
      where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } },
      orderBy: { firstName: "asc" },
    }),
  ]);

  const count = (s: string) => counts.find((c) => c.status === s)?._count ?? 0;
  const openTotal = OPEN_JOB_STATUS.reduce((n, s) => n + count(s), 0);

  const tabs: { key: string; label: string; n?: number }[] = [
    { key: "OPEN", label: "งานค้างทั้งหมด", n: openTotal },
    ...(["NEW", "ASSIGNED", "IN_PROGRESS", "ON_HOLD", "COMPLETED", "CANCELLED"] as JobStatus[]).map((s) => ({
      key: s,
      label: JOB_STATUS_LABEL[s],
      n: s === "COMPLETED" || s === "CANCELLED" ? undefined : count(s),
    })),
    { key: "ALL", label: "ทั้งหมด" },
  ];

  const qs = (patch: Partial<Search>) => {
    const p = new URLSearchParams();
    const merged = { ...searchParams, ...patch };
    for (const [k, v] of Object.entries(merged)) if (v) p.set(k, v);
    return `/jobs?${p.toString()}`;
  };

  return (
    <div>
      <PageHeader
        title="งานบริการ"
        subtitle={
          can.assignJobs(user.role) && count("NEW") > 0 ? (
            <span className="text-amber-700">มี {count("NEW")} งานที่รอหัวหน้าช่างจ่ายงาน</span>
          ) : (
            "งานทั้งหมดของแผนก Services"
          )
        }
        actions={
          can.manageJobs(user.role) && (
            <Link href="/jobs/new" className="btn btn-primary">
              ➕ ลงงานใหม่
            </Link>
          )
        }
      />

      <div className="-mx-4 mb-4 overflow-x-auto px-4">
        <div className="flex gap-1 border-b border-gray-200">
          {tabs.map((t) => (
            <Link
              key={t.key}
              href={qs({ status: t.key })}
              className={`-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${
                status === t.key ? "border-brand-600 text-brand-800" : "border-transparent text-gray-500 hover:text-gray-800"
              }`}
            >
              {t.label}
              {t.n != null && <span className="ml-1 text-xs text-gray-400">({t.n})</span>}
            </Link>
          ))}
        </div>
      </div>

      <form className="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-4" action="/jobs">
        <input type="hidden" name="status" value={status} />
        <input name="q" defaultValue={q} placeholder="ค้นหา เลขที่งาน / ลูกค้า / รหัสเครื่อง / อาการ" className="input sm:col-span-2" />
        <select name="tech" defaultValue={searchParams.tech ?? ""} className="input">
          <option value="">ช่างทุกคน</option>
          {techs.map((t) => (
            <option key={t.id} value={t.id}>
              {fullName(t)}
            </option>
          ))}
        </select>
        <div className="flex gap-2">
          <input type="date" name="date" defaultValue={searchParams.date ?? ""} className="input" aria-label="วันที่นัด" />
          <button className="btn btn-secondary">กรอง</button>
        </div>
      </form>

      <JobList jobs={jobs} empty="ไม่พบงานตามเงื่อนไข" />
      {jobs.length === 300 && <p className="mt-2 text-xs text-gray-400">แสดง 300 รายการแรก — ใช้ตัวกรองเพื่อจำกัดผลลัพธ์</p>}
    </div>
  );
}
