import { notFound } from "next/navigation";
import { requireModule } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";

const PLAN_STATUS_LABEL: Record<string, string> = {
  PLANNED: "วางแผนไว้",
  IN_PROGRESS: "กำลังดำเนินการ",
  COMPLETED: "เสร็จสิ้น",
  DELAYED: "ล่าช้า",
  CANCELLED: "ยกเลิก",
};

const ATTENDANCE_LABEL: Record<string, string> = {
  PRESENT: "มาทำงาน",
  LATE: "มาสาย",
  ABSENT: "ขาดงาน",
  LEAVE: "ลา",
};

export default async function EmployeeReportPage({ params }: { params: { id: string } }) {
  await requireModule("HR", "view");

  const employee = await prisma.employee.findUnique({ where: { id: params.id } });
  if (!employee) notFound();

  const [attendances, kpiRecords, plans] = await Promise.all([
    prisma.attendance.findMany({
      where: { employeeId: employee.id },
      orderBy: { date: "desc" },
      take: 30,
    }),
    prisma.kpiRecord.findMany({
      where: { employeeId: employee.id },
      orderBy: { createdAt: "desc" },
      include: { kpiDefinition: true },
      take: 30,
    }),
    prisma.employeePlan.findMany({
      where: { employeeId: employee.id },
      orderBy: [{ dueDate: "asc" }, { createdAt: "desc" }],
      take: 50,
    }),
  ]);

  return (
    <div>
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">รายงานพนักงาน: {employee.fullName}</h1>
          <p className="text-sm text-gray-500 mt-1">
            {employee.position} · {employee.department} · รหัส {employee.employeeCode}
          </p>
        </div>
        <a href={`/api/export/employee-report?employeeId=${employee.id}`} className="btn btn-secondary">
          Export CSV
        </a>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div className="card p-6">
          <h2 className="font-semibold text-gray-800 mb-3">การลงเวลาล่าสุด</h2>
          <table className="data-table">
            <thead>
              <tr>
                <th>วันที่</th>
                <th>สถานะ</th>
                <th>เวลาเข้า-ออก</th>
              </tr>
            </thead>
            <tbody>
              {attendances.map((a) => (
                <tr key={a.id}>
                  <td>{a.date.toLocaleDateString("th-TH")}</td>
                  <td>{ATTENDANCE_LABEL[a.status] ?? a.status}</td>
                  <td>
                    {a.checkIn ? a.checkIn.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }) : "-"}
                    {" - "}
                    {a.checkOut ? a.checkOut.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }) : "-"}
                  </td>
                </tr>
              ))}
              {attendances.length === 0 && (
                <tr>
                  <td colSpan={3} className="text-center text-gray-400 py-4">
                    ยังไม่มีข้อมูล
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="card p-6">
          <h2 className="font-semibold text-gray-800 mb-3">ผล KPI</h2>
          <table className="data-table">
            <thead>
              <tr>
                <th>ตัวชี้วัด</th>
                <th>รอบ</th>
                <th>ผลลัพธ์</th>
                <th>เป้าหมาย</th>
              </tr>
            </thead>
            <tbody>
              {kpiRecords.map((k) => (
                <tr key={k.id}>
                  <td>{k.kpiDefinition.name}</td>
                  <td>{k.period}</td>
                  <td>
                    {k.actualValue.toLocaleString("th-TH")} {k.kpiDefinition.unit ?? ""}
                  </td>
                  <td>
                    {k.kpiDefinition.targetValue != null
                      ? `${k.kpiDefinition.targetValue.toLocaleString("th-TH")} ${k.kpiDefinition.unit ?? ""}`
                      : "-"}
                  </td>
                </tr>
              ))}
              {kpiRecords.length === 0 && (
                <tr>
                  <td colSpan={4} className="text-center text-gray-400 py-4">
                    ยังไม่มีข้อมูล
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="card p-6 mt-6">
        <h2 className="font-semibold text-gray-800 mb-3">แผนงาน</h2>
        <table className="data-table">
          <thead>
            <tr>
              <th>แผนงาน</th>
              <th>รอบ</th>
              <th>กำหนดเสร็จ</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            {plans.map((p) => (
              <tr key={p.id}>
                <td className="font-medium text-gray-900">{p.title}</td>
                <td>{p.period}</td>
                <td>{p.dueDate ? p.dueDate.toLocaleDateString("th-TH") : "-"}</td>
                <td>
                  <span className="badge bg-gray-100 text-gray-600">
                    {PLAN_STATUS_LABEL[p.status] ?? p.status}
                  </span>
                </td>
              </tr>
            ))}
            {plans.length === 0 && (
              <tr>
                <td colSpan={4} className="text-center text-gray-400 py-4">
                  ยังไม่มีข้อมูล
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
