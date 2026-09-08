"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertCan } from "@/lib/rbac";

export async function createEmployeeAction(formData: FormData) {
  await assertCan("HR", "edit");

  const employeeCode = String(formData.get("employeeCode") || "").trim();
  const fullName = String(formData.get("fullName") || "").trim();
  const position = String(formData.get("position") || "").trim();
  const department = String(formData.get("department") || "").trim();
  const phone = String(formData.get("phone") || "") || null;
  const email = String(formData.get("email") || "") || null;
  const salary = formData.get("salary") ? Number(formData.get("salary")) : null;

  if (!employeeCode || !fullName || !position || !department) {
    throw new Error("กรุณากรอกรหัสพนักงาน ชื่อ ตำแหน่ง และแผนกให้ครบ");
  }

  await prisma.employee.create({
    data: {
      employeeCode,
      fullName,
      position,
      department,
      phone: phone ?? undefined,
      email: email ?? undefined,
      salary: salary ?? undefined,
    },
  });

  revalidatePath("/hr");
}

export async function setEmployeeStatusAction(employeeId: string, status: string) {
  await assertCan("HR", "edit");
  await prisma.employee.update({ where: { id: employeeId }, data: { status } });
  revalidatePath("/hr");
}

export async function recordAttendanceAction(formData: FormData) {
  await assertCan("HR", "edit");

  const employeeId = String(formData.get("employeeId") || "");
  const dateRaw = String(formData.get("date") || "");
  const status = String(formData.get("status") || "PRESENT");
  const checkInRaw = String(formData.get("checkIn") || "");
  const checkOutRaw = String(formData.get("checkOut") || "");
  const notes = String(formData.get("notes") || "") || null;

  if (!employeeId || !dateRaw) throw new Error("กรุณาเลือกพนักงานและวันที่");

  const date = new Date(dateRaw);
  const checkIn = checkInRaw ? new Date(`${dateRaw}T${checkInRaw}`) : null;
  const checkOut = checkOutRaw ? new Date(`${dateRaw}T${checkOutRaw}`) : null;

  await prisma.attendance.upsert({
    where: { employeeId_date: { employeeId, date } },
    create: { employeeId, date, status, checkIn, checkOut, notes: notes ?? undefined },
    update: { status, checkIn, checkOut, notes: notes ?? undefined },
  });

  revalidatePath("/hr/attendance");
}

export async function createKpiDefinitionAction(formData: FormData) {
  await assertCan("HR", "edit");

  const name = String(formData.get("name") || "").trim();
  const description = String(formData.get("description") || "") || null;
  const unit = String(formData.get("unit") || "") || null;
  const targetValue = formData.get("targetValue") ? Number(formData.get("targetValue")) : null;
  const period = String(formData.get("period") || "MONTHLY");

  if (!name) throw new Error("กรุณากรอกชื่อ KPI");

  await prisma.kpiDefinition.create({
    data: { name, description: description ?? undefined, unit: unit ?? undefined, targetValue: targetValue ?? undefined, period },
  });

  revalidatePath("/hr/kpi");
}

export async function recordKpiAction(formData: FormData) {
  const session = await assertCan("HR", "edit");

  const kpiDefinitionId = String(formData.get("kpiDefinitionId") || "");
  const employeeId = String(formData.get("employeeId") || "");
  const period = String(formData.get("period") || "");
  const actualValue = Number(formData.get("actualValue") || 0);
  const note = String(formData.get("note") || "") || null;

  if (!kpiDefinitionId || !employeeId || !period) {
    throw new Error("กรุณาเลือก KPI พนักงาน และรอบการประเมิน");
  }

  await prisma.kpiRecord.upsert({
    where: { kpiDefinitionId_employeeId_period: { kpiDefinitionId, employeeId, period } },
    create: { kpiDefinitionId, employeeId, period, actualValue, note: note ?? undefined, recordedById: session.user.id },
    update: { actualValue, note: note ?? undefined, recordedById: session.user.id },
  });

  revalidatePath("/hr/kpi");
}

export async function createOperationCostAction(formData: FormData) {
  const session = await assertCan("HR", "edit");

  const category = String(formData.get("category") || "").trim();
  const description = String(formData.get("description") || "") || null;
  const amount = Number(formData.get("amount") || 0);
  const costDateRaw = String(formData.get("costDate") || "");

  if (!category || !amount) throw new Error("กรุณากรอกหมวดหมู่และจำนวนเงิน");

  await prisma.$transaction(async (tx) => {
    await tx.operationCost.create({
      data: {
        category,
        description: description ?? undefined,
        amount,
        costDate: costDateRaw ? new Date(costDateRaw) : undefined,
        createdById: session.user.id,
      },
    });
    await tx.financeTransaction.create({
      data: {
        type: "EXPENSE",
        category: "OPERATION_COST",
        amount,
        description: `${category}${description ? " - " + description : ""}`,
        transactionDate: costDateRaw ? new Date(costDateRaw) : undefined,
        createdById: session.user.id,
      },
    });
  });

  revalidatePath("/hr/costs");
  revalidatePath("/finance");
}

export async function createPlanAction(formData: FormData) {
  const session = await assertCan("HR", "edit");

  const employeeId = String(formData.get("employeeId") || "");
  const title = String(formData.get("title") || "").trim();
  const description = String(formData.get("description") || "") || null;
  const period = String(formData.get("period") || "").trim();
  const dueDateRaw = String(formData.get("dueDate") || "");
  const priority = String(formData.get("priority") || "MEDIUM");

  if (!employeeId || !title || !period) {
    throw new Error("กรุณาเลือกพนักงาน กรอกชื่อแผนงาน และรอบเวลาให้ครบ");
  }

  await prisma.employeePlan.create({
    data: {
      employeeId,
      title,
      description: description ?? undefined,
      period,
      dueDate: dueDateRaw ? new Date(dueDateRaw) : undefined,
      priority,
      createdById: session.user.id,
    },
  });

  revalidatePath("/hr/planning");
}

export async function updatePlanStatusAction(planId: string, formData: FormData) {
  await assertCan("HR", "edit");
  const status = String(formData.get("status") || "");
  if (!status) throw new Error("กรุณาเลือกสถานะ");
  await prisma.employeePlan.update({ where: { id: planId }, data: { status } });
  revalidatePath("/hr/planning");
  revalidatePath("/hr/reports");
}
