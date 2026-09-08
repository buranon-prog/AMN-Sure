"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createCustomerAction(formData: FormData) {
  await assertCan("CRM", "edit");

  const name = String(formData.get("name") || "").trim();
  const type = String(formData.get("type") || "CLINIC");
  const phone = String(formData.get("phone") || "") || null;
  const email = String(formData.get("email") || "") || null;
  const address = String(formData.get("address") || "") || null;
  const industry = String(formData.get("industry") || "") || null;

  if (!name) throw new Error("กรุณากรอกชื่อลูกค้า");

  await prisma.customer.create({
    data: {
      name,
      type,
      phone: phone ?? undefined,
      email: email ?? undefined,
      address: address ?? undefined,
      industry: industry ?? undefined,
    },
  });

  revalidatePath("/crm");
}

export async function updateLoyaltyAction(customerId: string, formData: FormData) {
  await assertCan("CRM", "edit");
  const loyaltyTier = String(formData.get("loyaltyTier") || "BRONZE");
  await prisma.customer.update({ where: { id: customerId }, data: { loyaltyTier } });
  revalidatePath(`/crm/${customerId}`);
  revalidatePath("/crm");
}

export async function updateCustomerNotesAction(customerId: string, formData: FormData) {
  await assertCan("CRM", "edit");
  const notes = String(formData.get("notes") || "") || null;
  await prisma.customer.update({ where: { id: customerId }, data: { notes: notes ?? undefined } });
  revalidatePath(`/crm/${customerId}`);
}

export async function createInteractionAction(customerId: string, formData: FormData) {
  const session = await assertCan("CRM", "edit");

  const type = String(formData.get("type") || "INTEREST");
  const interestProduct = String(formData.get("interestProduct") || "") || null;
  const probability = formData.get("probability") ? Number(formData.get("probability")) : null;
  const notes = String(formData.get("notes") || "") || null;
  const followUpDateRaw = String(formData.get("followUpDate") || "");

  await prisma.customerInteraction.create({
    data: {
      customerId,
      type,
      interestProduct: interestProduct ?? undefined,
      probability: probability ?? undefined,
      notes: notes ?? undefined,
      followUpDate: followUpDateRaw ? new Date(followUpDateRaw) : undefined,
      createdById: session.user.id,
    },
  });

  revalidatePath(`/crm/${customerId}`);
}
