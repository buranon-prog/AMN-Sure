type SiteDefaults = {
  name?: string;
  address?: string | null;
  province?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  contactName?: string | null;
  contactPhone?: string | null;
};

export default function SiteFields({ site }: { site?: SiteDefaults }) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <label className="label">ชื่อสถานที่ *</label>
        <input name="siteName" required defaultValue={site?.name ?? "สำนักงานใหญ่"} className="input" placeholder="เช่น สำนักงานใหญ่, สาขารังสิต" />
      </div>
      <div>
        <label className="label">จังหวัด</label>
        <input name="province" defaultValue={site?.province ?? ""} className="input" />
      </div>
      <div className="sm:col-span-2">
        <label className="label">ที่อยู่</label>
        <textarea name="address" rows={2} defaultValue={site?.address ?? ""} className="input" />
      </div>
      <div className="sm:col-span-2">
        <label className="label">พิกัด (Location)</label>
        <div className="grid grid-cols-2 gap-2">
          <input name="latitude" inputMode="decimal" defaultValue={site?.latitude ?? ""} placeholder="ละติจูด เช่น 13.7563" className="input" />
          <input name="longitude" inputMode="decimal" defaultValue={site?.longitude ?? ""} placeholder="ลองจิจูด เช่น 100.5018" className="input" />
        </div>
        <input name="coords" placeholder="หรือวางพิกัด / ลิงก์ Google Maps ที่มีพิกัด" className="input mt-2" />
        <p className="mt-1 text-xs text-gray-500">ใช้นำทางช่าง และวัดระยะตอนช่างกด “ถึงหน้างาน” / “เสร็จงาน” ว่าอยู่ที่หน้างานจริง</p>
      </div>
      <div>
        <label className="label">ผู้ติดต่อหน้างาน</label>
        <input name="siteContactName" defaultValue={site?.contactName ?? ""} className="input" />
      </div>
      <div>
        <label className="label">เบอร์ผู้ติดต่อหน้างาน</label>
        <input name="siteContactPhone" type="tel" defaultValue={site?.contactPhone ?? ""} className="input" />
      </div>
    </div>
  );
}
