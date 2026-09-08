import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import HrTabs from "@/components/HrTabs";
import { PRIORITY, PLAN_STATUS } from "@/lib/constants";
import { createPlanAction, updatePlanStatusAction } from "../actions";

const PRIORITY_LABEL: Record<string, string> = { LOW: "ต่ำ", MEDIUM: "กลาง", HIGH: "สูง" };
const STATUS_LABEL: Record<string, string> = {
  PLANNED: "วางแผนไว้",
  IN_PROGRESS: "กำลังดำเนินการ",
  COMPLETED: "เสร็จสิ้น",
  DELAYED: "ล่าช้า",
  CANCELLED: "ยกเลิก",
};
const STATUS_COLOR: Record<string, string> = {
  PLANNED: "bg-gray-100 text-gray-600",
  IN_PROGRESS: "bg-blue-100 text-blue-700",
  COMPLETED: "bg-green-100 text-green-700",
  DELAYED: "bg-amber-100 text-amber-700",
  CANCELLED: "bg-red-100 text-red-700",
};

export default async function PlanningPage() {
  const session = await requireModule("HR", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "HR");

  const [employees, plans] = await Promise.all([
    prisma.employee.findMany({ where: { status: "ACTIVE" }, orderBy: { fullName: "asc" } }),
    prisma.employeePlan.findMany({
      orderBy: [{ dueDate: "asc" }, { createdAt: "desc" }],
      include: { employee: true },
      take: 200,
    }),
  ]);

  const currentPeriod = new Date().toISOString().slice(0, 7);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">บุคคล (HR)</h1>
      <p className="text-sm text-gray-500 mt-1">
        แผนงาน (Planning) ของพนักงานแต่ละคนในแต่ละรอบเวลา — สิ่งที่ควรทำ ต่างจาก KPI ที่เป็นผลลัพธ์จริง
      </p>
      <HrTabs active="/hr/planning" />

      {editable && (
        <div className="card p-6 max-w-2xl">
          <h2 className="font-semibold text-gray-800 mb-4">สร้างแผนงานใหม่</h2>
          <form action={createPlanAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
            <div className="sm:col-span-2">
              <label className="label">ชื่อแผนงาน</label>
              <input name="title" required className="input" placeholder="เช่น ปิดการขายลูกค้ารายใหญ่ 3 ราย" />
            </div>
            <div>
              <label className="label">กำหนดเสร็จ</label>
              <input name="dueDate" type="date" className="input" />
            </div>
            <div>
              <label className="label">ความสำคัญ</label>
              <select name="priority" className="input" defaultValue="MEDIUM">
                {PRIORITY.map((p) => (
                  <option key={p} value={p}>
                    {PRIORITY_LABEL[p]}
                  </option>
                ))}
              </select>
            </div>
            <div className="sm:col-span-2">
              <label className="label">รายละเอียด</label>
              <textarea name="description" className="input" rows={2} />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกแผนงาน
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>พนักงาน</th>
              <th>แผนงาน</th>
              <th>รอบ</th>
              <th>กำหนดเสร็จ</th>
              <th>ความสำคัญ</th>
              <th>สถานะ</th>
              {editable && <th></th>}
            </tr>
          </thead>
          <tbody>
            {plans.map((p) => (
              <tr key={p.id}>
                <td className="font-medium text-gray-900">{p.employee.fullName}</td>
                <td>
                  {p.title}
                  {p.description && <div className="text-xs text-gray-400">{p.description}</div>}
                </td>
                <td>{p.period}</td>
                <td>{p.dueDate ? p.dueDate.toLocaleDateString("th-TH") : "-"}</td>
                <td>
                  <span className="badge bg-brand-100 text-brand-700">{PRIORITY_LABEL[p.priority]}</span>
                </td>
                <td>
                  <span className={`badge ${STATUS_COLOR[p.status] ?? "bg-gray-100 text-gray-600"}`}>
                    {STATUS_LABEL[p.status] ?? p.status}
                  </span>
                </td>
                {editable && (
                  <td>
                    <form action={updatePlanStatusAction.bind(null, p.id)} className="flex gap-2">
                      <select name="status" defaultValue={p.status} className="input text-xs py-1">
                        {PLAN_STATUS.map((s) => (
                          <option key={s} value={s}>
                            {STATUS_LABEL[s]}
                          </option>
                        ))}
                      </select>
                      <button type="submit" className="btn btn-secondary text-xs">
                        อัปเดต
                      </button>
                    </form>
                  </td>
                )}
              </tr>
            ))}
            {plans.length === 0 && (
              <tr>
                <td colSpan={7} className="text-center text-gray-400 py-6">
                  ยังไม่มีแผนงาน
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
