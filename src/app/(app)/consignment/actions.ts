"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createConsignorAction(formData: FormData) {
  await assertCan("CONSIGNMENT", "edit");

  const name = String(formData.get("name") || "").trim();
  const contactPerson = String(formData.get("contactPerson") || "") || null;
  const phone = String(formData.get("phone") || "") || null;
  const email = String(formData.get("email") || "") || null;
  const commissionRate = formData.get("commissionRate") ? Number(formData.get("commissionRate")) : null;
  const notes = String(formData.get("notes") || "") || null;

  if (!name) throw new Error("กรุณากรอกชื่อผู้ฝากขาย");

  await prisma.consignor.create({
    data: {
      name,
      contactPerson: contactPerson ?? undefined,
      phone: phone ?? undefined,
      email: email ?? undefined,
      commissionRate: commissionRate ?? undefined,
      notes: notes ?? undefined,
    },
  });

  revalidatePath("/consignment");
}

export async function receiveConsignmentStockAction(formData: FormData) {
  const session = await assertCan("CONSIGNMENT", "edit");

  const productId = String(formData.get("productId") || "");
  const consignorId = String(formData.get("consignorId") || "");
  const quantity = Number(formData.get("quantity") || 1);
  const listPrice = Number(formData.get("listPrice") || 0);
  const agreedPayout = formData.get("agreedPayout") ? Number(formData.get("agreedPayout")) : null;
  const serialNumber = String(formData.get("serialNumber") || "") || null;
  const condition = String(formData.get("condition") || "USED");
  const notes = String(formData.get("notes") || "") || null;

  if (!productId || !consignorId || !quantity || listPrice <= 0) {
    throw new Error("กรุณาเลือกสินค้า ผู้ฝากขาย และกรอกจำนวน/ราคาขายให้ถูกต้อง");
  }

  const stockItem = await prisma.stockItem.create({
    data: {
      productId,
      consignorId,
      source: "CONSIGNMENT",
      serialNumber: serialNumber ?? undefined,
      condition,
      quantity,
      costPrice: 0,
      listPrice,
      agreedPayout: agreedPayout ?? undefined,
      notes: notes ?? undefined,
    },
  });

  await prisma.stockMovement.create({
    data: {
      stockItemId: stockItem.id,
      type: "IN",
      quantity,
      counterparty: undefined,
      referenceNo: `CSG-${stockItem.id.slice(-8)}`,
      createdById: session.user.id,
      notes: "รับสินค้าฝากขายเข้าสต็อก",
    },
  });

  revalidatePath("/consignment");
}
