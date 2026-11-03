import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { fullName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import EmployeeFields from "@/components/EmployeeFields";
import PermissionMatrix from "@/components/PermissionMatrix";
import { RoleBadge } from "@/components/Badges";
import { createEmployeeAction } from "./actions";
import { MIN_PASSWORD } from "@/lib/constants";

export default async function EmployeesPage() {
  await requireUser(can.manageEmployees);
  const employees = await prisma.employee.findMany({ orderBy: [{ active: "desc" }, { role: "asc" }, { employeeCode: "asc" }] });

  return (
    <div>
      <PageHeader
        title="พนักงาน & บัญชีผู้ใช้"
        subtitle="เฉพาะผู้จัดการ — พนักงานเข้าสู่ระบบด้วยรหัสพนักงาน + รหัสผ่านของตัวเอง และเห็นเฉพาะส่วนที่บทบาทอนุญาต"
      />

      <div className="card overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>รหัสพนักงาน</th>
              <th>ชื่อ-นามสกุล</th>
              <th>ตำแหน่ง</th>
              <th>บทบาท</th>
              <th>เบอร์โทร</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            {employees.map((e) => (
              <tr key={e.id} className={e.active ? "" : "opacity-50"}>
                <td>
                  <Link href={`/employees/${e.id}`} className="link">
                    {e.employeeCode}
                  </Link>
                </td>
                <td>{fullName(e)}</td>
                <td>{e.position ?? "-"}</td>
                <td>
                  <RoleBadge role={e.role} />
                </td>
                <td>{e.phone ?? "-"}</td>
                <td>
                  {!e.active ? (
                    <span className="badge bg-gray-200 text-gray-600">ปิดบัญชี</span>
                  ) : e.lockedUntil && e.lockedUntil > new Date() ? (
                    <span className="badge bg-red-100 text-red-700">ถูกล็อก</span>
                  ) : (
                    <span className="badge bg-emerald-100 text-emerald-800">ใช้งาน</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <details className="card mt-6 p-5">
        <summary className="cursor-pointer font-semibold text-gray-800">➕ เพิ่มพนักงาน / สร้างบัญชี</summary>
        <ActionForm action={createEmployeeAction} className="mt-4 space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label className="label">รหัสพนักงาน (ใช้เข้าสู่ระบบ) *</label>
              <input name="employeeCode" required className="input uppercase" placeholder="เช่น TEC005" />
            </div>
            <div>
              <label className="label">รหัสผ่านเริ่มต้น * (อย่างน้อย {MIN_PASSWORD} ตัว)</label>
              <input name="password" type="password" required minLength={MIN_PASSWORD} autoComplete="new-password" className="input" />
            </div>
          </div>
          <EmployeeFields />
          <SubmitButton>สร้างบัญชี</SubmitButton>
        </ActionForm>
      </details>

      <section className="card mt-6 p-5">
        <h2 className="section-title">สิทธิ์การเข้าถึงของแต่ละบทบาท</h2>
        <p className="mb-3 text-sm text-gray-500">
          ระบบตรวจสิทธิ์ที่ฝั่ง server ทุกหน้าและทุกการบันทึก — เปลี่ยนบทบาทแล้วมีผลทันที ไม่ต้องรอเข้าสู่ระบบใหม่
        </p>
        <PermissionMatrix />
      </section>
    </div>
  );
}
