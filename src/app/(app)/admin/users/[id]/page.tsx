import { notFound } from "next/navigation";
import { requireManager } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { MODULES, MODULE_LABELS } from "@/lib/constants";
import { updatePermissionsAction, resetPasswordAction } from "../actions";

export default async function UserDetailPage({ params }: { params: { id: string } }) {
  await requireManager();

  const user = await prisma.user.findUnique({
    where: { id: params.id },
    include: { permissions: true },
  });
  if (!user) notFound();

  const permMap = new Map(user.permissions.map((p) => [p.module, p]));

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">{user.name}</h1>
      <p className="text-sm text-gray-500 mt-1">{user.email}</p>

      {user.role === "MANAGER" ? (
        <div className="card p-6 mt-6 text-sm text-gray-600">
          บัญชีนี้เป็นผู้จัดการ (Manager) จึงเข้าถึงได้ทุกโมดูลโดยอัตโนมัติ ไม่ต้องกำหนดสิทธิ์
        </div>
      ) : (
        <div className="card p-6 mt-6">
          <h2 className="font-semibold text-gray-800 mb-4">สิทธิ์การเข้าถึงรายโมดูล</h2>
          <form action={updatePermissionsAction.bind(null, user.id)}>
            <table className="data-table mb-4">
              <thead>
                <tr>
                  <th>โมดูล</th>
                  <th>ดูข้อมูลได้</th>
                  <th>กรอก/แก้ไขข้อมูลได้</th>
                </tr>
              </thead>
              <tbody>
                {MODULES.map((m) => {
                  const perm = permMap.get(m);
                  return (
                    <tr key={m}>
                      <td className="font-medium text-gray-800">{MODULE_LABELS[m]}</td>
                      <td>
                        <input
                          type="checkbox"
                          name={`view_${m}`}
                          defaultChecked={perm?.canView}
                          className="h-4 w-4"
                        />
                      </td>
                      <td>
                        <input
                          type="checkbox"
                          name={`edit_${m}`}
                          defaultChecked={perm?.canEdit}
                          className="h-4 w-4"
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            <p className="text-xs text-gray-400 mb-4">
              &quot;กรอก/แก้ไขข้อมูลได้&quot; หมายถึงพนักงานสามารถเพิ่ม/แก้ไขข้อมูลในโมดูลนั้นได้
              แต่ยังต้องมีสิทธิ์ &quot;ดูข้อมูลได้&quot; ควบคู่กันจึงจะเข้าเมนูได้
            </p>
            <button type="submit" className="btn btn-primary">
              บันทึกสิทธิ์
            </button>
          </form>
        </div>
      )}

      <div className="card p-6 mt-6">
        <h2 className="font-semibold text-gray-800 mb-4">รีเซ็ตรหัสผ่าน</h2>
        <form action={resetPasswordAction.bind(null, user.id)} className="flex gap-3 items-end max-w-md">
          <div className="flex-1">
            <label className="label">รหัสผ่านใหม่</label>
            <input type="password" name="password" minLength={6} required className="input" />
          </div>
          <button type="submit" className="btn btn-secondary">
            บันทึก
          </button>
        </form>
      </div>
    </div>
  );
}
