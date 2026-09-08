import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import HrTabs from "@/components/HrTabs";
import { ATTENDANCE_STATUS } from "@/lib/constants";
import { recordAttendanceAction } from "../actions";

const STATUS_LABEL: Record<string, string> = {
  PRESENT: "มาทำงาน",
  LATE: "มาสาย",
  ABSENT: "ขาดงาน",
  LEAVE: "ลา",
};

export default async function AttendancePage() {
  const session = await requireModule("HR", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "HR");

  const [employees, attendances] = await Promise.all([
    prisma.employee.findMany({ where: { status: "ACTIVE" }, orderBy: { fullName: "asc" } }),
    prisma.attendance.findMany({
      orderBy: { date: "desc" },
      include: { employee: true },
      take: 100,
    }),
  ]);

  const today = new Date().toISOString().slice(0, 10);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">บุคคล (HR)</h1>
      <p className="text-sm text-gray-500 mt-1">การลงเวลาทำงานประจำวันของพนักงานแต่ละคน</p>
      <HrTabs active="/hr/attendance" />

      {editable && (
        <div className="card p-6 max-w-2xl">
          <h2 className="font-semibold text-gray-800 mb-4">บันทึกการลงเวลา</h2>
          <form action={recordAttendanceAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">พนักงาน</label>
              <select name="employeeId" required className="input">
                <option value="">-- เลือกพนักงาน --</option>
                {employees.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.fullName} ({e.employeeCode})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">วันที่</label>
              <input name="date" type="date" defaultValue={today} required className="input" />
            </div>
            <div>
              <label className="label">เวลาเข้า</label>
              <input name="checkIn" type="time" className="input" />
            </div>
            <div>
              <label className="label">เวลาออก</label>
              <input name="checkOut" type="time" className="input" />
            </div>
            <div>
              <label className="label">สถานะ</label>
              <select name="status" className="input" defaultValue="PRESENT">
                {ATTENDANCE_STATUS.map((s) => (
                  <option key={s} value={s}>
                    {STATUS_LABEL[s]}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">หมายเหตุ</label>
              <input name="notes" className="input" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึก
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>วันที่</th>
              <th>พนักงาน</th>
              <th>เวลาเข้า</th>
              <th>เวลาออก</th>
              <th>สถานะ</th>
              <th>หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>
            {attendances.map((a) => (
              <tr key={a.id}>
                <td>{a.date.toLocaleDateString("th-TH")}</td>
                <td className="font-medium text-gray-900">{a.employee.fullName}</td>
                <td>{a.checkIn ? a.checkIn.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }) : "-"}</td>
                <td>{a.checkOut ? a.checkOut.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit" }) : "-"}</td>
                <td>
                  <span
                    className={`badge ${
                      a.status === "PRESENT"
                        ? "bg-green-100 text-green-700"
                        : a.status === "LATE"
                        ? "bg-amber-100 text-amber-700"
                        : "bg-red-100 text-red-700"
                    }`}
                  >
                    {STATUS_LABEL[a.status] ?? a.status}
                  </span>
                </td>
                <td>{a.notes ?? "-"}</td>
              </tr>
            ))}
            {attendances.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลการลงเวลา
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
