"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan, requireSession } from "@/lib/rbac";

/**
 * Sells a stock item (owned stock or consignment stock). This is the single
 * place where a sale turns into a financial transaction, and — for
 * consignment items — a payable to the consignor.
 */
export async function sellStockItemAction(stockItemId: string, formData: FormData) {
  const session = await requireSession();

  const stockItem = await prisma.stockItem.findUniqueOrThrow({
    where: { id: stockItemId },
    include: { consignor: true },
  });

  await assertCan(stockItem.source === "CONSIGNMENT" ? "CONSIGNMENT" : "INVENTORY", "edit");

  if (stockItem.status !== "IN_STOCK") {
    throw new Error("รายการนี้ถูกขายหรือคืนไปแล้ว");
  }

  const salePrice = Number(formData.get("salePrice"));
  const customerId = String(formData.get("customerId") || "") || null;
  const paymentStatus = String(formData.get("paymentStatus") || "PAID");
  const notes = String(formData.get("notes") || "") || null;

  if (!salePrice || salePrice <= 0) throw new Error("กรุณากรอกราคาขายให้ถูกต้อง");

  let consignorPayout: number | null = null;
  if (stockItem.source === "CONSIGNMENT") {
    if (stockItem.agreedPayout != null) {
      consignorPayout = stockItem.agreedPayout;
    } else if (stockItem.consignor?.commissionRate != null) {
      consignorPayout = salePrice * (1 - stockItem.consignor.commissionRate / 100);
    }
  }

  await prisma.$transaction(async (tx) => {
    const sale = await tx.sale.create({
      data: {
        stockItemId,
        customerId,
        salePrice,
        consignorPayout: consignorPayout ?? undefined,
        paymentStatus,
        notes: notes ?? undefined,
        soldById: session.user.id,
      },
    });

    await tx.stockItem.update({ where: { id: stockItemId }, data: { status: "SOLD" } });

    await tx.stockMovement.create({
      data: {
        stockItemId,
        type: "OUT",
        quantity: stockItem.quantity,
        unitPrice: salePrice,
        counterparty: customerId ? undefined : "ลูกค้าหน้าร้าน",
        referenceNo: `SALE-${sale.id.slice(-8)}`,
        createdById: session.user.id,
        notes: "ขายออกจากสต็อก",
      },
    });

    await tx.financeTransaction.create({
      data: {
        type: "INCOME",
        category: "SALES",
        amount: salePrice,
        description: `ขาย ${stockItem.serialNumber ?? stockItem.id}`,
        saleId: sale.id,
        createdById: session.user.id,
      },
    });

    if (consignorPayout != null && consignorPayout > 0) {
      await tx.financeTransaction.create({
        data: {
          type: "EXPENSE",
          category: "CONSIGNMENT_PAYOUT",
          amount: consignorPayout,
          description: `จ่ายเจ้าของฝากขาย (${stockItem.consignor?.name ?? "-"})`,
          saleId: sale.id,
          createdById: session.user.id,
        },
      });
    }
  });

  revalidatePath("/inventory");
  revalidatePath("/consignment");
  revalidatePath("/finance");
  revalidatePath("/dashboard");
}
