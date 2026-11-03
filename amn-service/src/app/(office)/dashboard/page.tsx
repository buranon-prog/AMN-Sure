import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { JOB_TYPE_LABEL, OPEN_JOB_STATUS } from "@/lib/constants";
import { dateKey, dayRange, formatDuration, formatMonth, formatTime, monthKey, monthRange, timeAgo } from "@/lib/datetime";
import { jobListInclude } from "@/lib/jobs";
import { fullName, shortName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import { StatusBadge } from "@/components/Badges";
import { HBars, Legend, StackedBar, StatTile } from "@/components/Charts";

function shiftMonth(key: string, delta: number) {
  const [y, m] = key.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1 + delta, 1)).toISOString().slice(0, 7);
}

export default async function DashboardPage({ searchParams }: { searchParams: { month?: string; denied?: string } }) {
  const user = await requireUser(can.useOffice);
  const month = /^\d{4}-\d{2}$/.test(searchParams.month ?? "") ? searchParams.month! : monthKey();
  const isCurrent = month === monthKey();
  const { start, end } = monthRange(month);
  const today = dayRange(dateKey());

  const [statusCounts, overdue, createdInMonth, completedInMonth, visitsInMonth, techs, openAssignments, todayJobs, holds] =
    await Promise.all([
      prisma.job.groupBy({ by: ["status"], where: { status: { in: OPEN_JOB_STATUS } }, _count: true }),
      prisma.job.count({ where: { status: "ASSIGNED", scheduledAt: { lt: today.start } } }),
      prisma.job.findMany({
        where: { createdAt: { gte: start, lt: end } },
        select: { type: true, machine: { select: { machineModel: { select: { brand: true, model: true } } } } },
      }),
      prisma.job.findMany({
        where: { status: "COMPLETED", completedAt: { gte: start, lt: end } },
        select: { createdAt: true, completedAt: true, _count: { select: { visits: true } }, assignments: { select: { employeeId: true } } },
      }),
      prisma.jobVisit.findMany({
        where: { checkInAt: { gte: start, lt: end } },
        select: { employeeId: true, checkInAt: true, checkOutAt: true },
      }),
      prisma.employee.findMany({ where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } }, orderBy: { firstName: "asc" } }),
      prisma.jobAssignment.findMany({
        where: { job: { status: { in: ["ASSIGNED", "IN_PROGRESS", "ON_HOLD"] } } },
        select: { employeeId: true, job: { select: { status: true } } },
      }),
      prisma.job.findMany({
        where: { scheduledAt: { gte: today.start, lt: today.end }, status: { not: "CANCELLED" } },
        include: jobListInclude,
        orderBy: { scheduledAt: "asc" },
      }),
      prisma.job.findMany({
        where: { status: "ON_HOLD" },
        include: { ...jobListInclude, visits: { orderBy: { checkInAt: "desc" }, take: 1 } },
        orderBy: { updatedAt: "asc" },
        take: 6,
      }),
    ]);

  const count = (s: string) => statusCounts.find((c) => c.status === s)?._count ?? 0;
  const avgMs = completedInMonth.length
    ? completedInMonth.reduce((n, j) => n + (j.completedAt!.getTime() - j.createdAt.getTime()), 0) / completedInMonth.length
    : 0;
  const firstFix = completedInMonth.filter((j) => j._count.visits === 1).length;

  const perTech = techs
    .map((t) => {
      const mine = openAssignments.filter((a) => a.employeeId === t.id);
      const visits = visitsInMonth.filter((v) => v.employeeId === t.id);
      return {
        tech: t,
        done: completedInMonth.filter((j) => j.assignments.some((a) => a.employeeId === t.id)).length,
        hold: mine.filter((a) => a.job.status === "ON_HOLD").length,
        open: mine.filter((a) => a.job.status !== "ON_HOLD").length,
        visits: visits.length,
        onSiteMs: visits.reduce((n, v) => n + (v.checkOutAt ? v.checkOutAt.getTime() - v.checkInAt.getTime() : 0), 0),
      };
    })
    .sort((a, b) => b.done - a.done || b.open + b.hold - (a.open + a.hold));
  const maxTotal = Math.max(1, ...perTech.map((r) => r.done + r.hold + r.open));

  const byType = Object.entries(
    createdInMonth.reduce<Record<string, number>>((acc, j) => ((acc[j.type] = (acc[j.type] ?? 0) + 1), acc), {})
  )
    .map(([type, value]) => ({ label: JOB_TYPE_LABEL[type as keyof typeof JOB_TYPE_LABEL] ?? type, value }))
    .sort((a, b) => b.value - a.value);

  const byModel = Object.entries(
    createdInMonth.reduce<Record<string, number>>((acc, j) => {
      const k = `${j.machine.machineModel.brand} ${j.machine.machineModel.model}`;
      acc[k] = (acc[k] ?? 0) + 1;
      return acc;
    }, {})
  )
    .map(([label, value]) => ({ label, value }))
    .sort((a, b) => b.value - a.value)
    .slice(0, 6);

  return (
    <div>
      {searchParams.denied && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          บัญชีของคุณไม่มีสิทธิ์เข้าหน้าที่เลือก
        </div>
      )}
      <PageHeader
        title="ภาพรวมงานบริการ"
        subtitle={`สวัสดี ${shortName(user)} — สรุปงานของทีมช่าง`}
        actions={
          can.manageJobs(user.role) && (
            <Link href="/jobs/new" className="btn btn-primary">
              ➕ ลงงานใหม่
            </Link>
          )
        }
      />

      <h2 className="mb-2 text-sm font-semibold text-gray-500">ตอนนี้</h2>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
        <StatTile label="รอจ่ายงาน" value={count("NEW")} tone={count("NEW") ? "warn" : undefined} href="/jobs?status=NEW" />
        <StatTile label="จ่ายแล้ว รอเข้า" value={count("ASSIGNED")} hint={overdue ? `เลยนัด ${overdue} งาน` : undefined} href="/jobs?status=ASSIGNED" />
        <StatTile label="กำลังทำหน้างาน" value={count("IN_PROGRESS")} href="/jobs?status=IN_PROGRESS" />
        <StatTile label="Hold (ยังไม่เสร็จ)" value={count("ON_HOLD")} href="/jobs?status=ON_HOLD" />
        <StatTile label="งานนัดวันนี้" value={todayJobs.length} href={`/jobs?status=ALL&date=${dateKey()}`} />
      </div>

      <div className="mb-2 mt-6 flex items-center gap-2">
        <h2 className="text-sm font-semibold text-gray-500">สรุปประจำเดือน</h2>
        <Link href={`/dashboard?month=${shiftMonth(month, -1)}`} className="btn btn-secondary px-2 py-0.5 text-xs" aria-label="เดือนก่อน">
          ‹
        </Link>
        <span className="text-sm font-semibold text-gray-800">{formatMonth(month)}</span>
        {!isCurrent && (
          <Link href={`/dashboard?month=${shiftMonth(month, 1)}`} className="btn btn-secondary px-2 py-0.5 text-xs" aria-label="เดือนถัดไป">
            ›
          </Link>
        )}
      </div>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <StatTile label="งานเข้าใหม่" value={createdInMonth.length} />
        <StatTile label="งานที่เสร็จ (ส่งงาน)" value={completedInMonth.length} />
        <StatTile label="เวลาเฉลี่ย ลงงาน → เสร็จ" value={completedInMonth.length ? formatDuration(avgMs) : "-"} />
        <StatTile
          label="จบในการเข้าครั้งเดียว"
          value={completedInMonth.length ? `${Math.round((firstFix / completedInMonth.length) * 100)}%` : "-"}
          hint={completedInMonth.length ? `${firstFix} จาก ${completedInMonth.length} งาน` : undefined}
        />
      </div>

      <section className="card mt-6 p-5">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-semibold text-gray-800">สรุปงานรายคน — {formatMonth(month)}</h2>
          <Legend
            items={[
              { key: "done", label: "เสร็จในเดือนนี้" },
              { key: "hold", label: "Hold ตอนนี้" },
              { key: "open", label: "รอทำ/กำลังทำ ตอนนี้" },
            ]}
          />
        </div>
        <div className="overflow-x-auto">
          <table className="data-table">
            <thead>
              <tr>
                <th>ช่าง</th>
                <th className="w-1/3 min-w-40">ภาระงาน</th>
                <th className="num">เสร็จ</th>
                <th className="num">Hold</th>
                <th className="num">รอทำ</th>
                <th className="num">เข้าหน้างาน</th>
                <th className="num">เวลาที่หน้างาน</th>
              </tr>
            </thead>
            <tbody>
              {perTech.map((r) => (
                <tr key={r.tech.id}>
                  <td className="whitespace-nowrap">
                    <Link href={`/technicians/${r.tech.id}?month=${month}`} className="link">
                      {fullName(r.tech)}
                    </Link>
                  </td>
                  <td className="align-middle">
                    <StackedBar
                      max={maxTotal}
                      parts={[
                        { key: "done", label: "เสร็จ", value: r.done },
                        { key: "hold", label: "Hold", value: r.hold },
                        { key: "open", label: "รอทำ", value: r.open },
                      ]}
                    />
                  </td>
                  <td className="num">{r.done}</td>
                  <td className="num">{r.hold}</td>
                  <td className="num">{r.open}</td>
                  <td className="num">{r.visits}</td>
                  <td className="num whitespace-nowrap">{formatDuration(r.onSiteMs)}</td>
                </tr>
              ))}
              {perTech.length === 0 && (
                <tr>
                  <td colSpan={7} className="py-6 text-center text-gray-400">
                    ยังไม่มีช่างในระบบ
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section className="card p-5">
          <h2 className="section-title">งานเข้าใหม่ตามประเภท</h2>
          <HBars rows={byType} unit=" งาน" />
        </section>
        <section className="card p-5">
          <h2 className="section-title">รุ่นเครื่องที่มีงานมากที่สุด</h2>
          <HBars rows={byModel} unit=" งาน" />
        </section>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section className="card p-5">
          <h2 className="section-title">ตารางงานวันนี้</h2>
          {todayJobs.length === 0 ? (
            <p className="text-sm text-gray-400">วันนี้ไม่มีงานนัด</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {todayJobs.map((j) => (
                <li key={j.id} className="flex items-start justify-between gap-3 py-2.5 text-sm">
                  <div className="min-w-0">
                    <div>
                      <span className="font-semibold tabular-nums">{formatTime(j.scheduledAt)}</span>{" "}
                      <Link href={`/jobs/${j.id}`} className="link">
                        {j.jobNo}
                      </Link>{" "}
                      {j.machine.site.customer.name}
                    </div>
                    <div className="truncate text-xs text-gray-500">
                      {j.assignments.length ? j.assignments.map((a) => shortName(a.employee)).join(", ") : "ยังไม่จ่ายงาน"} ·{" "}
                      {j.machine.machineModel.brand} {j.machine.machineModel.model}
                    </div>
                  </div>
                  <StatusBadge status={j.status} />
                </li>
              ))}
            </ul>
          )}
        </section>
        <section className="card p-5">
          <h2 className="section-title">งาน Hold ที่ค้างนานที่สุด</h2>
          {holds.length === 0 ? (
            <p className="text-sm text-gray-400">ไม่มีงาน Hold</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {holds.map((j) => (
                <li key={j.id} className="py-2.5 text-sm">
                  <div className="flex justify-between gap-2">
                    <span>
                      <Link href={`/jobs/${j.id}`} className="link">
                        {j.jobNo}
                      </Link>{" "}
                      {j.machine.site.customer.name}
                    </span>
                    <span className="whitespace-nowrap text-xs text-gray-500">ตั้งแต่ {timeAgo(j.visits[0]?.checkOutAt ?? j.updatedAt)}</span>
                  </div>
                  <div className="text-xs text-orange-800">{j.visits[0]?.holdReason ?? "-"}</div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}
