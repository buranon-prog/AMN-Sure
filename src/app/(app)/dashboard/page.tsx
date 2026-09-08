import Link from "next/link";
import { auth } from "@/auth";
import { prisma } from "@/lib/prisma";
import { canView, type Action } from "@/lib/rbac";
import { MODULE_LABELS } from "@/lib/constants";

export default async function DashboardPage({
  searchParams,
}: {
  searchParams: { denied?: string };
}) {
  const session = await auth();
  const role = session!.user.role;
  const permissions = session!.user.permissions;

  const show = (m: Parameters<typeof canView>[2]) => canView(role, permissions, m);

  const [
    stockCount,
    consignmentCount,
    financeSummary,
    employeeCount,
    activeShipments,
    customerCount,
  ] = await Promise.all([
    show("INVENTORY") ? prisma.stockItem.count({ where: { status: "IN_STOCK" } }) : null,
    show("CONSIGNMENT")
      ? prisma.stockItem.count({ where: { source: "CONSIGNMENT", status: "IN_STOCK" } })
      : null,
    show("FINANCE")
      ? prisma.financeTransaction.groupBy({ by: ["type"], _sum: { amount: true } })
      : null,
    show("HR") ? prisma.employee.count({ where: { status: "ACTIVE" } }) : null,
    show("SUPPLY_CHAIN")
      ? prisma.shipment.count({ where: { status: { in: ["ORDERED", "IN_TRANSIT", "CUSTOMS"] } } })
      : null,
    show("CRM") ? prisma.customer.count() : null,
  ]);

  const income = financeSummary?.find((f) => f.type === "INCOME")?._sum.amount ?? 0;
  const expense = financeSummary?.find((f) => f.type === "EXPENSE")?._sum.amount ?? 0;

  const cards: { label: string; value: string; href: string }[] = [];
  if (stockCount !== null)
    cards.push({ label: "สต็อกสินค้าคงเหลือ", value: `${stockCount} รายการ`, href: "/inventory" });
  if (consignmentCount !== null)
    cards.push({
      label: "สินค้าฝากขายในสต็อก",
      value: `${consignmentCount} รายการ`,
      href: "/consignment",
    });
  if (financeSummary !== null)
    cards.push({
      label: "กำไร/ขาดทุนสะสม",
      value: `${(income - expense).toLocaleString("th-TH")} บาท`,
      href: "/finance",
    });
  if (employeeCount !== null)
    cards.push({ label: "พนักงานที่ทำงานอยู่", value: `${employeeCount} คน`, href: "/hr" });
  if (activeShipments !== null)
    cards.push({
      label: "ขนส่งที่กำลังดำเนินการ",
      value: `${activeShipments} รายการ`,
      href: "/supply-chain",
    });
  if (customerCount !== null)
    cards.push({ label: "ลูกค้าทั้งหมด", value: `${customerCount} ราย`, href: "/crm" });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">ภาพรวม</h1>
      <p className="text-sm text-gray-500 mt-1">
        สวัสดี {session!.user.name} — สิทธิ์การใช้งาน: {role === "MANAGER" ? "ผู้จัดการ (เข้าถึงได้ทุกส่วน)" : "พนักงาน"}
      </p>

      {searchParams.denied && (
        <div className="mt-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
          คุณไม่มีสิทธิ์เข้าถึงส่วน &quot;{MODULE_LABELS[searchParams.denied as keyof typeof MODULE_LABELS] ?? searchParams.denied}&quot;
          กรุณาติดต่อผู้จัดการเพื่อขอสิทธิ์การใช้งาน
        </div>
      )}

      {cards.length === 0 ? (
        <div className="card p-6 mt-6 text-sm text-gray-500">
          บัญชีของคุณยังไม่ได้รับสิทธิ์เข้าถึงโมดูลใด กรุณาติดต่อผู้จัดการ
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
          {cards.map((c) => (
            <Link key={c.label} href={c.href} className="card p-5 hover:border-brand-300 transition-colors">
              <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
                {c.label}
              </div>
              <div className="text-2xl font-bold text-gray-900 mt-2">{c.value}</div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
