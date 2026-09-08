import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import HrTabs from "@/components/HrTabs";
import { createOperationCostAction } from "../actions";

export default async function OperationCostsPage() {
  const session = await requireModule("HR", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "HR");

  const costs = await prisma.operationCost.findMany({
    orderBy: { costDate: "desc" },
    include: { createdBy: { select: { name: true } } },
    take: 100,
  });

  const total = costs.reduce((sum, c) => sum + c.amount, 0);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">บุคคล (HR)</h1>
      <p className="text-sm text-gray-500 mt-1">
        ค่าใช้จ่ายดำเนินงาน (operation cost) — รายการเหล่านี้จะถูกบันทึกในระบบการเงินโดยอัตโนมัติ
      </p>
      <HrTabs active="/hr/costs" />

      <div className="card p-5 max-w-xs">
        <div className="text-xs font-semibold text-gray-400 uppercase">ค่าใช้จ่ายรวม</div>
        <div className="text-2xl font-bold text-red-600 mt-2">{total.toLocaleString("th-TH")} บาท</div>
      </div>

      {editable && (
        <div className="card p-6 max-w-2xl mt-6">
          <h2 className="font-semibold text-gray-800 mb-4">บันทึกค่าใช้จ่ายดำเนินงาน</h2>
          <form action={createOperationCostAction} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="label">หมวดหมู่</label>
              <input name="category" required className="input" placeholder="เช่น ค่าเช่า ค่าน้ำค่าไฟ ค่าขนส่ง" />
            </div>
            <div>
              <label className="label">วันที่</label>
              <input name="costDate" type="date" className="input" />
            </div>
            <div>
              <label className="label">จำนวนเงิน (บาท)</label>
              <input name="amount" type="number" step="0.01" min={0} required className="input" />
            </div>
            <div>
              <label className="label">รายละเอียด</label>
              <input name="description" className="input" />
            </div>
            <div className="sm:col-span-2">
              <button type="submit" className="btn btn-primary">
                บันทึกค่าใช้จ่าย
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>วันที่</th>
              <th>หมวดหมู่</th>
              <th>รายละเอียด</th>
              <th>จำนวนเงิน</th>
              <th>บันทึกโดย</th>
            </tr>
          </thead>
          <tbody>
            {costs.map((c) => (
              <tr key={c.id}>
                <td>{c.costDate.toLocaleDateString("th-TH")}</td>
                <td className="font-medium text-gray-900">{c.category}</td>
                <td>{c.description ?? "-"}</td>
                <td className="text-red-600">{c.amount.toLocaleString("th-TH")}</td>
                <td className="text-gray-500">{c.createdBy.name}</td>
              </tr>
            ))}
            {costs.length === 0 && (
              <tr>
                <td colSpan={5} className="text-center text-gray-400 py-6">
                  ยังไม่มีข้อมูลค่าใช้จ่าย
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
