import { requireSession, canView } from "@/lib/rbac";
import { EXPORTERS } from "@/lib/exporters";
import { MODULE_LABELS } from "@/lib/constants";

export default async function DataWarehousePage() {
  const session = await requireSession();
  const { role, permissions } = session.user;

  const available = EXPORTERS.filter((e) => canView(role, permissions, e.module));
  const hidden = EXPORTERS.length - available.length;

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">คลังข้อมูล (Data Warehouse)</h1>
      <p className="text-sm text-gray-500 mt-1">
        รวมข้อมูลรายละเอียดทุกอย่างของระบบไว้ที่เดียว สามารถ export เป็นไฟล์ CSV เพื่อนำไปใช้งานต่อ
        (เปิดด้วย Excel / Google Sheets หรือนำเข้าระบบวิเคราะห์ข้อมูลอื่นๆ ได้ทันที)
      </p>
      <p className="text-xs text-gray-400 mt-1">
        แต่ละรายการจะแสดงเฉพาะที่คุณมีสิทธิ์ &quot;ดูข้อมูลได้&quot; เท่านั้น{" "}
        {hidden > 0 && `(ซ่อนอยู่ ${hidden} รายการที่คุณไม่มีสิทธิ์เข้าถึง)`}
      </p>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
        {available.map((e) => (
          <div key={e.key} className="card p-5 flex flex-col justify-between">
            <div>
              <div className="text-xs font-semibold text-brand-600 uppercase tracking-wide">
                {MODULE_LABELS[e.module]}
              </div>
              <div className="font-semibold text-gray-900 mt-1">{e.label}</div>
              <div className="text-sm text-gray-500 mt-1">{e.description}</div>
            </div>
            <a href={`/api/export/${e.key}`} className="btn btn-secondary mt-4 self-start">
              Export CSV
            </a>
          </div>
        ))}
        {available.length === 0 && (
          <div className="card p-6 text-center text-gray-400 sm:col-span-2 lg:col-span-3">
            บัญชีของคุณยังไม่มีสิทธิ์ดูข้อมูลของโมดูลใดเลย
          </div>
        )}
      </div>
    </div>
  );
}
