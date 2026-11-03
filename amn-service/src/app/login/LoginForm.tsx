"use client";

import { useFormState } from "react-dom";
import SubmitButton from "@/components/SubmitButton";
import { loginAction } from "./actions";

export default function LoginForm() {
  const [state, formAction] = useFormState(loginAction, {});
  return (
    <form action={formAction} className="card space-y-4 p-6">
      <div>
        <label className="label" htmlFor="employeeCode">
          รหัสพนักงาน
        </label>
        <input
          id="employeeCode"
          name="employeeCode"
          required
          autoComplete="username"
          autoCapitalize="characters"
          autoCorrect="off"
          spellCheck={false}
          className="input py-3 text-base uppercase"
          placeholder="เช่น TEC001"
        />
      </div>
      <div>
        <label className="label" htmlFor="password">
          รหัสผ่าน
        </label>
        <input
          id="password"
          name="password"
          type="password"
          required
          autoComplete="current-password"
          className="input py-3 text-base"
        />
      </div>
      {state.error && (
        <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
          {state.error}
        </p>
      )}
      <SubmitButton className="btn btn-primary btn-lg w-full" pendingText="กำลังเข้าสู่ระบบ…">
        เข้าสู่ระบบ
      </SubmitButton>
    </form>
  );
}
