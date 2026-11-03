import type { MachineModel, Site } from "@prisma/client";
import { MACHINE_STATUS, MACHINE_STATUS_LABEL } from "@/lib/constants";
import { dateKey } from "@/lib/datetime";

type MachineDefaults = {
  machineModelId?: string;
  siteId?: string;
  serialNumber?: string | null;
  locationDetail?: string | null;
  installDate?: Date | null;
  warrantyEnd?: Date | null;
  status?: string;
  notes?: string | null;
};

export default function MachineFields({
  models,
  sites,
  machine,
  withCode,
  withStatus,
}: {
  models: MachineModel[];
  sites: Site[];
  machine?: MachineDefaults;
  withCode?: boolean;
  withStatus?: boolean;
}) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {withCode && (
        <div>
          <label className="label">รหัสเครื่องลูกค้า *</label>
          <input name="machineCode" required className="input uppercase" placeholder="เช่น M-0001" />
        </div>
      )}
      <div>
        <label className="label">รุ่นเครื่อง *</label>
        <select name="machineModelId" required defaultValue={machine?.machineModelId ?? ""} className="input">
          <option value="">-- เลือกรุ่น --</option>
          {models.map((m) => (
            <option key={m.id} value={m.id}>
              {m.brand} {m.model} ({m.category})
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="label">สถานที่ติดตั้ง *</label>
        <select name="siteId" required defaultValue={machine?.siteId ?? sites[0]?.id ?? ""} className="input">
          {sites.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="label">Serial Number</label>
        <input name="serialNumber" defaultValue={machine?.serialNumber ?? ""} className="input" />
      </div>
      <div className="sm:col-span-2">
        <label className="label">ตำแหน่งเครื่องในสถานที่</label>
        <input name="locationDetail" defaultValue={machine?.locationDetail ?? ""} className="input" placeholder="เช่น อาคาร 2 ชั้น 3 ห้อง X-Ray" />
      </div>
      <div>
        <label className="label">วันที่ติดตั้ง</label>
        <input type="date" name="installDate" defaultValue={machine?.installDate ? dateKey(machine.installDate) : ""} className="input" />
      </div>
      <div>
        <label className="label">ประกันถึงวันที่</label>
        <input type="date" name="warrantyEnd" defaultValue={machine?.warrantyEnd ? dateKey(machine.warrantyEnd) : ""} className="input" />
      </div>
      {withStatus && (
        <div>
          <label className="label">สถานะเครื่อง</label>
          <select name="status" defaultValue={machine?.status ?? "ACTIVE"} className="input">
            {MACHINE_STATUS.map((s) => (
              <option key={s} value={s}>
                {MACHINE_STATUS_LABEL[s]}
              </option>
            ))}
          </select>
        </div>
      )}
      <div className="sm:col-span-2">
        <label className="label">หมายเหตุ</label>
        <textarea name="notes" rows={2} defaultValue={machine?.notes ?? ""} className="input" />
      </div>
    </div>
  );
}
