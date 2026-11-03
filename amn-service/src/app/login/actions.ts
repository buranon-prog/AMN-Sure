"use server";

import { AuthError } from "next-auth";
import { signIn, normalizeCode } from "@/auth";
import { prisma } from "@/lib/prisma";
import type { ActionState } from "@/lib/types";

export async function loginAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const employeeCode = normalizeCode(formData.get("employeeCode"));
  const password = String(formData.get("password") ?? "");
  if (!employeeCode || !password) return { error: "กรุณากรอกรหัสพนักงานและรหัสผ่าน" };

  const emp = await prisma.employee.findUnique({
    where: { employeeCode },
    select: { lockedUntil: true },
  });
  if (emp?.lockedUntil && emp.lockedUntil > new Date()) {
    const mins = Math.ceil((emp.lockedUntil.getTime() - Date.now()) / 60000);
    return { error: `ใส่รหัสผ่านผิดหลายครั้ง บัญชีถูกล็อกชั่วคราว ลองใหม่ใน ${mins} นาที หรือแจ้งผู้จัดการให้ปลดล็อก` };
  }

  try {
    // "/" routes technicians to the mobile screens and office staff to the dashboard.
    await signIn("credentials", { employeeCode, password, redirectTo: "/" });
    return {};
  } catch (err) {
    if (err instanceof AuthError) return { error: "รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง" };
    throw err;
  }
}
