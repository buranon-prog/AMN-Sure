"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createProductAction(formData: FormData) {
  await assertCan("INVENTORY", "edit");

  const sku = String(formData.get("sku") || "").trim();
  const name = String(formData.get("name") || "").trim();
  const category = String(formData.get("category") || "").trim();
  const brand = String(formData.get("brand") || "") || null;
  const condition = String(formData.get("condition") || "USED");
  const description = String(formData.get("description") || "") || null;

  if (!sku || !name || !category) throw new Error("กรุณากรอกรหัสสินค้า ชื่อ และหมวดหมู่");

  await prisma.product.create({
    data: { sku, name, category, brand: brand ?? undefined, condition, description: description ?? undefined },
  });

  revalidatePath("/inventory");
}

export async function receiveStockAction(formData: FormData) {
  const session = await assertCan("INVENTORY", "edit");

  const productId = String(formData.get("productId") || "");
  const quantity = Number(formData.get("quantity") || 1);
  const costPrice = Number(formData.get("costPrice") || 0);
  const listPrice = Number(formData.get("listPrice") || 0);
  const serialNumber = String(formData.get("serialNumber") || "") || null;
  const condition = String(formData.get("condition") || "USED");
  const supplierName = String(formData.get("supplierName") || "") || null;
  const notes = String(formData.get("notes") || "") || null;

  if (!productId || !quantity || listPrice <= 0) {
    throw new Error("กรุณาเลือกสินค้าและกรอกจำนวน/ราคาขายให้ถูกต้อง");
  }

  await prisma.$transaction(async (tx) => {
    const stockItem = await tx.stockItem.create({
      data: {
        productId,
        source: "PURCHASE",
        serialNumber: serialNumber ?? undefined,
        condition,
        quantity,
        costPrice,
        listPrice,
        supplierName: supplierName ?? undefined,
        notes: notes ?? undefined,
      },
    });

    await tx.stockMovement.create({
      data: {
        stockItemId: stockItem.id,
        type: "IN",
        quantity,
        unitPrice: costPrice,
        counterparty: supplierName ?? undefined,
        referenceNo: `RCV-${stockItem.id.slice(-8)}`,
        createdById: session.user.id,
        notes: "รับสินค้าเข้าสต็อก (ซื้อ)",
      },
    });

    if (costPrice > 0) {
      await tx.financeTransaction.create({
        data: {
          type: "EXPENSE",
          category: "PURCHASE",
          amount: costPrice * quantity,
          description: `ซื้อสินค้าเข้าสต็อก ${serialNumber ?? stockItem.id}`,
          createdById: session.user.id,
        },
      });
    }
  });

  revalidatePath("/inventory");
  revalidatePath("/finance");
  revalidatePath("/dashboard");
}

export async function logMovementAction(formData: FormData) {
  const session = await assertCan("INVENTORY", "edit");

  const stockItemId = String(formData.get("stockItemId") || "");
  const type = String(formData.get("type") || "IN");
  const quantity = Number(formData.get("quantity") || 0);
  const unitPrice = formData.get("unitPrice") ? Number(formData.get("unitPrice")) : null;
  const counterparty = String(formData.get("counterparty") || "") || null;
  const referenceNo = String(formData.get("referenceNo") || "") || null;
  const notes = String(formData.get("notes") || "") || null;

  if (!stockItemId || !quantity) throw new Error("กรุณาเลือกสินค้าและกรอกจำนวน");

  await prisma.stockMovement.create({
    data: {
      stockItemId,
      type,
      quantity,
      unitPrice: unitPrice ?? undefined,
      counterparty: counterparty ?? undefined,
      referenceNo: referenceNo ?? undefined,
      notes: notes ?? undefined,
      createdById: session.user.id,
    },
  });

  revalidatePath("/inventory");
}
