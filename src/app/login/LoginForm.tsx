"use client";

import { useFormState, useFormStatus } from "react-dom";
import { useSearchParams } from "next/navigation";
import { loginAction, type LoginState } from "./actions";

const initialState: LoginState = {};

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <button type="submit" disabled={pending} className="btn btn-primary w-full">
      {pending ? "กำลังเข้าสู่ระบบ..." : "เข้าสู่ระบบ"}
    </button>
  );
}

export default function LoginForm() {
  const [state, formAction] = useFormState(loginAction, initialState);
  const params = useSearchParams();
  const callbackUrl = params.get("callbackUrl") || "/dashboard";

  return (
    <form action={formAction} className="card p-6 space-y-4">
      <input type="hidden" name="callbackUrl" value={callbackUrl} />
      <div>
        <label className="label" htmlFor="email">
          อีเมล
        </label>
        <input
          id="email"
          name="email"
          type="email"
          required
          className="input"
          placeholder="you@company.com"
          autoComplete="username"
        />
      </div>
      <div>
        <label className="label" htmlFor="password">
          รหัสผ่าน
        </label>
        <input id="password" name="password" type="password" required className="input" autoComplete="current-password" />
      </div>
      {state.error && <p className="text-sm text-red-600">{state.error}</p>}
      <SubmitButton />
    </form>
  );
}
