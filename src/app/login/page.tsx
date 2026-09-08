import { Suspense } from "react";
import LoginForm from "./LoginForm";

export default function LoginPage() {
  return (
    <div className="min-h-screen flex items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <div className="text-center mb-6">
          <div className="text-2xl font-bold text-brand-700">AMN-Sure</div>
          <p className="text-sm text-gray-500 mt-1">
            ระบบ ERP ภายในองค์กร — สำหรับพนักงานเท่านั้น
          </p>
        </div>
        <Suspense fallback={<div className="card p-6 text-sm text-gray-400">กำลังโหลด...</div>}>
          <LoginForm />
        </Suspense>
        <p className="text-xs text-gray-400 text-center mt-4">
          ระบบนี้ใช้งานได้เฉพาะภายในองค์กร บัญชีถูกสร้างโดยผู้ดูแลระบบเท่านั้น
        </p>
      </div>
    </div>
  );
}
