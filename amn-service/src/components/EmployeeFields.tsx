import { ROLES, ROLE_HINT, ROLE_LABEL } from "@/lib/constants";

type EmployeeDefaults = {
  firstName?: string;
  lastName?: string;
  nickname?: string | null;
  phone?: string | null;
  email?: string | null;
  position?: string | null;
  role?: string;
};

export default function EmployeeFields({ employee }: { employee?: EmployeeDefaults }) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <div>
        <label className="label">ชื่อ *</label>
        <input name="firstName" required defaultValue={employee?.firstName ?? ""} className="input" />
      </div>
      <div>
        <label className="label">นามสกุล *</label>
        <input name="lastName" required defaultValue={employee?.lastName ?? ""} className="input" />
      </div>
      <div>
        <label className="label">ชื่อเล่น</label>
        <input name="nickname" defaultValue={employee?.nickname ?? ""} className="input" />
      </div>
      <div>
        <label className="label">เบอร์โทร</label>
        <input name="phone" type="tel" defaultValue={employee?.phone ?? ""} className="input" />
      </div>
      <div>
        <label className="label">อีเมล</label>
        <input name="email" type="email" defaultValue={employee?.email ?? ""} className="input" />
      </div>
      <div>
        <label className="label">ตำแหน่ง</label>
        <input name="position" defaultValue={employee?.position ?? ""} className="input" />
      </div>
      <div className="sm:col-span-3">
        <label className="label">บทบาท / สิทธิ์การเข้าถึง *</label>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          {ROLES.map((r) => (
            <label key={r} className="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-2.5 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
              <input type="radio" name="role" value={r} defaultChecked={(employee?.role ?? "TECHNICIAN") === r} className="mt-1" />
              <span className="text-sm">
                <span className="font-semibold text-gray-900">{ROLE_LABEL[r]}</span>
                <span className="block text-xs text-gray-500">{ROLE_HINT[r]}</span>
              </span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );
}
