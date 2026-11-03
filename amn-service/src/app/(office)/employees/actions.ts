"use server";

import bcrypt from "bcryptjs";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";
import { ROLES } from "@/lib/constants";
import { oneOf, optStr, required, str } from "@/lib/form";

import { MIN_PASSWORD } from "@/lib/constants";

function checkPassword(pw: string) {
  if (pw.length < MIN_PASSWORD) throw new UserError(`รหัสผ่านต้องมีอย่างน้อย ${MIN_PASSWORD} ตัวอักษร`);
}

function profileFields(fd: FormData) {
  return {
    firstName: required(fd, "firstName", "ชื่อ"),
    lastName: required(fd, "lastName", "นามสกุล"),
    nickname: optStr(fd, "nickname"),
    phone: optStr(fd, "phone"),
    email: optStr(fd, "email"),
    position: optStr(fd, "position"),
    role: oneOf(str(fd, "role") || "TECHNICIAN", ROLES, "บทบาท"),
  };
}

/** Never leave the company without an active manager account. */
async function assertKeepsAManager(employeeId: string, nextRole: string, nextActive: boolean) {
  if (nextRole === "MANAGER" && nextActive) return;
  const others = await prisma.employee.count({ where: { role: "MANAGER", active: true, id: { not: employeeId } } });
  if (others === 0) throw new UserError("ต้องมีผู้จัดการที่ใช้งานได้อย่างน้อย 1 คน");
}

export async function createEmployeeAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  let id = "";
  const res = await run(async () => {
    await actionUser(can.manageEmployees);
    const employeeCode = required(fd, "employeeCode", "รหัสพนักงาน").toUpperCase();
    if (!/^[A-Z0-9_-]{2,20}$/.test(employeeCode)) {
      throw new UserError("รหัสพนักงานใช้ได้เฉพาะ A-Z, 0-9, - และ _ (2–20 ตัว)");
    }
    const password = str(fd, "password");
    checkPassword(password);
    const created = await prisma.employee.create({
      data: { ...profileFields(fd), employeeCode, passwordHash: await bcrypt.hash(password, 10) },
    });
    id = created.id;
  });
  if (res.error) return res;
  revalidatePath("/employees");
  redirect(`/employees/${id}?created=1`);
}

export async function updateEmployeeAction(employeeId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageEmployees);
    const emp = await prisma.employee.findUnique({ where: { id: employeeId } });
    if (!emp) throw new UserError("ไม่พบพนักงาน");
    const data = profileFields(fd);
    await assertKeepsAManager(employeeId, data.role, emp.active);
    if (!can.doFieldWork(data.role) && can.doFieldWork(emp.role)) {
      const open = await prisma.jobAssignment.count({
        where: { employeeId, job: { status: { in: ["ASSIGNED", "IN_PROGRESS", "ON_HOLD"] } } },
      });
      if (open > 0) throw new UserError(`พนักงานคนนี้ยังมีงานค้าง ${open} งาน — ให้หัวหน้าช่างโอนงานก่อนเปลี่ยนบทบาท`);
    }
    await prisma.employee.update({ where: { id: employeeId }, data });
    return { ok: true, message: "บันทึกแล้ว — สิทธิ์ใหม่มีผลทันที" };
  });
  if (res.ok) {
    revalidatePath(`/employees/${employeeId}`);
    revalidatePath("/employees");
  }
  return res;
}

export async function setActiveAction(employeeId: string, active: boolean, _prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    const me = await actionUser(can.manageEmployees);
    if (me.id === employeeId && !active) throw new UserError("ปิดบัญชีของตัวเองไม่ได้");
    const emp = await prisma.employee.findUnique({ where: { id: employeeId } });
    if (!emp) throw new UserError("ไม่พบพนักงาน");
    await assertKeepsAManager(employeeId, emp.role, active);
    await prisma.employee.update({ where: { id: employeeId }, data: { active } });
    return { ok: true, message: active ? "เปิดใช้งานบัญชีแล้ว" : "ปิดบัญชีแล้ว — พนักงานคนนี้เข้าสู่ระบบไม่ได้ทันที" };
  });
  if (res.ok) {
    revalidatePath(`/employees/${employeeId}`);
    revalidatePath("/employees");
  }
  return res;
}

export async function resetPasswordAction(employeeId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageEmployees);
    const password = str(fd, "password");
    checkPassword(password);
    await prisma.employee.update({
      where: { id: employeeId },
      data: { passwordHash: await bcrypt.hash(password, 10), failedLoginCount: 0, lockedUntil: null, passwordChangedAt: null },
    });
    return { ok: true, message: "รีเซ็ตรหัสผ่านแล้ว แจ้งรหัสใหม่ให้พนักงาน และให้เปลี่ยนเองหลังเข้าสู่ระบบ" };
  });
  return res;
}

export async function unlockAction(employeeId: string, _prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageEmployees);
    await prisma.employee.update({ where: { id: employeeId }, data: { failedLoginCount: 0, lockedUntil: null } });
    return { ok: true, message: "ปลดล็อกแล้ว" };
  });
  if (res.ok) revalidatePath(`/employees/${employeeId}`);
  return res;
}

