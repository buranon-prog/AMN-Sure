import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { FINANCE_TX_TYPE, FINANCE_CATEGORY } from "@/lib/constants";
import { createTransactionAction } from "./actions";

const CATEGORY_LABEL: Record<string, string> = {
  SALES: "รายได้จากการขาย",
  CONSIGNMENT_PAYOUT: "จ่ายคืนผู้ฝากขาย",
  PURCHASE: "ซื้อสินค้าเข้าสต็อก",
  OPERATION_COST: "ค่าใช้จ่ายดำเนินงาน",
  SALARY: "เงินเดือน",
  OTHER: "อื่นๆ",
};

export default async function FinancePage() {
  const session = await requireModule("FINANCE", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "FINANCE");

  const [transactions, totals] = await Promise.all([
    prisma.financeTransaction.findMany({
      orderBy: { transactionDate: "desc" },
      include: { createdBy: { select: { name: true } } },
      take: 200,
    }),
    prisma.financeTransaction.groupBy({ by: ["type"], _sum: { amount: true } }),
  ]);

  const income = totals.find((t) => t.type === "INCOME")?._sum.amount ?? 0;
  const expense = totals.find((t) => t.type === "EXPENSE")?._sum.amount ?? 0;

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">การเงิน (Finance)</h1>
      <p className="text-sm text-gray-500 mt-1">
        รายรับ-รายจ่ายทั้งหมด รวมถึงรายการที่เกิดขึ้นอัตโนมัติจากการขายสินค้าและสินค้าฝากขาย
      </p>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
        <div className="card p-5">
          <div className="text-xs font-semibold text-gray-400 uppercase">รายรับรวม</div>
          <div className="text-2xl font-bold text-green-600 mt-2">
            {income.toLocaleString("th-TH")} บาท
          </div>
        </div>
        <div className="card p-5">
          <div className="text-xs font-semibold text-gray-400 uppercase">รายจ่ายรวม</div>
          <div className="text-2xl font-bold text-red-600 mt-2">
            {expense.toLocaleString("th-TH")} บาท
          </div>
        </div>
        <div className="card p-5">
          <div className="text-xs font-semibold text-gray-400 uppercase">กำไร/ขาดทุนสุทธิ</div>
          <div className="text-2xl font-bold text-gray-900 mt-2">
            {(income - expense).toLocaleString("th-TH")} บาท
          </div>
        </div>
      </div>

      {editable && (
        <div className="card p-6 mt-6 max-w-xl">
          <h2 className="font-semibold text-gray-800 mb-4">บันทึกรายการการเงิน</h2>
          <form action={createTransactionAction} className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="label">ประเภท</label>
                <select name="type" className="input" defaultValue="EXPENSE">
                  {FINANCE_TX_TYPE.map((t) => (
                    <option key={t} value={t}>
                      {t === "INCOME" ? "รายรับ" : "รายจ่าย"}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">หมวดหมู่</label>
                <select name="category" className="input" defaultValue="OTHER">
                  {FINANCE_CATEGORY.map((c) => (
                    <option key={c} value={c}>
                      {CATEGORY_LABEL[c]}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="label">จำนวนเงิน (บาท)</label>
                <input name="amount" type="number" step="0.01" min={0} required className="input" />
              </div>
              <div>
                <label className="label">วันที่</label>
                <input name="transactionDate" type="date" className="input" />
              </div>
            </div>
            <div>
              <label className="label">รายละเอียด</label>
              <input name="description" className="input" />
            </div>
            <button type="submit" className="btn btn-primary">
              บันทึกรายการ
            </button>
          </form>
        </div>
      )}

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>วันที่</th>
              <th>ประเภท</th>
              <th>หมวดหมู่</th>
              <th>รายละเอียด</th>
              <th>จำนวนเงิน</th>
              <th>บันทึกโดย</th>
            </tr>
          </thead>
          <tbody>
            {transactions.map((t) => (
              <tr key={t.id}>
                <td>{t.transactionDate.toLocaleDateString("th-TH")}</td>
                <td>
                  <span
                    className={`badge ${
                      t.type === "INCOME" ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"
                    }`}
                  >
                    {t.type === "INCOME" ? "รายรับ" : "รายจ่าย"}
                  </span>
                </td>
                <td>{CATEGORY_LABEL[t.category] ?? t.category}</td>
                <td>{t.description ?? "-"}</td>
                <td className={t.type === "INCOME" ? "text-green-600" : "text-red-600"}>
                  {t.amount.toLocaleString("th-TH")}
                </td>
                <td className="text-gray-500">{t.createdBy.name}</td>
              </tr>
            ))}
            {transactions.length === 0 && (
              <tr>
                <td colSpan={6} className="text-center text-gray-400 py-6">
                  ยังไม่มีรายการการเงิน
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
