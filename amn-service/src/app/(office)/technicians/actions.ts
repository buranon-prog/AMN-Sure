"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";

/** Saves the whole skill sheet for one technician (level 0 = remove). */
export async function saveSkillsAction(employeeId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageSkills);
    const tech = await prisma.employee.findUnique({ where: { id: employeeId } });
    if (!tech) throw new UserError("ไม่พบพนักงาน");

    const ops = [];
    for (const [key, raw] of fd.entries()) {
      if (!key.startsWith("skill_")) continue;
      const machineModelId = key.slice("skill_".length);
      const level = Number(raw);
      if (!Number.isInteger(level) || level < 0 || level > 5) throw new UserError("ระดับความถนัดต้องอยู่ระหว่าง 0–5");
      const where = { employeeId_machineModelId: { employeeId, machineModelId } };
      ops.push(
        level === 0
          ? prisma.employeeSkill.deleteMany({ where: { employeeId, machineModelId } })
          : prisma.employeeSkill.upsert({ where, create: { employeeId, machineModelId, level }, update: { level } })
      );
    }
    await prisma.$transaction(ops);
    return { ok: true, message: "บันทึกความถนัดแล้ว" };
  });
  if (res.ok) {
    revalidatePath(`/technicians/${employeeId}`);
    revalidatePath("/technicians");
  }
  return res;
}
