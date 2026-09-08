import { NextRequest } from "next/server";
import { auth } from "@/auth";
import { canView } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { toCsv, csvResponse } from "@/lib/csv";

// A combined per-employee report: attendance + KPI results + plans in one
// downloadable file, each as its own labeled section.
export async function GET(req: NextRequest) {
  const session = await auth();
  if (!session?.user) return new Response("Unauthorized", { status: 401 });
  if (!canView(session.user.role, session.user.permissions, "HR")) {
    return new Response("Forbidden — คุณไม่มีสิทธิ์ดูข้อมูลส่วนนี้", { status: 403 });
  }

  const employeeId = req.nextUrl.searchParams.get("employeeId");
  if (!employeeId) return new Response("Missing employeeId", { status: 400 });

  const employee = await prisma.employee.findUnique({ where: { id: employeeId } });
  if (!employee) return new Response("Not found", { status: 404 });

  const [attendances, kpiRecords, plans] = await Promise.all([
    prisma.attendance.findMany({ where: { employeeId }, orderBy: { date: "desc" } }),
    prisma.kpiRecord.findMany({ where: { employeeId }, include: { kpiDefinition: true }, orderBy: { createdAt: "desc" } }),
    prisma.employeePlan.findMany({ where: { employeeId }, orderBy: { createdAt: "desc" } }),
  ]);

  const attendanceCsv = toCsv(attendances as unknown as Record<string, unknown>[], [
    { key: "date", label: "วันที่", value: (r) => r.date },
    { key: "status", label: "สถานะ", value: (r) => r.status },
    { key: "checkIn", label: "เวลาเข้า", value: (r) => r.checkIn },
    { key: "checkOut", label: "เวลาออก", value: (r) => r.checkOut },
    { key: "notes", label: "หมายเหตุ", value: (r) => r.notes },
  ]);

  const kpiCsv = toCsv(kpiRecords as unknown as Record<string, unknown>[], [
    { key: "kpi", label: "ตัวชี้วัด", value: (r) => (r.kpiDefinition as { name: string })?.name },
    { key: "period", label: "รอบ", value: (r) => r.period },
    { key: "actualValue", label: "ผลลัพธ์", value: (r) => r.actualValue },
    { key: "targetValue", label: "เป้าหมาย", value: (r) => (r.kpiDefinition as { targetValue: number | null })?.targetValue },
  ]);

  const plansCsv = toCsv(plans as unknown as Record<string, unknown>[], [
    { key: "title", label: "แผนงาน", value: (r) => r.title },
    { key: "period", label: "รอบ", value: (r) => r.period },
    { key: "dueDate", label: "กำหนดเสร็จ", value: (r) => r.dueDate },
    { key: "status", label: "สถานะ", value: (r) => r.status },
  ]);

  const combined = [
    `รายงานพนักงาน: ${employee.fullName} (${employee.employeeCode})`,
    "",
    "=== การลงเวลาทำงาน ===",
    attendanceCsv,
    "",
    "=== ผล KPI ===",
    kpiCsv,
    "",
    "=== แผนงาน ===",
    plansCsv,
  ].join("\n");

  return csvResponse(`employee-report-${employee.employeeCode}`, combined);
}
