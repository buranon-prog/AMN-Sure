import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { formatDateTime, formatDuration, formatMonth, monthKey, monthRange } from "@/lib/datetime";

function shiftMonth(key: string, delta: number) {
  const [y, m] = key.split("-").map(Number);
  const d = new Date(Date.UTC(y, m - 1 + delta, 1));
  return d.toISOString().slice(0, 7);
}

export default async function MyHistoryPage({ searchParams }: { searchParams: { month?: string } }) {
  const user = await requireUser(can.doFieldWork);
  const month = /^\d{4}-\d{2}$/.test(searchParams.month ?? "") ? searchParams.month! : monthKey();
  const { start, end } = monthRange(month);

  const [visits, completed, holdNow] = await Promise.all([
    prisma.jobVisit.findMany({
      where: { employeeId: user.id, checkInAt: { gte: start, lt: end } },
      include: { job: { include: { machine: { include: { machineModel: true, site: { include: { customer: true } } } } } } },
      orderBy: { checkInAt: "desc" },
    }),
    prisma.job.count({
      where: { status: "COMPLETED", completedAt: { gte: start, lt: end }, assignments: { some: { employeeId: user.id } } },
    }),
    prisma.job.count({ where: { status: "ON_HOLD", assignments: { some: { employeeId: user.id } } } }),
  ]);

  const onSiteMs = visits.reduce((sum, v) => sum + (v.checkOutAt ? v.checkOutAt.getTime() - v.checkInAt.getTime() : 0), 0);
  const isCurrent = month === monthKey();

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <Link href={`/m/history?month=${shiftMonth(month, -1)}`} className="btn btn-secondary px-3 py-1.5" aria-label="เดือนก่อน">
          ‹
        </Link>
        <h1 className="font-bold text-gray-900">{formatMonth(month)}</h1>
        {isCurrent ? (
          <span className="w-10" />
        ) : (
          <Link href={`/m/history?month=${shiftMonth(month, 1)}`} className="btn btn-secondary px-3 py-1.5" aria-label="เดือนถัดไป">
            ›
          </Link>
        )}
      </div>

      <div className="grid grid-cols-2 gap-2">
        {[
          { label: "งานที่เสร็จ", value: `${completed}` },
          { label: "เข้าหน้างาน", value: `${visits.length} ครั้ง` },
          { label: "เวลาที่หน้างาน", value: formatDuration(onSiteMs) },
          { label: "งาน Hold ตอนนี้", value: `${holdNow}` },
        ].map((s) => (
          <div key={s.label} className="card p-3">
            <div className="text-xs text-gray-500">{s.label}</div>
            <div className="text-xl font-bold tabular-nums text-gray-900">{s.value}</div>
          </div>
        ))}
      </div>

      <section>
        <h2 className="mb-2 font-semibold text-gray-800">ประวัติการซ่อมของฉัน</h2>
        {visits.length === 0 ? (
          <div className="card p-6 text-center text-sm text-gray-400">ไม่มีการเข้าหน้างานในเดือนนี้</div>
        ) : (
          <ul className="space-y-2">
            {visits.map((v) => (
              <li key={v.id}>
                <Link href={`/m/jobs/${v.jobId}`} className="card block p-3">
                  <div className="flex items-center justify-between text-xs text-gray-500">
                    <span>
                      {v.job.jobNo} · {formatDateTime(v.checkInAt)}
                    </span>
                    {v.outcome === "COMPLETED" ? (
                      <span className="badge bg-emerald-100 text-emerald-800">เสร็จ</span>
                    ) : v.outcome === "HOLD" ? (
                      <span className="badge bg-orange-100 text-orange-800">Hold</span>
                    ) : (
                      <span className="badge bg-indigo-100 text-indigo-800">กำลังทำ</span>
                    )}
                  </div>
                  <div className="mt-1 font-semibold text-gray-900">{v.job.machine.site.customer.name}</div>
                  <div className="text-sm text-gray-600">
                    {v.job.machine.machineModel.brand} {v.job.machine.machineModel.model} · {v.job.machine.machineCode}
                  </div>
                  {v.summary && <p className="mt-1 line-clamp-2 text-sm text-gray-700">{v.summary}</p>}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
