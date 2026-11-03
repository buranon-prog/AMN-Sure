import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/session";
import { homeFor } from "@/lib/permissions";
import LoginForm from "./LoginForm";

export default async function LoginPage() {
  const user = await getCurrentUser();
  if (user) redirect(homeFor(user.role));

  return (
    <div className="flex min-h-screen items-center justify-center px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl text-white">
            🔧
          </div>
          <div className="text-2xl font-bold text-gray-900">AMN Service</div>
          <p className="mt-1 text-sm text-gray-500">ระบบงานบริการ — เข้าสู่ระบบด้วยรหัสพนักงาน</p>
        </div>
        <LoginForm />
        <p className="mt-4 text-center text-xs text-gray-400">
          ลืมรหัสผ่าน? ติดต่อผู้จัดการเพื่อรีเซ็ตรหัสผ่าน
        </p>
      </div>
    </div>
  );
}
