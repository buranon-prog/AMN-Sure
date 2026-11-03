import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { formatDateTime } from "@/lib/datetime";
import { fullName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import EmployeeFields from "@/components/EmployeeFields";
import { RoleBadge } from "@/components/Badges";
import { resetPasswordAction, revokeTrackingAppAction, setActiveAction, unlockAction, updateEmployeeAction } from "../actions";
import { MIN_PASSWORD } from "@/lib/constants";

export default async function EmployeeDetailPage({ params, searchParams }: { params: { id: string }; searchParams: { created?: string } }) {
  const me = await requireUser(can.manageEmployees);
  const emp = await prisma.employee.findUnique({ where: { id: params.id } });
  if (!emp) notFound();
  const locked = emp.lockedUntil && emp.lockedUntil > new Date();

  return (
    <div className="max-w-3xl">
      <Link href="/employees" className="text-sm text-gray-500 hover:text-gray-800">
        ← พนักงาน & บัญชี
      </Link>
      <PageHeader
        title={fullName(emp)}
        subtitle={
          <span className="flex items-center gap-2">
            {emp.employeeCode} <RoleBadge role={emp.role} />
            {!emp.active && <span className="badge bg-gray-200 text-gray-600">ปิดบัญชี</span>}
          </span>
        }
        actions={
          can.doFieldWork(emp.role) && (
            <Link href={`/technicians/${emp.id}`} className="btn btn-secondary">
              ดูประวัติงาน / ความถนัด
            </Link>
          )
        }
      />
      {searchParams.created && (
        <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          สร้างบัญชีแล้ว — พนักงานเข้าสู่ระบบด้วยรหัส <b>{emp.employeeCode}</b> และรหัสผ่านเริ่มต้นที่ตั้งไว้
        </div>
      )}

      <ActionForm action={updateEmployeeAction.bind(null, emp.id)} className="card space-y-4 p-5">
        <h2 className="section-title">ข้อมูลพนักงาน & บทบาท</h2>
        <EmployeeFields employee={emp} />
        <SubmitButton>บันทึก</SubmitButton>
      </ActionForm>

      <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
        <ActionForm action={resetPasswordAction.bind(null, emp.id)} resetOnSuccess className="card space-y-3 p-5">
          <h2 className="section-title">รีเซ็ตรหัสผ่าน</h2>
          <input
            name="password"
            type="password"
            required
            minLength={MIN_PASSWORD}
            autoComplete="new-password"
            placeholder={`รหัสผ่านใหม่ (อย่างน้อย ${MIN_PASSWORD} ตัว)`}
            className="input"
          />
          <SubmitButton className="btn btn-secondary">ตั้งรหัสผ่านใหม่</SubmitButton>
        </ActionForm>

        <div className="card space-y-3 p-5">
          <h2 className="section-title">สถานะบัญชี</h2>
          <p className="text-sm text-gray-600">
            {emp.active ? "ใช้งานได้" : "ปิดบัญชีแล้ว (เข้าสู่ระบบไม่ได้)"}
            {locked && ` · ถูกล็อกถึง ${formatDateTime(emp.lockedUntil)} เพราะใส่รหัสผิดหลายครั้ง`}
          </p>
          {locked && (
            <ActionForm action={unlockAction.bind(null, emp.id)}>
              <SubmitButton className="btn btn-secondary">ปลดล็อก</SubmitButton>
            </ActionForm>
          )}
          {emp.trackingTokenHash && (
            <div className="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
              เชื่อมแอปติดตามเบื้องหลังแล้ว (รหัสลงท้าย ••••{emp.trackingTokenHint}, {formatDateTime(emp.trackingTokenAt)})
              <ActionForm action={revokeTrackingAppAction.bind(null, emp.id)} confirm="ยกเลิกการเชื่อมแอปติดตามของพนักงานคนนี้?" className="mt-2">
                <SubmitButton className="btn btn-secondary text-xs">ยกเลิกการเชื่อมแอปติดตาม</SubmitButton>
              </ActionForm>
            </div>
          )}
          {emp.id !== me.id && (
            <ActionForm
              action={setActiveAction.bind(null, emp.id, !emp.active)}
              confirm={emp.active ? "ปิดบัญชีพนักงานคนนี้? จะเข้าสู่ระบบไม่ได้ทันที (ข้อมูลประวัติยังอยู่ครบ)" : undefined}
            >
              <SubmitButton className={emp.active ? "btn btn-danger" : "btn btn-primary"}>
                {emp.active ? "ปิดบัญชี" : "เปิดใช้งานบัญชี"}
              </SubmitButton>
            </ActionForm>
          )}
        </div>
      </div>
    </div>
  );
}
