import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { PRODUCT_CONDITIONS } from "@/lib/constants";
import { createProductAction, receiveStockAction } from "./actions";
import { sellStockItemAction } from "@/lib/sales";

export default async function InventoryPage() {
  const session = await requireModule("INVENTORY", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "INVENTORY");

  const [products, stockItems, customers] = await Promise.all([
    prisma.product.findMany({ orderBy: { createdAt: "desc" } }),
    prisma.stockItem.findMany({
      where: { source: "PURCHASE" },
      include: { product: true },
      orderBy: { createdAt: "desc" },
      take: 100,
    }),
    prisma.customer.findMany({ select: { id: true, name: true }, orderBy: { name: "asc" } }),
  ]);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">รับ-จ่ายสินค้า (Inventory)</h1>
      <p className="text-sm text-gray-500 mt-1">
        บันทึกสินค้าที่ซื้อเข้ามาเพื่อขาย พร้อมราคาต้นทุน/ราคาขาย และประวัติการรับ-จ่าย
      </p>

      {editable && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
          <div className="card p-6">
            <h2 className="font-semibold text-gray-800 mb-4">เพิ่มสินค้าในแคตตาล็อก</h2>
            <form action={createProductAction} className="space-y-3">
              <div>
                <label className="label">รหัสสินค้า (SKU)</label>
                <input name="sku" required className="input" />
              </div>
              <div>
                <label className="label">ชื่อสินค้า</label>
                <input name="name" required className="input" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="label">หมวดหมู่</label>
                  <input name="category" required className="input" placeholder="เช่น เครื่องอัลตราซาวด์" />
                </div>
                <div>
                  <label className="label">ยี่ห้อ</label>
                  <input name="brand" className="input" />
                </div>
              </div>
              <div>
                <label className="label">สภาพสินค้า</label>
                <select name="condition" className="input" defaultValue="USED">
                  {PRODUCT_CONDITIONS.map((c) => (
                    <option key={c} value={c}>
                      {c}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">รายละเอียด</label>
                <textarea name="description" className="input" rows={2} />
              </div>
              <button type="submit" className="btn btn-primary">
                บันทึกสินค้า
              </button>
            </form>
          </div>

          <div className="card p-6">
            <h2 className="font-semibold text-gray-800 mb-4">รับสินค้าเข้าสต็อก (ซื้อ)</h2>
            <form action={receiveStockAction} className="space-y-3">
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
                  <label className="label">ราคาต้นทุน/หน่วย (บาท)</label>
                  <input name="costPrice" type="number" step="0.01" min={0} required className="input" />
                </div>
                <div>
                  <label className="label">ราคาขาย/หน่วย (บาท)</label>
                  <input name="listPrice" type="number" step="0.01" min={0} required className="input" />
                </div>
              </div>
              <div>
                <label className="label">ผู้จำหน่าย/ซัพพลายเออร์</label>
                <input name="supplierName" className="input" />
              </div>
              <div>
                <label className="label">หมายเหตุ</label>
                <textarea name="notes" className="input" rows={2} />
              </div>
              <button type="submit" className="btn btn-primary">
                บันทึกรับเข้า
              </button>
            </form>
          </div>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <div className="px-6 pt-5 pb-1 font-semibold text-gray-800">สต็อกสินค้า (ซื้อขาดเป็นของเรา)</div>
        <table className="data-table">
          <thead>
            <tr>
              <th>สินค้า</th>
              <th>ซีเรียล</th>
              <th>จำนวน</th>
              <th>ต้นทุน</th>
              <th>ราคาขาย</th>
              <th>สถานะ</th>
              {editable && <th></th>}
            </tr>
          </thead>
          <tbody>
            {stockItems.map((s) => (
              <tr key={s.id}>
                <td className="font-medium text-gray-900">
                  {s.product.name}
                  <div className="text-xs text-gray-400">{s.product.sku}</div>
                </td>
                <td>{s.serialNumber ?? "-"}</td>
                <td>{s.quantity}</td>
                <td>{s.costPrice.toLocaleString("th-TH")}</td>
                <td>{s.listPrice.toLocaleString("th-TH")}</td>
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
                <td colSpan={7} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลสต็อกสินค้า
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
