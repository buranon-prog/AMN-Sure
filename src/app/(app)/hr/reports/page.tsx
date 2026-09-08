import Link from "next/link";
import { requireModule } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import HrTabs from "@/components/HrTabs";

function monthRange(date = new Date()) {
  const start = new Date(date.getFullYear(), date.getMonth(), 1);
  const end = new Date(date.getFullYear(), date.getMonth() + 1, 1);
  return { start, end };
}

export default async function HrReportsPage() {
  await requireModule("HR", "view");

  const employees = await prisma.employee.findMany({
    where: { status: "ACTIVE" },
    orderBy: { fullName: "asc" },
  });

  const { start, end } = monthRange();
  const currentPeriod = new Date().toISOString().slice(0, 7);

  const rows = await Promise.all(
    employees.map(async (e) => {
      const [attendance, planCounts, kpiRecords] = await Promise.all([
        prisma.attendance.groupBy({
          by: ["status"],
          where: { employeeId: e.id, date: { gte: start, lt: end } },
          _count: true,
        }),
        prisma.employeePlan.groupBy({
          by: ["status"],
          where: { employeeId: e.id },
          _count: true,
        }),
        prisma.kpiRecord.findMany({
          where: { employeeId: e.id, period: currentPeriod },
          include: { kpiDefinition: true },
        }),
      ]);

      const present = attendance.find((a) => a.status === "PRESENT")?._count ?? 0;
      const totalDays = attendance.reduce((sum, a) => sum + a._count, 0);

      const openPlans = planCounts
        .filter((p) => p.status === "PLANNED" || p.status === "IN_PROGRESS" || p.status === "DELAYED")
        .reduce((sum, p) => sum + p._count, 0);
      const completedPlans = planCounts.find((p) => p.status === "COMPLETED")?._count ?? 0;

      const withTarget = kpiRecords.filter((k) => k.kpiDefinition.targetValue);
      const avgAchievement =
        withTarget.length > 0
          ? withTarget.reduce((sum, k) => sum + k.actualValue / (k.kpiDefinition.targetValue as number), 0) /
            withTarget.length
          : null;

      return { employee: e, present, totalDays, openPlans, completedPlans, avgAchievement };
    })
  );

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">บุคคล (HR)</h1>
      <p className="text-sm text-gray-500 mt-1">
        รายงานสรุปผลการทำงานของพนักงานแต่ละคน (เดือนปัจจุบัน) — การมาทำงาน, KPI, แผนงาน
      </p>
      <HrTabs active="/hr/reports" />

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>พนักงาน</th>
              <th>ตำแหน่ง</th>
              <th>การมาทำงานเดือนนี้</th>
              <th>KPI เฉลี่ย (เทียบเป้าหมาย)</th>
              <th>แผนงานค้าง / เสร็จแล้ว</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.employee.id}>
                <td className="font-medium text-gray-900">{r.employee.fullName}</td>
                <td>{r.employee.position}</td>
                <td>
                  {r.totalDays > 0 ? (
                    <>
                      {r.present}/{r.totalDays} วัน
                      <span className="text-xs text-gray-400"> ({Math.round((r.present / r.totalDays) * 100)}%)</span>
                    </>
                  ) : (
                    "ยังไม่มีข้อมูล"
                  )}
                </td>
                <td>
                  {r.avgAchievement != null ? (
                    <span
                      className={`badge ${
                        r.avgAchievement >= 1
                          ? "bg-green-100 text-green-700"
                          : r.avgAchievement >= 0.7
                          ? "bg-amber-100 text-amber-700"
                          : "bg-red-100 text-red-700"
                      }`}
                    >
                      {Math.round(r.avgAchievement * 100)}%
                    </span>
                  ) : (
                    "-"
                  )}
                </td>
                <td>
                  {r.openPlans} ค้าง / {r.completedPlans} เสร็จ
                </td>
                <td>
                  <Link href={`/hr/reports/${r.employee.id}`} className="text-brand-600 hover:underline text-sm">
                    ดูรายงานฉบับเต็ม
                  </Link>
                </td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลพนักงาน
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
