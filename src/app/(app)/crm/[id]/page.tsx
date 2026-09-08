import { notFound } from "next/navigation";
import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { LOYALTY_TIER, INTERACTION_TYPE } from "@/lib/constants";
import { updateLoyaltyAction, updateCustomerNotesAction, createInteractionAction } from "../actions";

const INTERACTION_LABEL: Record<string, string> = {
  CALL: "โทรศัพท์",
  MEETING: "นัดพบ",
  INTEREST: "ความสนใจสินค้า",
  COMPLAINT: "ข้อร้องเรียน",
  PURCHASE_FOLLOWUP: "ติดตามหลังการขาย",
};

export default async function CustomerProfilePage({ params }: { params: { id: string } }) {
  const session = await requireModule("CRM", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "CRM");

  const customer = await prisma.customer.findUnique({
    where: { id: params.id },
    include: {
      interactions: { orderBy: { createdAt: "desc" }, include: { createdBy: { select: { name: true } } } },
      sales: { orderBy: { saleDate: "desc" }, include: { stockItem: { include: { product: true } } } },
    },
  });
  if (!customer) notFound();

  const totalSpent = customer.sales.reduce((sum, s) => sum + s.salePrice, 0);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">{customer.name}</h1>
      <p className="text-sm text-gray-500 mt-1">
        {customer.industry ?? "-"} · {customer.phone ?? "-"} · {customer.email ?? "-"}
      </p>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <div className="card p-6 lg:col-span-1">
          <h2 className="font-semibold text-gray-800 mb-3">Loyalty Tier</h2>
          {editable ? (
            <form action={updateLoyaltyAction.bind(null, customer.id)} className="flex gap-2">
              <select name="loyaltyTier" defaultValue={customer.loyaltyTier} className="input">
                {LOYALTY_TIER.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </select>
              <button type="submit" className="btn btn-secondary">
                บันทึก
              </button>
            </form>
          ) : (
            <span className="badge bg-brand-100 text-brand-700">{customer.loyaltyTier}</span>
          )}

          <div className="mt-4 text-sm text-gray-600">
            ยอดซื้อสะสม: <span className="font-semibold">{totalSpent.toLocaleString("th-TH")} บาท</span>
          </div>
          <div className="text-sm text-gray-600">จำนวนครั้งที่ซื้อ: {customer.sales.length}</div>

          {editable && (
            <form action={updateCustomerNotesAction.bind(null, customer.id)} className="mt-4 space-y-2">
              <label className="label">โปรไฟล์ / หมายเหตุลูกค้า</label>
              <textarea name="notes" defaultValue={customer.notes ?? ""} rows={4} className="input" />
              <button type="submit" className="btn btn-secondary">
                บันทึกโปรไฟล์
              </button>
            </form>
          )}
          {!editable && customer.notes && (
            <div className="mt-4 text-sm text-gray-600 whitespace-pre-wrap">{customer.notes}</div>
          )}
        </div>

        <div className="card p-6 lg:col-span-2">
          <h2 className="font-semibold text-gray-800 mb-3">
            ความเป็นไปได้และความสนใจของลูกค้า
          </h2>

          {editable && (
            <form
              action={createInteractionAction.bind(null, customer.id)}
              className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5 border-b border-gray-100 pb-5"
            >
              <div>
                <label className="label">ประเภท</label>
                <select name="type" className="input" defaultValue="INTEREST">
                  {INTERACTION_TYPE.map((t) => (
                    <option key={t} value={t}>
                      {INTERACTION_LABEL[t]}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">สินค้าที่สนใจ</label>
                <input name="interestProduct" className="input" />
              </div>
              <div>
                <label className="label">ความเป็นไปได้ในการปิดการขาย (%)</label>
                <input name="probability" type="number" min={0} max={100} className="input" />
              </div>
              <div>
                <label className="label">วันที่ควรติดตามต่อ</label>
                <input name="followUpDate" type="date" className="input" />
              </div>
              <div className="sm:col-span-2">
                <label className="label">รายละเอียด</label>
                <textarea name="notes" className="input" rows={2} />
              </div>
              <div className="sm:col-span-2">
                <button type="submit" className="btn btn-primary">
                  บันทึกการติดต่อ
                </button>
              </div>
            </form>
          )}

          <div className="space-y-3">
            {customer.interactions.map((i) => (
              <div key={i.id} className="border border-gray-100 rounded-lg p-3">
                <div className="flex items-center justify-between">
                  <span className="badge bg-brand-100 text-brand-700">
                    {INTERACTION_LABEL[i.type] ?? i.type}
                  </span>
                  <span className="text-xs text-gray-400">
                    {i.createdAt.toLocaleDateString("th-TH")} · {i.createdBy.name}
                  </span>
                </div>
                {i.interestProduct && (
                  <div className="text-sm text-gray-700 mt-2">สนใจ: {i.interestProduct}</div>
                )}
                {i.probability != null && (
                  <div className="text-sm text-gray-700">โอกาสปิดการขาย: {i.probability}%</div>
                )}
                {i.notes && <div className="text-sm text-gray-500 mt-1">{i.notes}</div>}
                {i.followUpDate && (
                  <div className="text-xs text-amber-600 mt-1">
                    ติดตามต่อวันที่ {i.followUpDate.toLocaleDateString("th-TH")}
                  </div>
                )}
              </div>
            ))}
            {customer.interactions.length === 0 && (
              <div className="text-sm text-gray-400">ยังไม่มีประวัติการติดต่อ</div>
            )}
          </div>
        </div>
      </div>

      <div className="card mt-6 overflow-x-auto">
        <div className="px-6 pt-5 pb-1 font-semibold text-gray-800">ประวัติการซื้อ</div>
        <table className="data-table">
          <thead>
            <tr>
              <th>วันที่</th>
              <th>สินค้า</th>
              <th>ราคาขาย</th>
              <th>สถานะชำระเงิน</th>
            </tr>
          </thead>
          <tbody>
            {customer.sales.map((s) => (
              <tr key={s.id}>
                <td>{s.saleDate.toLocaleDateString("th-TH")}</td>
                <td>{s.stockItem.product.name}</td>
                <td>{s.salePrice.toLocaleString("th-TH")}</td>
                <td>{s.paymentStatus}</td>
              </tr>
            ))}
            {customer.sales.length === 0 && (
              <tr>
                <td colSpan={4} className="text-center text-gray-400 py-6">
                  ยังไม่มีประวัติการซื้อ
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
