import { prisma } from "@/lib/prisma";
import { can } from "@/lib/permissions";
import { fullName } from "@/lib/people";

// "เก็บข้อมูลไว้ทั้งหมด": every table, exportable to CSV (opens in Excel / Google Sheets).

type Row = Record<string, unknown>;
type Column = { label: string; value: (r: Row) => unknown };
type Exporter = {
  key: string;
  label: string;
  description: string;
  allowed: (role: string) => boolean;
  fetch: () => Promise<Row[]>;
  columns: Column[];
};

const c = (label: string, value: (r: Row) => unknown): Column => ({ label, value });
const f = (key: string, label: string) => c(label, (r) => r[key]);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const any = (r: Row) => r as any;

export const EXPORTERS: Exporter[] = [
  {
    key: "jobs",
    label: "งานบริการ",
    description: "งานทั้งหมด พร้อมลูกค้า เครื่อง ช่าง สถานะ และสรุปการแก้ไข",
    allowed: can.exportData,
    fetch: async () =>
      (await prisma.job.findMany({
        include: {
          machine: { include: { machineModel: true, site: { include: { customer: true } } } },
          assignments: { include: { employee: true } },
          createdBy: true,
          _count: { select: { visits: true } },
        },
        orderBy: { createdAt: "desc" },
      })) as unknown as Row[],
    columns: [
      f("jobNo", "เลขที่งาน"),
      f("type", "ประเภท"),
      f("priority", "ความเร่งด่วน"),
      f("status", "สถานะ"),
      c("ลูกค้า", (r) => any(r).machine.site.customer.name),
      c("สถานที่", (r) => any(r).machine.site.name),
      c("รหัสเครื่อง", (r) => any(r).machine.machineCode),
      c("รุ่น", (r) => `${any(r).machine.machineModel.brand} ${any(r).machine.machineModel.model}`),
      f("problem", "อาการ/งาน"),
      f("scheduledAt", "นัดเข้า"),
      c("ช่าง", (r) => any(r).assignments.map((a: { employee: never }) => fullName(a.employee)).join("; ")),
      c("จำนวนครั้งที่เข้า", (r) => any(r)._count.visits),
      f("resolution", "สรุปการแก้ไข"),
      f("completedAt", "ส่งงานเมื่อ"),
      f("cancelReason", "เหตุที่ยกเลิก"),
      c("ลงงานโดย", (r) => fullName(any(r).createdBy)),
      f("createdAt", "ลงงานเมื่อ"),
    ],
  },
  {
    key: "visits",
    label: "รายงานการเข้าหน้างาน",
    description: "ทุกการเช็คอิน/สรุปงาน (ประวัติการซ่อมของเครื่องและของพนักงาน)",
    allowed: can.exportData,
    fetch: async () =>
      (await prisma.jobVisit.findMany({
        include: { job: { include: { machine: true } }, employee: true },
        orderBy: { checkInAt: "desc" },
      })) as unknown as Row[],
    columns: [
      c("เลขที่งาน", (r) => any(r).job.jobNo),
      c("รหัสเครื่อง", (r) => any(r).job.machine.machineCode),
      c("รหัสพนักงาน", (r) => any(r).employee.employeeCode),
      c("ช่าง", (r) => fullName(any(r).employee)),
      f("checkInAt", "เช็คอิน"),
      f("checkInLat", "ละติจูดเช็คอิน"),
      f("checkInLng", "ลองจิจูดเช็คอิน"),
      f("checkInDistanceM", "ห่างจากหน้างาน (ม.)"),
      f("checkOutAt", "ส่งรายงาน"),
      f("summary", "สรุปงาน"),
      f("partsUsed", "อะไหล่"),
      f("outcome", "ผล"),
      f("holdReason", "เหตุที่ Hold"),
      f("nextVisitAt", "นัดครั้งถัดไป"),
    ],
  },
  {
    key: "job-events",
    label: "ประวัติการดำเนินการ",
    description: "Audit log ของทุกงาน (ลงงาน จ่ายงาน เลื่อนนัด Hold ส่งงาน ยกเลิก)",
    allowed: can.exportData,
    fetch: async () =>
      (await prisma.jobEvent.findMany({ include: { job: true, actor: true }, orderBy: { createdAt: "desc" } })) as unknown as Row[],
    columns: [
      c("เลขที่งาน", (r) => any(r).job.jobNo),
      f("type", "เหตุการณ์"),
      f("message", "รายละเอียด"),
      c("โดย", (r) => (any(r).actor ? fullName(any(r).actor) : "ระบบ")),
      f("createdAt", "เวลา"),
    ],
  },
  {
    key: "customers",
    label: "ลูกค้า & สถานที่",
    description: "ลูกค้าทุกราย แยกแถวตามสถานที่ พร้อมที่อยู่และพิกัด",
    allowed: can.exportData,
    fetch: async () =>
      (await prisma.site.findMany({ include: { customer: true }, orderBy: [{ customer: { name: "asc" } }, { name: "asc" }] })) as unknown as Row[],
    columns: [
      c("รหัสลูกค้า", (r) => any(r).customer.customerCode),
      c("ลูกค้า", (r) => any(r).customer.name),
      c("ประเภท", (r) => any(r).customer.type),
      c("ผู้ติดต่อหลัก", (r) => any(r).customer.contactName),
      c("เบอร์ติดต่อ", (r) => any(r).customer.contactPhone),
      f("name", "สถานที่"),
      f("address", "ที่อยู่"),
      f("province", "จังหวัด"),
      f("latitude", "ละติจูด"),
      f("longitude", "ลองจิจูด"),
      f("contactName", "ผู้ติดต่อหน้างาน"),
      f("contactPhone", "เบอร์หน้างาน"),
    ],
  },
  {
    key: "machines",
    label: "เครื่องลูกค้า",
    description: "เครื่องทุกเครื่องที่ดูแล",
    allowed: can.exportData,
    fetch: async () =>
      (await prisma.machine.findMany({
        include: { machineModel: true, site: { include: { customer: true } } },
        orderBy: { machineCode: "asc" },
      })) as unknown as Row[],
    columns: [
      f("machineCode", "รหัสเครื่อง"),
      c("ยี่ห้อ", (r) => any(r).machineModel.brand),
      c("รุ่น", (r) => any(r).machineModel.model),
      c("หมวด", (r) => any(r).machineModel.category),
      f("serialNumber", "S/N"),
      c("ลูกค้า", (r) => any(r).site.customer.name),
      c("สถานที่", (r) => any(r).site.name),
      f("locationDetail", "ตำแหน่งเครื่อง"),
      f("installDate", "วันที่ติดตั้ง"),
      f("warrantyEnd", "ประกันถึง"),
      f("status", "สถานะ"),
    ],
  },
  {
    key: "employees",
    label: "พนักงาน & ความถนัด",
    description: "รายชื่อพนักงาน บทบาท และความถนัดต่อรุ่นเครื่อง (ไม่มีรหัสผ่าน)",
    allowed: can.exportData,
    fetch: async () =>
      (await prisma.employee.findMany({
        include: { skills: { include: { machineModel: true } } },
        orderBy: { employeeCode: "asc" },
      })) as unknown as Row[],
    columns: [
      f("employeeCode", "รหัสพนักงาน"),
      f("firstName", "ชื่อ"),
      f("lastName", "นามสกุล"),
      f("nickname", "ชื่อเล่น"),
      f("position", "ตำแหน่ง"),
      f("role", "บทบาท"),
      f("phone", "เบอร์โทร"),
      f("active", "ใช้งาน"),
      c("ความถนัด (รุ่น:ระดับ)", (r) =>
        any(r)
          .skills.map((s: { machineModel: { brand: string; model: string }; level: number }) => `${s.machineModel.brand} ${s.machineModel.model}:${s.level}`)
          .join("; ")
      ),
    ],
  },
  {
    key: "shifts",
    label: "เวลาเริ่ม-เลิกงาน",
    description: "การลงเวลาเริ่มงาน/เลิกงานจากมือถือ",
    allowed: can.exportData,
    fetch: async () => (await prisma.shift.findMany({ include: { employee: true }, orderBy: { startAt: "desc" } })) as unknown as Row[],
    columns: [
      c("รหัสพนักงาน", (r) => any(r).employee.employeeCode),
      c("ชื่อ", (r) => fullName(any(r).employee)),
      f("workDate", "วันที่"),
      f("startAt", "เริ่มงาน"),
      f("endAt", "เลิกงาน"),
      f("autoClosed", "ปิดอัตโนมัติ"),
    ],
  },
  {
    key: "location-pings",
    label: "ประวัติตำแหน่ง (ข้อมูลส่วนบุคคล)",
    description: "พิกัดระหว่างเวลางานทั้งหมด — เฉพาะผู้จัดการ",
    allowed: can.exportLocations,
    fetch: async () =>
      (await prisma.locationPing.findMany({ include: { employee: true, job: true }, orderBy: { recordedAt: "desc" } })) as unknown as Row[],
    columns: [
      c("รหัสพนักงาน", (r) => any(r).employee.employeeCode),
      c("ชื่อ", (r) => fullName(any(r).employee)),
      f("recordedAt", "เวลา"),
      f("latitude", "ละติจูด"),
      f("longitude", "ลองจิจูด"),
      f("accuracy", "ความแม่นยำ (ม.)"),
      f("source", "ที่มา"),
      c("งาน", (r) => any(r).job?.jobNo ?? ""),
    ],
  },
];

function cell(v: unknown): string {
  if (v === null || v === undefined) return "";
  const s = v instanceof Date ? v.toISOString() : String(v);
  // Neutralise spreadsheet formula injection (=, +, -, @ at the start of a text cell).
  const safe = typeof v === "string" && /^[=+\-@\t\r]/.test(s) ? `'${s}` : s;
  return /[",\n\r]/.test(safe) ? `"${safe.replace(/"/g, '""')}"` : safe;
}

export async function buildCsv(e: Exporter) {
  const rows = await e.fetch();
  const lines = [e.columns.map((col) => cell(col.label)).join(",")];
  for (const r of rows) lines.push(e.columns.map((col) => cell(col.value(r))).join(","));
  return "﻿" + lines.join("\r\n");
}
