import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import HrTabs from "@/components/HrTabs";
import { KPI_PERIOD } from "@/lib/constants";
import { createKpiDefinitionAction, recordKpiAction } from "../actions";

export default async function KpiPage() {
  const session = await requireModule("HR", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "HR");
  const isManager = session.user.role === "MANAGER";

  const [definitions, employees, records] = await Promise.all([
    prisma.kpiDefinition.findMany({ where: { active: true }, orderBy: { createdAt: "desc" } }),
    prisma.employee.findMany({ where: { status: "ACTIVE" }, orderBy: { fullName: "asc" } }),
    prisma.kpiRecord.findMany({
      orderBy: { createdAt: "desc" },
      include: { employee: true, kpiDefinition: true },
      take: 100,
    }),
  ]);

  const currentPeriod = new Date().toISOString().slice(0, 7);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">บุคคล (HR)</h1>
      <p className="text-sm text-gray-500 mt-1">
        กำหนดตัวชี้วัด KPI ได้อย่างอิสระ และบันทึกผลของพนักงานแต่ละคนตามรอบเวลา
      </p>
      <HrTabs active="/hr/kpi" />

      {isManager && (
        <div className="card p-6 max-w-2xl">
          <h2 className="font-semibold text-gray-800 mb-4">ตั้งค่าตัวชี้วัด KPI ใหม่</h2>
          <form action={createKpiDefinitionAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div className="sm:col-span-2">
              <label className="label">ชื่อ KPI</label>
              <input name="name" required className="input" placeholder="เช่น ยอดขายต่อเดือน, จำนวนลูกค้าใหม่" />
            </div>
            <div>
              <label className="label">หน่วย</label>
              <input name="unit" className="input" placeholder="บาท / ครั้ง / %" />
            </div>
            <div>
              <label className="label">เป้าหมาย</label>
              <input name="targetValue" type="number" step="0.01" className="input" />
            </div>
            <div>
              <label className="label">รอบการประเมิน</label>
              <select name="period" className="input" defaultValue="MONTHLY">
                {KPI_PERIOD.map((p) => (
                  <option key={p} value={p}>
                    {p === "MONTHLY" ? "รายเดือน" : p === "QUARTERLY" ? "รายไตรมาส" : "รายปี"}
                  </option>
                ))}
              </select>
            </div>
            <div className="sm:col-span-2">
              <label className="label">คำอธิบาย</label>
              <textarea name="description" className="input" rows={2} />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกตัวชี้วัด
              </button>
            </div>
          </form>
        </div>
      )}

      {editable && (
        <div className="card p-6 max-w-2xl mt-6">
          <h2 className="font-semibold text-gray-800 mb-4">บันทึกผล KPI ของพนักงาน</h2>
          <form action={recordKpiAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">ตัวชี้วัด</label>
              <select name="kpiDefinitionId" required className="input">
                <option value="">-- เลือก KPI --</option>
                {definitions.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">พนักงาน</label>
              <select name="employeeId" required className="input">
                <option value="">-- เลือกพนักงาน --</option>
                {employees.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.fullName}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">รอบ (เช่น 2026-09)</label>
              <input name="period" defaultValue={currentPeriod} required className="input" />
            </div>
            <div>
              <label className="label">ผลลัพธ์จริง</label>
              <input name="actualValue" type="number" step="0.01" required className="input" />
            </div>
            <div className="sm:col-span-2">
              <label className="label">หมายเหตุ</label>
              <input name="note" className="input" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกผล
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>ตัวชี้วัด</th>
              <th>พนักงาน</th>
              <th>รอบ</th>
              <th>ผลลัพธ์</th>
              <th>เป้าหมาย</th>
              <th>หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>
            {records.map((r) => (
              <tr key={r.id}>
                <td className="font-medium text-gray-900">{r.kpiDefinition.name}</td>
                <td>{r.employee.fullName}</td>
                <td>{r.period}</td>
                <td>
                  {r.actualValue.toLocaleString("th-TH")} {r.kpiDefinition.unit ?? ""}
                </td>
                <td>
                  {r.kpiDefinition.targetValue != null
                    ? `${r.kpiDefinition.targetValue.toLocaleString("th-TH")} ${r.kpiDefinition.unit ?? ""}`
                    : "-"}
                </td>
                <td>{r.note ?? "-"}</td>
              </tr>
            ))}
            {records.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลผล KPI
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
