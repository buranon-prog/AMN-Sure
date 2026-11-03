import { JOB_TYPE, JOB_TYPE_LABEL, PRIORITY, PRIORITY_LABEL } from "@/lib/constants";
import { toLocalInput } from "@/lib/datetime";

type JobDefaults = {
  type?: string;
  priority?: string;
  problem?: string;
  reporterName?: string | null;
  reporterPhone?: string | null;
  scheduledAt?: Date;
  estimatedHours?: number | null;
  officeNotes?: string | null;
};

/** The job detail fields shared by "ลงงานใหม่" and "แก้ไขงาน". */
export default function JobFields({ job }: { job?: JobDefaults }) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <div>
        <label className="label">นัดเข้าหน้างาน (วัน-เวลา) *</label>
        <input
          type="datetime-local"
          name="scheduledAt"
          required
          defaultValue={toLocalInput(job?.scheduledAt)}
          className="input"
        />
      </div>
      <div>
        <label className="label">ใช้เวลาประมาณ (ชั่วโมง)</label>
        <input type="number" name="estimatedHours" min={0} step={0.5} defaultValue={job?.estimatedHours ?? ""} className="input" />
      </div>
      <div>
        <label className="label">ประเภทงาน</label>
        <select name="type" defaultValue={job?.type ?? "REPAIR"} className="input">
          {JOB_TYPE.map((t) => (
            <option key={t} value={t}>
              {JOB_TYPE_LABEL[t]}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="label">ความเร่งด่วน</label>
        <select name="priority" defaultValue={job?.priority ?? "NORMAL"} className="input">
          {PRIORITY.map((p) => (
            <option key={p} value={p}>
              {PRIORITY_LABEL[p]}
            </option>
          ))}
        </select>
      </div>
      <div className="sm:col-span-2">
        <label className="label">อาการเสีย / งานที่ต้องทำ *</label>
        <textarea name="problem" required rows={3} defaultValue={job?.problem ?? ""} className="input" />
      </div>
      <div>
        <label className="label">ผู้แจ้ง</label>
        <input name="reporterName" defaultValue={job?.reporterName ?? ""} className="input" />
      </div>
      <div>
        <label className="label">เบอร์ผู้แจ้ง</label>
        <input name="reporterPhone" type="tel" defaultValue={job?.reporterPhone ?? ""} className="input" />
      </div>
      <div className="sm:col-span-2">
        <label className="label">หมายเหตุสำหรับช่าง / ออฟฟิศ</label>
        <textarea name="officeNotes" rows={2} defaultValue={job?.officeNotes ?? ""} className="input" />
      </div>
    </div>
  );
}
