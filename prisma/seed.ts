import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function main() {
  const managerPassword = process.env.SEED_MANAGER_PASSWORD || "ChangeMe123!";
  const employeePassword = process.env.SEED_EMPLOYEE_PASSWORD || "ChangeMe123!";

  const manager = await prisma.user.upsert({
    where: { email: "manager@amnsure.local" },
    update: {},
    create: {
      email: "manager@amnsure.local",
      name: "ผู้จัดการระบบ",
      role: "MANAGER",
      passwordHash: await bcrypt.hash(managerPassword, 10),
    },
  });

  const employee = await prisma.user.upsert({
    where: { email: "employee@amnsure.local" },
    update: {},
    create: {
      email: "employee@amnsure.local",
      name: "พนักงานตัวอย่าง",
      role: "EMPLOYEE",
      passwordHash: await bcrypt.hash(employeePassword, 10),
    },
  });

  // Sample employee gets full access to Inventory/Consignment, view-only on
  // Finance/CRM, and nothing on HR/Supply Chain/Strategy — a realistic
  // "warehouse & sales" access profile to demonstrate the permission model.
  const employeePerms: { module: string; canView: boolean; canEdit: boolean }[] = [
    { module: "INVENTORY", canView: true, canEdit: true },
    { module: "CONSIGNMENT", canView: true, canEdit: true },
    { module: "FINANCE", canView: true, canEdit: false },
    { module: "HR", canView: false, canEdit: false },
    { module: "SUPPLY_CHAIN", canView: false, canEdit: false },
    { module: "CRM", canView: true, canEdit: true },
    { module: "STRATEGY", canView: false, canEdit: false },
  ];

  for (const p of employeePerms) {
    await prisma.userPermission.upsert({
      where: { userId_module: { userId: employee.id, module: p.module } },
      update: { canView: p.canView, canEdit: p.canEdit },
      create: { userId: employee.id, module: p.module, canView: p.canView, canEdit: p.canEdit },
    });
  }

  await prisma.employee.upsert({
    where: { employeeCode: "EMP-001" },
    update: {},
    create: {
      employeeCode: "EMP-001",
      fullName: "พนักงานตัวอย่าง",
      position: "เจ้าหน้าที่คลังสินค้า",
      department: "คลังสินค้า",
      userId: employee.id,
    },
  });

  const product = await prisma.product.upsert({
    where: { sku: "US-GE-VOLUSON-E8" },
    update: {},
    create: {
      sku: "US-GE-VOLUSON-E8",
      name: "เครื่องอัลตราซาวด์ GE Voluson E8",
      category: "เครื่องอัลตราซาวด์",
      brand: "GE Healthcare",
      condition: "USED",
      description: "เครื่องอัลตราซาวด์มือสอง สภาพดี ผ่านการตรวจเช็คแล้ว",
    },
  });

  await prisma.stockItem.upsert({
    where: { id: "seed-stock-item-1" },
    update: {},
    create: {
      id: "seed-stock-item-1",
      productId: product.id,
      source: "PURCHASE",
      serialNumber: "SN-2024-0001",
      condition: "USED",
      quantity: 1,
      costPrice: 350000,
      listPrice: 480000,
      supplierName: "บริษัท เมดิคอล อิมพอร์ต จำกัด",
    },
  });

  const customer = await prisma.customer.upsert({
    where: { id: "seed-customer-1" },
    update: {},
    create: {
      id: "seed-customer-1",
      name: "คลินิกสุขภาพดี",
      type: "CLINIC",
      phone: "02-000-0000",
      loyaltyTier: "SILVER",
    },
  });

  await prisma.kpiDefinition.upsert({
    where: { id: "seed-kpi-1" },
    update: {},
    create: {
      id: "seed-kpi-1",
      name: "ยอดขายต่อเดือน",
      unit: "บาท",
      targetValue: 500000,
      period: "MONTHLY",
    },
  });

  console.log("Seed complete.");
  console.log(`Manager login: manager@amnsure.local / ${managerPassword}`);
  console.log(`Employee login: employee@amnsure.local / ${employeePassword}`);
  console.log(`Sample customer id: ${customer.id}`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
