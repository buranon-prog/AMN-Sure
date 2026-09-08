"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createSourcingTargetAction(formData: FormData) {
  await assertCan("STRATEGY", "edit");

  const equipmentName = String(formData.get("equipmentName") || "").trim();
  const description = String(formData.get("description") || "") || null;
  const targetSource = String(formData.get("targetSource") || "") || null;
  const estimatedCost = formData.get("estimatedCost") ? Number(formData.get("estimatedCost")) : null;
  const priority = String(formData.get("priority") || "MEDIUM");
  const status = String(formData.get("status") || "SEEKING");
  const notes = String(formData.get("notes") || "") || null;

  if (!equipmentName) throw new Error("กรุณากรอกชื่อเครื่องมือแพทย์ที่ต้องการหา");

  await prisma.sourcingTarget.create({
    data: {
      equipmentName,
      description: description ?? undefined,
      targetSource: targetSource ?? undefined,
      estimatedCost: estimatedCost ?? undefined,
      priority,
      status,
      notes: notes ?? undefined,
    },
  });

  revalidatePath("/strategy");
}

export async function updateSourcingStatusAction(id: string, formData: FormData) {
  await assertCan("STRATEGY", "edit");
  const status = String(formData.get("status") || "");
  await prisma.sourcingTarget.update({ where: { id }, data: { status } });
  revalidatePath("/strategy");
}

export async function createMarketTargetAction(formData: FormData) {
  await assertCan("STRATEGY", "edit");

  const segmentName = String(formData.get("segmentName") || "").trim();
  const description = String(formData.get("description") || "") || null;
  const potentialValue = formData.get("potentialValue") ? Number(formData.get("potentialValue")) : null;
  const priority = String(formData.get("priority") || "MEDIUM");
  const status = String(formData.get("status") || "PLANNED");
  const notes = String(formData.get("notes") || "") || null;

  if (!segmentName) throw new Error("กรุณากรอกกลุ่มลูกค้า/พื้นที่ที่ควรขาย");

  await prisma.marketTarget.create({
    data: {
      segmentName,
      description: description ?? undefined,
      potentialValue: potentialValue ?? undefined,
      priority,
      status,
      notes: notes ?? undefined,
    },
  });

  revalidatePath("/strategy");
}

export async function updateMarketStatusAction(id: string, formData: FormData) {
  await assertCan("STRATEGY", "edit");
  const status = String(formData.get("status") || "");
  await prisma.marketTarget.update({ where: { id }, data: { status } });
  revalidatePath("/strategy");
}
