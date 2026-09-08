import { redirect } from "next/navigation";
import { auth } from "@/auth";
import type { ModuleName } from "@/lib/constants";

export type Action = "view" | "edit";

/**
 * Loads the current session and requires it to exist, redirecting to
 * /login otherwise. Use in server components / server actions.
 */
export async function requireSession() {
  const session = await auth();
  if (!session?.user) redirect("/login");
  return session;
}

/**
 * Managers always pass. Employees are checked against their per-module
 * UserPermission row. Redirects to /dashboard with no access if denied
 * (server components) — for server actions, use `can()` and throw instead.
 */
export async function requireModule(module: ModuleName, action: Action = "view") {
  const session = await requireSession();
  if (session.user.role === "MANAGER") return session;

  const perm = session.user.permissions[module];
  const allowed = action === "view" ? perm?.canView : perm?.canEdit;
  if (!allowed) redirect("/dashboard?denied=" + module);
  return session;
}

/** Non-redirecting check for use in server actions (throws instead). */
export async function assertCan(module: ModuleName, action: Action = "edit") {
  const session = await requireSession();
  if (session.user.role === "MANAGER") return session;

  const perm = session.user.permissions[module];
  const allowed = action === "view" ? perm?.canView : perm?.canEdit;
  if (!allowed) {
    throw new Error(`ไม่มีสิทธิ์เข้าถึงส่วน ${module} (${action})`);
  }
  return session;
}

export async function requireManager() {
  const session = await requireSession();
  if (session.user.role !== "MANAGER") redirect("/dashboard?denied=ADMIN");
  return session;
}

export async function assertManager() {
  const session = await requireSession();
  if (session.user.role !== "MANAGER") throw new Error("เฉพาะผู้จัดการเท่านั้นที่ทำรายการนี้ได้");
  return session;
}

export function canView(
  role: string,
  permissions: Partial<Record<ModuleName, { canView: boolean; canEdit: boolean }>>,
  module: ModuleName
) {
  if (role === "MANAGER") return true;
  return !!permissions[module]?.canView;
}

export function canEdit(
  role: string,
  permissions: Partial<Record<ModuleName, { canView: boolean; canEdit: boolean }>>,
  module: ModuleName
) {
  if (role === "MANAGER") return true;
  return !!permissions[module]?.canEdit;
}
