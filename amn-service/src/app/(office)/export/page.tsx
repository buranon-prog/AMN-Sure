import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { EXPORTERS } from "@/lib/exporters";
import PageHeader from "@/components/PageHeader";

export default async function ExportPage() {
  const user = await requireUser(can.exportData);
  const list = EXPORTERS.filter((e) => e.allowed(user.role));
  return (
    <div>
      <PageHeader
        title="Export ข้อมูล"
        subtitle="ข้อมูลทั้งหมดเก็บอยู่ในฐานข้อมูลของระบบ — ดาวน์โหลดเป็น CSV เพื่อเปิดใน Excel / Google Sheets"
      />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {list.map((e) => (
          <div key={e.key} className="card flex flex-col justify-between p-5">
            <div>
              <div className="font-semibold text-gray-900">{e.label}</div>
              <div className="mt-1 text-sm text-gray-500">{e.description}</div>
            </div>
            <a href={`/api/export/${e.key}`} className="btn btn-secondary mt-4 self-start">
              ดาวน์โหลด CSV
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}
