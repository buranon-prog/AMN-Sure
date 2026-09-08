import Link from "next/link";
import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { CUSTOMER_TYPE } from "@/lib/constants";
import { createCustomerAction } from "./actions";

const TYPE_LABEL: Record<string, string> = {
  INDIVIDUAL: "บุคคลทั่วไป",
  CLINIC: "คลินิก",
  HOSPITAL: "โรงพยาบาล",
  DEALER: "ตัวแทนจำหน่าย",
};

const TIER_COLOR: Record<string, string> = {
  BRONZE: "bg-orange-100 text-orange-700",
  SILVER: "bg-gray-200 text-gray-700",
  GOLD: "bg-yellow-100 text-yellow-700",
  PLATINUM: "bg-brand-100 text-brand-700",
};

export default async function CrmPage() {
  const session = await requireModule("CRM", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "CRM");

  const customers = await prisma.customer.findMany({
    orderBy: { createdAt: "desc" },
    include: { _count: { select: { sales: true, interactions: true } } },
  });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">ลูกค้าสัมพันธ์ (CRM)</h1>
      <p className="text-sm text-gray-500 mt-1">
        ข้อมูลลูกค้า ความ loyalty ความสนใจ และโอกาสในการขายของลูกค้าแต่ละราย
      </p>

      {editable && (
        <div className="card p-6 mt-6 max-w-2xl">
          <h2 className="font-semibold text-gray-800 mb-4">เพิ่มลูกค้าใหม่</h2>
          <form action={createCustomerAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">ชื่อลูกค้า/หน่วยงาน</label>
              <input name="name" required className="input" />
            </div>
            <div>
              <label className="label">ประเภท</label>
              <select name="type" className="input" defaultValue="CLINIC">
                {CUSTOMER_TYPE.map((t) => (
                  <option key={t} value={t}>
                    {TYPE_LABEL[t]}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">เบอร์โทร</label>
              <input name="phone" className="input" />
            </div>
            <div>
              <label className="label">อีเมล</label>
              <input name="email" type="email" className="input" />
            </div>
            <div>
              <label className="label">อุตสาหกรรม/สาขา</label>
              <input name="industry" className="input" />
            </div>
            <div>
              <label className="label">ที่อยู่</label>
              <input name="address" className="input" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกลูกค้า
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>ชื่อลูกค้า</th>
              <th>ประเภท</th>
              <th>Loyalty</th>
              <th>ยอดซื้อสะสม</th>
              <th>ประวัติการติดต่อ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {customers.map((c) => (
              <tr key={c.id}>
                <td className="font-medium text-gray-900">{c.name}</td>
                <td>{TYPE_LABEL[c.type] ?? c.type}</td>
                <td>
                  <span className={`badge ${TIER_COLOR[c.loyaltyTier] ?? "bg-gray-100 text-gray-600"}`}>
                    {c.loyaltyTier}
                  </span>
                </td>
                <td>{c._count.sales} รายการ</td>
                <td>{c._count.interactions} รายการ</td>
                <td>
                  <Link href={`/crm/${c.id}`} className="text-brand-600 hover:underline text-sm">
                    ดูโปรไฟล์
                  </Link>
                </td>
              </tr>
            ))}
            {customers.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลลูกค้า
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
