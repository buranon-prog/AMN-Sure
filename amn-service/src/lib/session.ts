import { cache } from "react";
import { redirect } from "next/navigation";
import { auth } from "@/auth";
import { prisma } from "@/lib/prisma";
import { homeFor } from "@/lib/permissions";

/**
 * The signed-in employee, re-read from the database once per request so a
 * role change or deactivation takes effect immediately (not at next login).
 */
export const getCurrentUser = cache(async () => {
  const session = await auth();
  const id = session?.user?.id;
  if (!id) return null;
  const emp = await prisma.employee.findUnique({ where: { id } });
  if (!emp || !emp.active) return null;
  return emp;
});

export type CurrentUser = NonNullable<Awaited<ReturnType<typeof getCurrentUser>>>;

/** For pages: redirects to /login, or to the user's home if not allowed. */
export async function requireUser(check?: (role: string) => boolean) {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  if (check && !check(user.role)) redirect(`${homeFor(user.role)}?denied=1`);
  return user;
}

/** Message shown to the user as-is (validation / permission problems). */
export class UserError extends Error {}

export type { ActionState } from "@/lib/types";
import type { ActionState } from "@/lib/types";

/** For server actions: throws UserError instead of redirecting. */
export async function actionUser(check?: (role: string) => boolean) {
  const user = await getCurrentUser();
  if (!user) throw new UserError("เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่");
  if (check && !check(user.role)) throw new UserError("คุณไม่มีสิทธิ์ทำรายการนี้");
  return user;
}

/**
 * Runs an action body and converts failures into an ActionState for the
 * form to display. Call redirect() *after* run() returns, never inside it.
 */
export async function run(fn: () => Promise<ActionState | void>): Promise<ActionState> {
  try {
    return (await fn()) ?? { ok: true };
  } catch (e) {
    if (e instanceof UserError) return { error: e.message };
    if (typeof e === "object" && e && (e as { code?: string }).code === "P2002") {
      return { error: "ข้อมูลนี้มีอยู่แล้วในระบบ (รหัสซ้ำ)" };
    }
    console.error(e);
    return { error: "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง" };
  }
}
