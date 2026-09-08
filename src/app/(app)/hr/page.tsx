import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import HrTabs from "@/components/HrTabs";
import { createEmployeeAction, setEmployeeStatusAction } from "./actions";

export default async function HrEmployeesPage() {
  const session = await requireModule("HR", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "HR");

  const employees = await prisma.employee.findMany({ orderBy: { createdAt: "desc" } });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">บุคคล (HR)</h1>
      <p className="text-sm text-gray-500 mt-1">ข้อมูลพนักงาน การลงเวลา ค่าใช้จ่ายดำเนินงาน และ KPI</p>
      <HrTabs active="/hr" />

      {editable && (
        <div className="card p-6 max-w-2xl">
          <h2 className="font-semibold text-gray-800 mb-4">เพิ่มพนักงานใหม่</h2>
          <form action={createEmployeeAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">รหัสพนักงาน</label>
              <input name="employeeCode" required className="input" />
            </div>
            <div>
              <label className="label">ชื่อ-นามสกุล</label>
              <input name="fullName" required className="input" />
            </div>
            <div>
              <label className="label">ตำแหน่ง</label>
              <input name="position" required className="input" />
            </div>
            <div>
              <label className="label">แผนก</label>
              <input name="department" required className="input" />
            </div>
            <div>
              <label className="label">เบอร์โทร</label>
              <input name="phone" className="input" />
            </div>
            <div>
              <label className="label">อีเมล</label>
              <input name="email" type="email" className="input" />
            </div>
            <div>
              <label className="label">เงินเดือน (บาท)</label>
              <input name="salary" type="number" step="0.01" min={0} className="input" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกพนักงาน
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>รหัส</th>
              <th>ชื่อ-นามสกุล</th>
              <th>ตำแหน่ง</th>
              <th>แผนก</th>
              <th>สถานะ</th>
              {editable && <th></th>}
            </tr>
          </thead>
          <tbody>
            {employees.map((e) => (
              <tr key={e.id}>
                <td>{e.employeeCode}</td>
                <td className="font-medium text-gray-900">{e.fullName}</td>
                <td>{e.position}</td>
                <td>{e.department}</td>
                <td>
                  <span
                    className={`badge ${
                      e.status === "ACTIVE" ? "bg-green-100 text-green-700" : "bg-gray-100 text-gray-600"
                    }`}
                  >
                    {e.status === "ACTIVE" ? "ทำงานอยู่" : "พ้นสภาพ"}
                  </span>
                </td>
                {editable && (
                  <td>
                    <form
                      action={setEmployeeStatusAction.bind(
                        null,
                        e.id,
                        e.status === "ACTIVE" ? "INACTIVE" : "ACTIVE"
                      )}
                    >
                      <button type="submit" className="text-sm text-gray-500 hover:text-red-600">
                        {e.status === "ACTIVE" ? "ตั้งเป็นพ้นสภาพ" : "ตั้งเป็นทำงานอยู่"}
                      </button>
                    </form>
                  </td>
                )}
              </tr>
            ))}
            {employees.length === 0 && (
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
