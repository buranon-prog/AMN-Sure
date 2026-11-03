import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { OPEN_JOB_STATUS, SKILL_LABEL } from "@/lib/constants";
import { formatDateTime, formatDuration, formatMonth, monthKey, monthRange } from "@/lib/datetime";
import { jobListInclude } from "@/lib/jobs";
import { fullName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import JobList from "@/components/JobList";
import { RoleBadge, SkillDots } from "@/components/Badges";
import { saveSkillsAction } from "../actions";

function shiftMonth(key: string, delta: number) {
  const [y, m] = key.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1 + delta, 1)).toISOString().slice(0, 7);
}

/** ประวัติการซ่อมของพนักงาน + ความถนัด */
export default async function TechnicianDetailPage({ params, searchParams }: { params: { id: string }; searchParams: { month?: string } }) {
  const user = await requireUser(can.useOffice);
  const tech = await prisma.employee.findUnique({ where: { id: params.id }, include: { skills: true } });
  if (!tech) notFound();

  const month = /^\d{4}-\d{2}$/.test(searchParams.month ?? "") ? searchParams.month! : monthKey();
  const { start, end } = monthRange(month);

  const [openJobs, visits, completedJobs, models] = await Promise.all([
    prisma.job.findMany({
      where: { status: { in: OPEN_JOB_STATUS }, assignments: { some: { employeeId: tech.id } } },
      include: jobListInclude,
      orderBy: { scheduledAt: "asc" },
    }),
    prisma.jobVisit.findMany({
      where: { employeeId: tech.id, checkInAt: { gte: start, lt: end } },
      include: { job: { include: { machine: { include: { machineModel: true, site: { include: { customer: true } } } } } } },
      orderBy: { checkInAt: "desc" },
    }),
    prisma.job.findMany({
      where: { status: "COMPLETED", completedAt: { gte: start, lt: end }, assignments: { some: { employeeId: tech.id } } },
      select: { id: true, createdAt: true, completedAt: true, _count: { select: { visits: true } } },
    }),
    prisma.machineModel.findMany({ where: { active: true }, orderBy: [{ category: "asc" }, { brand: "asc" }, { model: "asc" }] }),
  ]);

  const onSiteMs = visits.reduce((n, v) => n + (v.checkOutAt ? v.checkOutAt.getTime() - v.checkInAt.getTime() : 0), 0);
  const firstVisitFix = completedJobs.filter((j) => j._count.visits === 1).length;
  const levelOf = (modelId: string) => tech.skills.find((s) => s.machineModelId === modelId)?.level ?? 0;
  const editable = can.manageSkills(user.role);

  return (
    <div>
      <Link href="/technicians" className="text-sm text-gray-500 hover:text-gray-800">
        ← ช่าง & ความถนัด
      </Link>
      <PageHeader
        title={fullName(tech)}
        subtitle={
          <span className="flex flex-wrap items-center gap-2">
            <RoleBadge role={tech.role} /> {tech.employeeCode}
            {tech.position && ` · ${tech.position}`}
            {tech.phone && (
              <a href={`tel:${tech.phone}`} className="link">
                {tech.phone}
              </a>
            )}
            {!tech.active && <span className="badge bg-red-100 text-red-700">ปิดบัญชีแล้ว</span>}
          </span>
        }
        actions={
          can.viewTracking(user.role) && (
            <Link href={`/tracking?employee=${tech.id}`} className="btn btn-secondary">
              📍 ดูตำแหน่ง
            </Link>
          )
        }
      />

      <div className="mb-3 flex items-center gap-2">
        <Link href={`?month=${shiftMonth(month, -1)}`} className="btn btn-secondary px-3 py-1">
          ‹
        </Link>
        <span className="font-semibold text-gray-800">{formatMonth(month)}</span>
        {month !== monthKey() && (
          <Link href={`?month=${shiftMonth(month, 1)}`} className="btn btn-secondary px-3 py-1">
            ›
          </Link>
        )}
      </div>
      <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
        {[
          { label: "งานที่ส่งแล้ว", value: completedJobs.length },
          { label: "เข้าหน้างาน", value: `${visits.length} ครั้ง` },
          { label: "เวลาที่หน้างาน", value: formatDuration(onSiteMs) },
          { label: "จบในครั้งเดียว", value: completedJobs.length ? `${Math.round((firstVisitFix / completedJobs.length) * 100)}%` : "-" },
          { label: "งานค้างตอนนี้", value: openJobs.length },
        ].map((s) => (
          <div key={s.label} className="card p-3">
            <div className="text-xs text-gray-500">{s.label}</div>
            <div className="text-xl font-bold tabular-nums text-gray-900">{s.value}</div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section>
            <h2 className="section-title">งานที่รับผิดชอบอยู่ ({openJobs.length})</h2>
            <JobList jobs={openJobs} empty="ไม่มีงานค้าง" showTechs={false} />
          </section>
          <section>
            <h2 className="section-title">ประวัติการซ่อม — {formatMonth(month)}</h2>
            {visits.length === 0 ? (
              <div className="card p-6 text-center text-sm text-gray-400">ไม่มีการเข้าหน้างานในเดือนนี้</div>
            ) : (
              <div className="card overflow-x-auto">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>วันที่</th>
                      <th>งาน / ลูกค้า</th>
                      <th>เครื่อง</th>
                      <th>สรุปงาน</th>
                      <th>ผล</th>
                    </tr>
                  </thead>
                  <tbody>
                    {visits.map((v) => (
                      <tr key={v.id}>
                        <td className="whitespace-nowrap">{formatDateTime(v.checkInAt)}</td>
                        <td>
                          <Link href={`/jobs/${v.jobId}`} className="link">
                            {v.job.jobNo}
                          </Link>
                          <div className="text-xs text-gray-500">{v.job.machine.site.customer.name}</div>
                        </td>
                        <td>
                          {v.job.machine.machineModel.brand} {v.job.machine.machineModel.model}
                          <div className="text-xs text-gray-500">{v.job.machine.machineCode}</div>
                        </td>
                        <td className="max-w-sm">
                          <div className="line-clamp-3">{v.summary ?? "-"}</div>
                        </td>
                        <td>
                          {v.outcome === "COMPLETED" ? (
                            <span className="badge bg-emerald-100 text-emerald-800">ส่งงาน</span>
                          ) : v.outcome === "HOLD" ? (
                            <span className="badge bg-orange-100 text-orange-800">Hold</span>
                          ) : (
                            <span className="badge bg-indigo-100 text-indigo-800">อยู่หน้างาน</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </div>

        <section className="card h-fit p-5">
          <h2 className="section-title">ความถนัดต่อรุ่นเครื่อง</h2>
          {editable ? (
            <ActionForm action={saveSkillsAction.bind(null, tech.id)} className="space-y-2">
              {models.map((m) => (
                <div key={m.id} className="flex items-center justify-between gap-2 text-sm">
                  <span className="min-w-0">
                    {m.brand} {m.model}
                    <span className="block text-xs text-gray-400">{m.category}</span>
                  </span>
                  <select name={`skill_${m.id}`} defaultValue={levelOf(m.id)} className="input w-40 py-1 text-xs">
                    <option value={0}>— ไม่มี</option>
                    {[1, 2, 3, 4, 5].map((l) => (
                      <option key={l} value={l}>
                        {l} · {SKILL_LABEL[l]}
                      </option>
                    ))}
                  </select>
                </div>
              ))}
              <SubmitButton className="btn btn-primary mt-2 w-full">บันทึกความถนัด</SubmitButton>
            </ActionForm>
          ) : (
            <ul className="space-y-2 text-sm">
              {models
                .filter((m) => levelOf(m.id) > 0)
                .map((m) => (
                  <li key={m.id} className="flex items-center justify-between">
                    <span>
                      {m.brand} {m.model}
                    </span>
                    <SkillDots level={levelOf(m.id)} />
                  </li>
                ))}
              {tech.skills.length === 0 && <li className="text-gray-400">ยังไม่มีข้อมูลความถนัด</li>}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}
