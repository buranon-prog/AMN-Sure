"use server";

import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { str } from "@/lib/form";

export async function changePasswordAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  return run(async () => {
    const user = await actionUser();
    const current = str(fd, "current");
    const next = str(fd, "next");
    if (!(await bcrypt.compare(current, user.passwordHash))) throw new UserError("รหัสผ่านปัจจุบันไม่ถูกต้อง");
    if (next.length < 8) throw new UserError("รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร");
    if (next !== str(fd, "confirm")) throw new UserError("ยืนยันรหัสผ่านใหม่ไม่ตรงกัน");
    if (next === current) throw new UserError("รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม");
    await prisma.employee.update({
      where: { id: user.id },
      data: { passwordHash: await bcrypt.hash(next, 10), passwordChangedAt: new Date() },
    });
    return { ok: true, message: "เปลี่ยนรหัสผ่านเรียบร้อย" };
  });
}
