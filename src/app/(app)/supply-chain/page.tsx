import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { SHIPMENT_STATUS } from "@/lib/constants";
import { createShipmentAction, updateShipmentStatusAction } from "./actions";

const STATUS_LABEL: Record<string, string> = {
  ORDERED: "สั่งซื้อแล้ว",
  IN_TRANSIT: "อยู่ระหว่างขนส่ง",
  CUSTOMS: "อยู่ที่ศุลกากร",
  DELIVERED: "ส่งถึงแล้ว",
  DELAYED: "ล่าช้า",
  CANCELLED: "ยกเลิก",
};

const STATUS_COLOR: Record<string, string> = {
  ORDERED: "bg-gray-100 text-gray-600",
  IN_TRANSIT: "bg-blue-100 text-blue-700",
  CUSTOMS: "bg-amber-100 text-amber-700",
  DELIVERED: "bg-green-100 text-green-700",
  DELAYED: "bg-red-100 text-red-700",
  CANCELLED: "bg-red-100 text-red-700",
};

export default async function SupplyChainPage() {
  const session = await requireModule("SUPPLY_CHAIN", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "SUPPLY_CHAIN");

  const shipments = await prisma.shipment.findMany({ orderBy: { createdAt: "desc" }, take: 100 });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">ซัพพลายเชน / ขนส่ง</h1>
      <p className="text-sm text-gray-500 mt-1">ติดตามสถานะการสั่งซื้อและขนส่งสินค้าจากซัพพลายเออร์</p>

      {editable && (
        <div className="card p-6 mt-6 max-w-2xl">
          <h2 className="font-semibold text-gray-800 mb-4">เพิ่มรายการขนส่ง</h2>
          <form action={createShipmentAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">เลขที่อ้างอิง</label>
              <input name="referenceNo" required className="input" />
            </div>
            <div>
              <label className="label">ซัพพลายเออร์</label>
              <input name="supplierName" className="input" />
            </div>
            <div>
              <label className="label">ต้นทาง</label>
              <input name="origin" className="input" />
            </div>
            <div>
              <label className="label">ปลายทาง</label>
              <input name="destination" className="input" />
            </div>
            <div>
              <label className="label">ผู้ขนส่ง</label>
              <input name="carrier" className="input" />
            </div>
            <div>
              <label className="label">เลขพัสดุ/ติดตาม</label>
              <input name="trackingNumber" className="input" />
            </div>
            <div>
              <label className="label">สถานะ</label>
              <select name="status" className="input" defaultValue="ORDERED">
                {SHIPMENT_STATUS.map((s) => (
                  <option key={s} value={s}>
                    {STATUS_LABEL[s]}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">วันที่คาดว่าจะถึง</label>
              <input name="expectedDate" type="date" className="input" />
            </div>
            <div className="sm:col-span-2">
              <label className="label">หมายเหตุ</label>
              <textarea name="notes" className="input" rows={2} />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกรายการขนส่ง
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>เลขที่อ้างอิง</th>
              <th>ซัพพลายเออร์</th>
              <th>เส้นทาง</th>
              <th>ผู้ขนส่ง</th>
              <th>วันที่คาดว่าจะถึง</th>
              <th>สถานะ</th>
              {editable && <th></th>}
            </tr>
          </thead>
          <tbody>
            {shipments.map((s) => (
              <tr key={s.id}>
                <td className="font-medium text-gray-900">{s.referenceNo}</td>
                <td>{s.supplierName ?? "-"}</td>
                <td>
                  {s.origin ?? "-"} → {s.destination ?? "-"}
                </td>
                <td>{s.carrier ?? "-"}</td>
                <td>{s.expectedDate ? s.expectedDate.toLocaleDateString("th-TH") : "-"}</td>
                <td>
                  <span className={`badge ${STATUS_COLOR[s.status] ?? "bg-gray-100 text-gray-600"}`}>
                    {STATUS_LABEL[s.status] ?? s.status}
                  </span>
                </td>
                {editable && (
                  <td>
                    <form action={updateShipmentStatusAction.bind(null, s.id)} className="flex gap-2">
                      <select name="status" defaultValue={s.status} className="input text-xs py-1">
                        {SHIPMENT_STATUS.map((st) => (
                          <option key={st} value={st}>
                            {STATUS_LABEL[st]}
                          </option>
                        ))}
                      </select>
                      <button type="submit" className="btn btn-secondary text-xs">
                        อัปเดต
                      </button>
                    </form>
                  </td>
                )}
              </tr>
            ))}
            {shipments.length === 0 && (
              <tr>
                <td colSpan={7} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลการขนส่ง
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
