"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createShipmentAction(formData: FormData) {
  await assertCan("SUPPLY_CHAIN", "edit");

  const referenceNo = String(formData.get("referenceNo") || "").trim();
  const supplierName = String(formData.get("supplierName") || "") || null;
  const origin = String(formData.get("origin") || "") || null;
  const destination = String(formData.get("destination") || "") || null;
  const carrier = String(formData.get("carrier") || "") || null;
  const trackingNumber = String(formData.get("trackingNumber") || "") || null;
  const status = String(formData.get("status") || "ORDERED");
  const expectedDateRaw = String(formData.get("expectedDate") || "");
  const notes = String(formData.get("notes") || "") || null;

  if (!referenceNo) throw new Error("กรุณากรอกเลขที่อ้างอิงการขนส่ง");

  await prisma.shipment.create({
    data: {
      referenceNo,
      supplierName: supplierName ?? undefined,
      origin: origin ?? undefined,
      destination: destination ?? undefined,
      carrier: carrier ?? undefined,
      trackingNumber: trackingNumber ?? undefined,
      status,
      expectedDate: expectedDateRaw ? new Date(expectedDateRaw) : undefined,
      notes: notes ?? undefined,
    },
  });

  revalidatePath("/supply-chain");
}

export async function updateShipmentStatusAction(shipmentId: string, formData: FormData) {
  await assertCan("SUPPLY_CHAIN", "edit");
  const status = String(formData.get("status") || "");
  if (!status) throw new Error("กรุณาเลือกสถานะ");

  await prisma.shipment.update({
    where: { id: shipmentId },
    data: {
      status,
      actualDate: status === "DELIVERED" ? new Date() : undefined,
    },
  });

  revalidatePath("/supply-chain");
}
