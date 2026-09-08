"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createTransactionAction(formData: FormData) {
  const session = await assertCan("FINANCE", "edit");

  const type = String(formData.get("type") || "EXPENSE");
  const category = String(formData.get("category") || "OTHER");
  const amount = Number(formData.get("amount") || 0);
  const description = String(formData.get("description") || "") || null;
  const transactionDateRaw = String(formData.get("transactionDate") || "");

  if (!amount || amount <= 0) throw new Error("กรุณากรอกจำนวนเงินให้ถูกต้อง");

  await prisma.financeTransaction.create({
    data: {
      type,
      category,
      amount,
      description: description ?? undefined,
      transactionDate: transactionDateRaw ? new Date(transactionDateRaw) : undefined,
      createdById: session.user.id,
    },
  });

  revalidatePath("/finance");
  revalidatePath("/dashboard");
}
