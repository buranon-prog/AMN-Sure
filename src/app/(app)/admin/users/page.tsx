import Link from "next/link";
import { requireManager } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { createUserAction, toggleActiveAction } from "./actions";

export default async function UsersPage() {
  await requireManager();

  const users = await prisma.user.findMany({
    orderBy: { createdAt: "asc" },
    include: { employee: true },
  });

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">จัดการผู้ใช้งานและสิทธิ์การเข้าถึง</h1>
      <p className="text-sm text-gray-500 mt-1">
        ผู้จัดการ (Manager) เข้าถึงได้ทุกส่วนโดยอัตโนมัติ ส่วนพนักงานต้องกำหนดสิทธิ์ราย
        โมดูลด้วยตนเอง
      </p>

      <div className="card p-6 mt-6">
        <h2 className="font-semibold text-gray-800 mb-4">เพิ่มบัญชีผู้ใช้งานใหม่</h2>
        <form action={createUserAction} className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="label">ชื่อ-นามสกุล</label>
            <input name="name" required className="input" />
          </div>
          <div>
            <label className="label">อีเมล</label>
            <input name="email" type="email" required className="input" />
          </div>
          <div>
            <label className="label">รหัสผ่านเริ่มต้น</label>
            <input name="password" type="password" required minLength={6} className="input" />
          </div>
          <div>
            <label className="label">บทบาท</label>
            <select name="role" className="input" defaultValue="EMPLOYEE">
              <option value="EMPLOYEE">พนักงาน (Employee)</option>
              <option value="MANAGER">ผู้จัดการ (Manager)</option>
            </select>
          </div>
          <div className="sm:col-span-2">
            <button type="submit" className="btn btn-primary">
              สร้างบัญชี
            </button>
          </div>
        </form>
      </div>

      <div className="card mt-6 overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>ชื่อ</th>
              <th>อีเมล</th>
              <th>บทบาท</th>
              <th>สถานะ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id}>
                <td className="font-medium text-gray-900">{u.name}</td>
                <td>{u.email}</td>
                <td>
                  <span
                    className={`badge ${
                      u.role === "MANAGER" ? "bg-brand-100 text-brand-700" : "bg-gray-100 text-gray-600"
                    }`}
                  >
                    {u.role === "MANAGER" ? "ผู้จัดการ" : "พนักงาน"}
                  </span>
                </td>
                <td>
                  <span className={`badge ${u.active ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"}`}>
                    {u.active ? "ใช้งานอยู่" : "ปิดใช้งาน"}
                  </span>
                </td>
                <td className="text-right space-x-3 whitespace-nowrap">
                  {u.role === "EMPLOYEE" && (
                    <Link href={`/admin/users/${u.id}`} className="text-brand-600 hover:underline text-sm">
                      กำหนดสิทธิ์
                    </Link>
                  )}
                  <Link href={`/admin/users/${u.id}`} className="text-brand-600 hover:underline text-sm">
                    รีเซ็ตรหัสผ่าน
                  </Link>
                  <form action={toggleActiveAction.bind(null, u.id)} className="inline">
                    <button type="submit" className="text-sm text-gray-500 hover:text-red-600">
                      {u.active ? "ปิดใช้งาน" : "เปิดใช้งาน"}
                    </button>
                  </form>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
