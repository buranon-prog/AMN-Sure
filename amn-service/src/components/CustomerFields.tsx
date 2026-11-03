import { CUSTOMER_TYPE, CUSTOMER_TYPE_LABEL } from "@/lib/constants";

type CustomerDefaults = {
  name?: string;
  type?: string;
  contactName?: string | null;
  contactPhone?: string | null;
  email?: string | null;
  taxId?: string | null;
  notes?: string | null;
};

export default function CustomerFields({ customer }: { customer?: CustomerDefaults }) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <label className="label">ชื่อลูกค้า *</label>
        <input name="name" required defaultValue={customer?.name ?? ""} className="input" />
      </div>
      <div>
        <label className="label">ลูกค้าคือใคร (ประเภท)</label>
        <select name="type" defaultValue={customer?.type ?? "HOSPITAL"} className="input">
          {CUSTOMER_TYPE.map((t) => (
            <option key={t} value={t}>
              {CUSTOMER_TYPE_LABEL[t]}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="label">ผู้ติดต่อหลัก</label>
        <input name="contactName" defaultValue={customer?.contactName ?? ""} className="input" />
      </div>
      <div>
        <label className="label">เบอร์ติดต่อ</label>
        <input name="contactPhone" type="tel" defaultValue={customer?.contactPhone ?? ""} className="input" />
      </div>
      <div>
        <label className="label">อีเมล</label>
        <input name="email" type="email" defaultValue={customer?.email ?? ""} className="input" />
      </div>
      <div>
        <label className="label">เลขประจำตัวผู้เสียภาษี</label>
        <input name="taxId" defaultValue={customer?.taxId ?? ""} className="input" />
      </div>
      <div className="sm:col-span-2">
        <label className="label">หมายเหตุ</label>
        <textarea name="notes" rows={2} defaultValue={customer?.notes ?? ""} className="input" />
      </div>
    </div>
  );
}
