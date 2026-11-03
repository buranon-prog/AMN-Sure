import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import { createModelAction, toggleModelAction } from "../customers/actions";

export default async function ModelsPage() {
  const user = await requireUser(can.useOffice);
  const editable = can.manageCustomers(user.role);
  const models = await prisma.machineModel.findMany({
    include: { _count: { select: { machines: true, skills: true } } },
    orderBy: [{ category: "asc" }, { brand: "asc" }, { model: "asc" }],
  });
  const categories = [...new Set(models.map((m) => m.category))];

  return (
    <div>
      <PageHeader title="รุ่นเครื่อง" subtitle="รายการรุ่นเครื่องที่ดูแล — ใช้ผูกกับเครื่องลูกค้าและความถนัดของช่าง" />
      {editable && (
        <ActionForm action={createModelAction} resetOnSuccess className="card mb-6 p-5">
          <h2 className="section-title">เพิ่มรุ่นเครื่อง</h2>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <input name="brand" required placeholder="ยี่ห้อ *" className="input" />
            <input name="model" required placeholder="รุ่น *" className="input" />
            <input name="category" required placeholder="หมวด * เช่น เครื่องอัลตราซาวด์" list="categories" className="input" />
            <SubmitButton>เพิ่ม</SubmitButton>
          </div>
          <datalist id="categories">
            {categories.map((c) => (
              <option key={c} value={c} />
            ))}
          </datalist>
        </ActionForm>
      )}
      <div className="card overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>หมวด</th>
              <th>ยี่ห้อ</th>
              <th>รุ่น</th>
              <th className="num">เครื่องที่ดูแล</th>
              <th className="num">ช่างที่มีความถนัด</th>
              <th>สถานะ</th>
              {editable && <th></th>}
            </tr>
          </thead>
          <tbody>
            {models.map((m) => (
              <tr key={m.id} className={m.active ? "" : "opacity-50"}>
                <td>{m.category}</td>
                <td>{m.brand}</td>
                <td className="font-medium text-gray-900">{m.model}</td>
                <td className="num">{m._count.machines}</td>
                <td className="num">{m._count.skills}</td>
                <td>{m.active ? "ใช้งาน" : "ซ่อน"}</td>
                {editable && (
                  <td>
                    <ActionForm action={toggleModelAction.bind(null, m.id)}>
                      <SubmitButton className="text-xs text-gray-500 hover:text-gray-900" pendingText="…">
                        {m.active ? "ซ่อน" : "แสดง"}
                      </SubmitButton>
                    </ActionForm>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
