import Link from "next/link";
import { requireUser } from "@/lib/session";
import { homeFor, can } from "@/lib/permissions";
import { fullName } from "@/lib/people";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import SignOutButton from "@/components/SignOutButton";
import { RoleBadge } from "@/components/Badges";
import { changePasswordAction } from "./actions";

export default async function AccountPage() {
  const user = await requireUser();
  return (
    <div className="mx-auto min-h-screen max-w-md px-4 py-6">
      <Link href={homeFor(user.role)} className="text-sm text-gray-500">
        ← กลับ
      </Link>
      <div className="card mt-3 p-5">
        <div className="text-lg font-bold text-gray-900">{fullName(user)}</div>
        <div className="mt-1 flex items-center gap-2 text-sm text-gray-500">
          {user.employeeCode} <RoleBadge role={user.role} />
        </div>
        {can.doFieldWork(user.role) && can.useOffice(user.role) && (
          <div className="mt-3 flex gap-2">
            <Link href="/m" className="btn btn-secondary text-xs">
              📱 งานของฉัน
            </Link>
            <Link href="/dashboard" className="btn btn-secondary text-xs">
              🖥️ หน้าออฟฟิศ
            </Link>
          </div>
        )}
      </div>

      <ActionForm action={changePasswordAction} resetOnSuccess className="card mt-4 space-y-3 p-5">
        <h2 className="section-title">เปลี่ยนรหัสผ่าน</h2>
        <input name="current" type="password" required autoComplete="current-password" placeholder="รหัสผ่านปัจจุบัน" className="input py-3" />
        <input name="next" type="password" required minLength={8} autoComplete="new-password" placeholder="รหัสผ่านใหม่ (อย่างน้อย 8 ตัว)" className="input py-3" />
        <input name="confirm" type="password" required minLength={8} autoComplete="new-password" placeholder="ยืนยันรหัสผ่านใหม่" className="input py-3" />
        <SubmitButton className="btn btn-primary w-full">บันทึกรหัสผ่านใหม่</SubmitButton>
      </ActionForm>

      <div className="mt-6 text-center">
        <SignOutButton className="btn btn-danger w-full" />
      </div>
    </div>
  );
}
