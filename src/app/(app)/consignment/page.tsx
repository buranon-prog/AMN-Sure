import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { createConsignorAction, receiveConsignmentStockAction } from "./actions";
import { sellStockItemAction } from "@/lib/sales";

export default async function ConsignmentPage() {
  const session = await requireModule("CONSIGNMENT", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "CONSIGNMENT");

  const [products, consignors, stockItems, customers] = await Promise.all([
    prisma.product.findMany({ orderBy: { name: "asc" } }),
    prisma.consignor.findMany({ orderBy: { createdAt: "desc" } }),
    prisma.stockItem.findMany({
      where: { source: "CONSIGNMENT" },
      include: { product: true, consignor: true },
      orderBy: { createdAt: "desc" },
      take: 100,
    }),
    prisma.customer.findMany({ select: { id: true, name: true }, orderBy: { name: "asc" } }),
  ]);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">สินค้าฝากขาย (Consignment)</h1>
      <p className="text-sm text-gray-500 mt-1">
        สินค้าที่รับฝากขายไม่ใช้เงินทุนของเรา แต่เมื่อขายออกจะเกิดธุรกรรมการเงินอัตโนมัติ
        (รายรับจากการขาย + รายจ่ายจ่ายคืนเจ้าของ)
      </p>

      {editable && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
          <div className="card p-6">
            <h2 className="font-semibold text-gray-800 mb-4">เพิ่มผู้ฝากขาย</h2>
            <form action={createConsignorAction} className="space-y-3">
              <div>
                <label className="label">ชื่อผู้ฝากขาย</label>
                <input name="name" required className="input" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">ผู้ติดต่อ</label>
                  <input name="contactPerson" className="input" />
                </div>
                <div>
                  <label className="label">เบอร์โทร</label>
                  <input name="phone" className="input" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">อีเมล</label>
                  <input name="email" type="email" className="input" />
                </div>
                <div>
                  <label className="label">ส่วนแบ่งของเรา (%)</label>
                  <input name="commissionRate" type="number" step="0.1" min={0} max={100} className="input" />
                </div>
              </div>
              <div>
                <label className="label">หมายเหตุ</label>
                <textarea name="notes" className="input" rows={2} />
              </div>
              <button type="submit" className="btn btn-primary">
                บันทึกผู้ฝากขาย
              </button>
            </form>
          </div>

          <div className="card p-6">
            <h2 className="font-semibold text-gray-800 mb-4">รับสินค้าฝากขายเข้าสต็อก</h2>
            <form action={receiveConsignmentStockAction} className="space-y-3">
              <div>
                <label className="label">สินค้า</label>
                <select name="productId" required className="input">
                  <option value="">-- เลือกสินค้า --</option>
                  {products.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.sku} - {p.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">ผู้ฝากขาย</label>
                <select name="consignorId" required className="input">
                  <option value="">-- เลือกผู้ฝากขาย --</option>
                  {consignors.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">จำนวน</label>
                  <input name="quantity" type="number" min={1} defaultValue={1} required className="input" />
                </div>
                <div>
                  <label className="label">เลขซีเรียล</label>
                  <input name="serialNumber" className="input" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">ราคาขาย/หน่วย (บาท)</label>
                  <input name="listPrice" type="number" step="0.01" min={0} required className="input" />
                </div>
                <div>
                  <label className="label">ยอดจ่ายคืนที่ตกลงไว้ (ถ้ามี)</label>
                  <input name="agreedPayout" type="number" step="0.01" min={0} className="input" />
                </div>
              </div>
              <div>
                <label className="label">หมายเหตุ</label>
                <textarea name="notes" className="input" rows={2} />
              </div>
              <button type="submit" className="btn btn-primary">
                บันทึกรับฝากขาย
              </button>
            </form>
          </div>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <div className="px-6 pt-5 pb-1 font-semibold text-gray-800">สต็อกสินค้าฝากขาย</div>
        <table className="data-table">
          <thead>
            <tr>
              <th>สินค้า</th>
              <th>ผู้ฝากขาย</th>
              <th>ราคาขาย</th>
              <th>ยอดจ่ายคืน</th>
              <th>สถานะ</th>
              {editable && <th></th>}
            </tr>
          </thead>
          <tbody>
            {stockItems.map((s) => (
              <tr key={s.id}>
                <td className="font-medium text-gray-900">
                  {s.product.name}
                  <div className="text-xs text-gray-400">{s.serialNumber ?? s.product.sku}</div>
                </td>
                <td>{s.consignor?.name ?? "-"}</td>
                <td>{s.listPrice.toLocaleString("th-TH")}</td>
                <td>
                  {s.agreedPayout != null
                    ? s.agreedPayout.toLocaleString("th-TH")
                    : s.consignor?.commissionRate != null
                    ? `${100 - s.consignor.commissionRate}% ของราคาขาย`
                    : "-"}
                </td>
                <td>
                  <span
                    className={`badge ${
                      s.status === "IN_STOCK"
                        ? "bg-green-100 text-green-700"
                        : s.status === "SOLD"
                        ? "bg-gray-100 text-gray-600"
                        : "bg-amber-100 text-amber-700"
                    }`}
                  >
                    {s.status}
                  </span>
                </td>
                {editable && (
                  <td>
                    {s.status === "IN_STOCK" && (
                      <details>
                        <summary className="cursor-pointer text-brand-600 text-sm select-none">ขายสินค้า</summary>
                        <form action={sellStockItemAction.bind(null, s.id)} className="mt-2 space-y-2 min-w-[220px]">
                          <select name="customerId" className="input">
                            <option value="">-- ลูกค้าหน้าร้าน --</option>
                            {customers.map((c) => (
                              <option key={c.id} value={c.id}>
                                {c.name}
                              </option>
                            ))}
                          </select>
                          <input
                            name="salePrice"
                            type="number"
                            step="0.01"
                            min={0}
                            defaultValue={s.listPrice}
                            required
                            placeholder="ราคาขายจริง"
                            className="input"
                          />
                          <select name="paymentStatus" className="input" defaultValue="PAID">
                            <option value="PAID">ชำระแล้ว</option>
                            <option value="PENDING">ค้างชำระ</option>
                            <option value="PARTIAL">ชำระบางส่วน</option>
                          </select>
                          <button type="submit" className="btn btn-primary w-full text-xs">
                            ยืนยันการขาย
                          </button>
                        </form>
                      </details>
                    )}
                  </td>
                )}
              </tr>
            ))}
            {stockItems.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center text-gray-400 py-6">
                  ยังไม่มีสินค้าฝากขาย
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
