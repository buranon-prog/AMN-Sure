import { prisma } from "@/lib/prisma";
import type { ModuleName } from "@/lib/constants";
import { toCsv, type CsvColumn } from "@/lib/csv";

// The "data warehouse": every module's raw data, in one place, exportable
// to CSV for use outside the app (spreadsheets, BI tools, accounting, etc).
// Each entry is gated by the same per-module view permission used
// everywhere else in the app (see src/lib/rbac.ts).

function col(key: string, label: string, value?: (row: Record<string, unknown>) => unknown): CsvColumn<Record<string, unknown>> {
  return { key, label, value: value ?? ((row) => row[key]) };
}

function cols(...c: CsvColumn<Record<string, unknown>>[]) {
  return c;
}

export type Entity = {
  key: string;
  label: string;
  description: string;
  module: ModuleName;
  fetch: (params: URLSearchParams) => Promise<Record<string, unknown>[]>;
  columns: CsvColumn<Record<string, unknown>>[];
};

export const EXPORTERS: Entity[] = [
  {
    key: "products",
    label: "แคตตาล็อกสินค้า",
    description: "รายการสินค้าทั้งหมดในแคตตาล็อก",
    module: "INVENTORY",
    fetch: () => prisma.product.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("sku", "SKU"),
      col("name", "ชื่อสินค้า"),
      col("category", "หมวดหมู่"),
      col("brand", "ยี่ห้อ"),
      col("condition", "สภาพ"),
      col("createdAt", "วันที่เพิ่ม")
    ),
  },
  {
    key: "stock-items",
    label: "สต็อกสินค้า (ซื้อขาด + ฝากขาย)",
    description: "สต็อกทุกรายการ ไม่ว่าจะเป็นของที่ซื้อมาเองหรือรับฝากขาย",
    module: "INVENTORY",
    fetch: async () => {
      const items = await prisma.stockItem.findMany({
        include: { product: true, consignor: true },
        orderBy: { createdAt: "desc" },
      });
      return items as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("source", "แหล่งที่มา"),
      col("product", "สินค้า", (r) => (r.product as { name: string })?.name),
      col("sku", "SKU", (r) => (r.product as { sku: string })?.sku),
      col("consignor", "ผู้ฝากขาย", (r) => (r.consignor as { name: string } | null)?.name ?? ""),
      col("serialNumber", "ซีเรียล"),
      col("quantity", "จำนวน"),
      col("costPrice", "ต้นทุน"),
      col("listPrice", "ราคาขาย"),
      col("status", "สถานะ"),
      col("receivedDate", "วันที่รับเข้า")
    ),
  },
  {
    key: "stock-movements",
    label: "ประวัติรับ-จ่ายสินค้า",
    description: "ทุกการเคลื่อนไหวของสต็อก (รับเข้า/จ่ายออก)",
    module: "INVENTORY",
    fetch: async () => {
      const rows = await prisma.stockMovement.findMany({
        include: { stockItem: { include: { product: true } }, createdBy: true },
        orderBy: { movementDate: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("product", "สินค้า", (r) => (r.stockItem as { product: { name: string } })?.product?.name),
      col("type", "ประเภท"),
      col("quantity", "จำนวน"),
      col("unitPrice", "ราคาต่อหน่วย"),
      col("counterparty", "คู่ค้า"),
      col("referenceNo", "เลขที่อ้างอิง"),
      col("movementDate", "วันที่"),
      col("createdBy", "บันทึกโดย", (r) => (r.createdBy as { name: string })?.name)
    ),
  },
  {
    key: "consignors",
    label: "ผู้ฝากขาย",
    description: "รายชื่อผู้ฝากขายและเงื่อนไขค่าคอมมิชชัน",
    module: "CONSIGNMENT",
    fetch: () => prisma.consignor.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("name", "ชื่อ"),
      col("contactPerson", "ผู้ติดต่อ"),
      col("phone", "เบอร์โทร"),
      col("email", "อีเมล"),
      col("commissionRate", "ส่วนแบ่งของเรา (%)")
    ),
  },
  {
    key: "sales",
    label: "ประวัติการขาย",
    description: "ทุกรายการขาย ทั้งสินค้าของเราเองและสินค้าฝากขาย",
    module: "FINANCE",
    fetch: async () => {
      const rows = await prisma.sale.findMany({
        include: { stockItem: { include: { product: true } }, customer: true, soldBy: true },
        orderBy: { saleDate: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("product", "สินค้า", (r) => (r.stockItem as { product: { name: string } })?.product?.name),
      col("customer", "ลูกค้า", (r) => (r.customer as { name: string } | null)?.name ?? "ลูกค้าหน้าร้าน"),
      col("salePrice", "ราคาขาย"),
      col("consignorPayout", "จ่ายคืนผู้ฝากขาย"),
      col("paymentStatus", "สถานะชำระเงิน"),
      col("saleDate", "วันที่ขาย"),
      col("soldBy", "ผู้ขาย", (r) => (r.soldBy as { name: string })?.name)
    ),
  },
  {
    key: "finance-transactions",
    label: "รายการการเงิน",
    description: "รายรับ-รายจ่ายทั้งหมด",
    module: "FINANCE",
    fetch: async () => {
      const rows = await prisma.financeTransaction.findMany({
        include: { createdBy: true },
        orderBy: { transactionDate: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("type", "ประเภท"),
      col("category", "หมวดหมู่"),
      col("amount", "จำนวนเงิน"),
      col("description", "รายละเอียด"),
      col("transactionDate", "วันที่"),
      col("createdBy", "บันทึกโดย", (r) => (r.createdBy as { name: string })?.name)
    ),
  },
  {
    key: "employees",
    label: "ข้อมูลพนักงาน",
    description: "รายชื่อพนักงานทั้งหมด",
    module: "HR",
    fetch: () => prisma.employee.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("employeeCode", "รหัสพนักงาน"),
      col("fullName", "ชื่อ-นามสกุล"),
      col("position", "ตำแหน่ง"),
      col("department", "แผนก"),
      col("salary", "เงินเดือน"),
      col("status", "สถานะ"),
      col("hireDate", "วันที่เริ่มงาน")
    ),
  },
  {
    key: "attendance",
    label: "การลงเวลาทำงาน",
    description: "ประวัติการลงเวลาของพนักงาน (ใส่ ?employeeId= เพื่อกรองเฉพาะคน)",
    module: "HR",
    fetch: async (params) => {
      const employeeId = params.get("employeeId");
      const rows = await prisma.attendance.findMany({
        where: employeeId ? { employeeId } : undefined,
        include: { employee: true },
        orderBy: { date: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("employee", "พนักงาน", (r) => (r.employee as { fullName: string })?.fullName),
      col("date", "วันที่"),
      col("checkIn", "เวลาเข้า"),
      col("checkOut", "เวลาออก"),
      col("status", "สถานะ"),
      col("notes", "หมายเหตุ")
    ),
  },
  {
    key: "operation-costs",
    label: "ค่าใช้จ่ายดำเนินงาน",
    description: "ค่าใช้จ่ายดำเนินงานทั้งหมด",
    module: "HR",
    fetch: async () => {
      const rows = await prisma.operationCost.findMany({ include: { createdBy: true }, orderBy: { costDate: "desc" } });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("category", "หมวดหมู่"),
      col("description", "รายละเอียด"),
      col("amount", "จำนวนเงิน"),
      col("costDate", "วันที่")
    ),
  },
  {
    key: "kpi-records",
    label: "ผล KPI ของพนักงาน",
    description: "ผลลัพธ์ KPI ทุกรอบของพนักงานทุกคน (ใส่ ?employeeId= เพื่อกรองเฉพาะคน)",
    module: "HR",
    fetch: async (params) => {
      const employeeId = params.get("employeeId");
      const rows = await prisma.kpiRecord.findMany({
        where: employeeId ? { employeeId } : undefined,
        include: { employee: true, kpiDefinition: true },
        orderBy: { createdAt: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("employee", "พนักงาน", (r) => (r.employee as { fullName: string })?.fullName),
      col("kpi", "ตัวชี้วัด", (r) => (r.kpiDefinition as { name: string })?.name),
      col("period", "รอบ"),
      col("actualValue", "ผลลัพธ์"),
      col("targetValue", "เป้าหมาย", (r) => (r.kpiDefinition as { targetValue: number | null })?.targetValue ?? "")
    ),
  },
  {
    key: "employee-plans",
    label: "แผนงานของพนักงาน",
    description: "แผนงาน/เป้าหมายของพนักงานแต่ละคน (ใส่ ?employeeId= เพื่อกรองเฉพาะคน)",
    module: "HR",
    fetch: async (params) => {
      const employeeId = params.get("employeeId");
      const rows = await prisma.employeePlan.findMany({
        where: employeeId ? { employeeId } : undefined,
        include: { employee: true },
        orderBy: { createdAt: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("employee", "พนักงาน", (r) => (r.employee as { fullName: string })?.fullName),
      col("title", "แผนงาน"),
      col("period", "รอบ"),
      col("dueDate", "กำหนดเสร็จ"),
      col("priority", "ความสำคัญ"),
      col("status", "สถานะ")
    ),
  },
  {
    key: "shipments",
    label: "การขนส่ง",
    description: "สถานะการสั่งซื้อและขนส่งจากซัพพลายเออร์",
    module: "SUPPLY_CHAIN",
    fetch: () => prisma.shipment.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("referenceNo", "เลขที่อ้างอิง"),
      col("supplierName", "ซัพพลายเออร์"),
      col("origin", "ต้นทาง"),
      col("destination", "ปลายทาง"),
      col("carrier", "ผู้ขนส่ง"),
      col("status", "สถานะ"),
      col("expectedDate", "วันที่คาดว่าจะถึง"),
      col("actualDate", "วันที่ถึงจริง")
    ),
  },
  {
    key: "customers",
    label: "ลูกค้า",
    description: "ข้อมูลลูกค้าทั้งหมด",
    module: "CRM",
    fetch: () => prisma.customer.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("name", "ชื่อ"),
      col("type", "ประเภท"),
      col("phone", "เบอร์โทร"),
      col("email", "อีเมล"),
      col("loyaltyTier", "Loyalty Tier"),
      col("industry", "อุตสาหกรรม")
    ),
  },
  {
    key: "customer-interactions",
    label: "ประวัติการติดต่อลูกค้า",
    description: "ความสนใจและโอกาสในการขายของลูกค้าแต่ละราย",
    module: "CRM",
    fetch: async () => {
      const rows = await prisma.customerInteraction.findMany({
        include: { customer: true, createdBy: true },
        orderBy: { createdAt: "desc" },
      });
      return rows as unknown as Record<string, unknown>[];
    },
    columns: cols(
      col("id", "ID"),
      col("customer", "ลูกค้า", (r) => (r.customer as { name: string })?.name),
      col("type", "ประเภท"),
      col("interestProduct", "สินค้าที่สนใจ"),
      col("probability", "โอกาสปิดการขาย (%)"),
      col("followUpDate", "วันที่ติดตามต่อ"),
      col("createdAt", "วันที่บันทึก")
    ),
  },
  {
    key: "sourcing-targets",
    label: "เป้าหมายจัดหาสินค้า",
    description: "เครื่องมือแพทย์ที่ต้องการหาและควรซื้อจากที่ไหน",
    module: "STRATEGY",
    fetch: () => prisma.sourcingTarget.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("equipmentName", "ชื่อเครื่องมือ"),
      col("targetSource", "แหล่งซื้อ"),
      col("estimatedCost", "งบประมาณ"),
      col("priority", "ความสำคัญ"),
      col("status", "สถานะ")
    ),
  },
  {
    key: "market-targets",
    label: "เป้าหมายตลาด",
    description: "ตลาด/กลุ่มลูกค้าที่ควรขาย",
    module: "STRATEGY",
    fetch: () => prisma.marketTarget.findMany({ orderBy: { createdAt: "desc" } }),
    columns: cols(
      col("id", "ID"),
      col("segmentName", "กลุ่มเป้าหมาย"),
      col("potentialValue", "มูลค่าโอกาส"),
      col("priority", "ความสำคัญ"),
      col("status", "สถานะ")
    ),
  },
];

export function getExporter(key: string) {
  return EXPORTERS.find((e) => e.key === key) ?? null;
}

export async function buildCsvForExporter(entity: Entity, params: URLSearchParams) {
  const rows = await entity.fetch(params);
  return toCsv(rows, entity.columns);
}
