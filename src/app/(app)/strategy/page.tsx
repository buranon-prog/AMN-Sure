import { requireModule, canEdit } from "@/lib/rbac";
import { prisma } from "@/lib/prisma";
import { PRIORITY, SOURCING_STATUS, MARKET_STATUS } from "@/lib/constants";
import {
  createSourcingTargetAction,
  updateSourcingStatusAction,
  createMarketTargetAction,
  updateMarketStatusAction,
} from "./actions";

const PRIORITY_LABEL: Record<string, string> = { LOW: "ต่ำ", MEDIUM: "กลาง", HIGH: "สูง" };
const SOURCING_LABEL: Record<string, string> = {
  SEEKING: "กำลังหา",
  NEGOTIATING: "กำลังเจรจา",
  ACQUIRED: "จัดหาแล้ว",
  DROPPED: "ยกเลิก",
};
const MARKET_LABEL: Record<string, string> = {
  PLANNED: "วางแผนไว้",
  IN_PROGRESS: "กำลังดำเนินการ",
  ACTIVE: "กำลังขายอยู่",
  DROPPED: "ยกเลิก",
};

export default async function StrategyPage() {
  const session = await requireModule("STRATEGY", "view");
  const editable = canEdit(session.user.role, session.user.permissions, "STRATEGY");

  const [sourcingTargets, marketTargets] = await Promise.all([
    prisma.sourcingTarget.findMany({ orderBy: { createdAt: "desc" } }),
    prisma.marketTarget.findMany({ orderBy: { createdAt: "desc" } }),
  ]);

  return (
    <div>
      <h1 className="text-2xl font-bold text-gray-900">กลยุทธ์ (Strategy)</h1>
      <p className="text-sm text-gray-500 mt-1">
        วางแผนว่าเครื่องมือแพทย์แบบไหนที่ควรจัดหา ควรซื้อจากที่ไหน และควรขายไปยังตลาดใด
      </p>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">
        <div>
          <h2 className="text-lg font-semibold text-gray-800 mb-3">
            เครื่องมือแพทย์ที่ต้องการหา / ควรซื้อจากที่ไหน
          </h2>
          {editable && (
            <div className="card p-6 mb-4">
              <form action={createSourcingTargetAction} className="space-y-3">
                <div>
                  <label className="label">ชื่อเครื่องมือแพทย์</label>
                  <input name="equipmentName" required className="input" />
                </div>
                <div>
                  <label className="label">แหล่งที่ควรซื้อ</label>
                  <input name="targetSource" className="input" placeholder="เช่น ตัวแทนจำหน่ายในยุโรป, งานประมูล" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="label">งบประมาณโดยประมาณ (บาท)</label>
                    <input name="estimatedCost" type="number" step="0.01" min={0} className="input" />
                  </div>
                  <div>
                    <label className="label">ความสำคัญ</label>
                    <select name="priority" className="input" defaultValue="MEDIUM">
                      {PRIORITY.map((p) => (
                        <option key={p} value={p}>
                          {PRIORITY_LABEL[p]}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
                <div>
                  <label className="label">หมายเหตุ</label>
                  <textarea name="notes" className="input" rows={2} />
                </div>
                <button type="submit" className="btn btn-primary">
                  บันทึก
                </button>
              </form>
            </div>
          )}

          <div className="space-y-3">
            {sourcingTargets.map((t) => (
              <div key={t.id} className="card p-4">
                <div className="flex items-center justify-between">
                  <div className="font-semibold text-gray-900">{t.equipmentName}</div>
                  <span className="badge bg-brand-100 text-brand-700">{PRIORITY_LABEL[t.priority]}</span>
                </div>
                {t.targetSource && <div className="text-sm text-gray-600 mt-1">แหล่งซื้อ: {t.targetSource}</div>}
                {t.estimatedCost != null && (
                  <div className="text-sm text-gray-600">งบประมาณ: {t.estimatedCost.toLocaleString("th-TH")} บาท</div>
                )}
                {t.notes && <div className="text-sm text-gray-500 mt-1">{t.notes}</div>}
                <div className="mt-2 flex items-center justify-between">
                  <span className="badge bg-gray-100 text-gray-600">{SOURCING_LABEL[t.status]}</span>
                  {editable && (
                    <form action={updateSourcingStatusAction.bind(null, t.id)} className="flex gap-2">
                      <select name="status" defaultValue={t.status} className="input text-xs py-1">
                        {SOURCING_STATUS.map((s) => (
                          <option key={s} value={s}>
                            {SOURCING_LABEL[s]}
                          </option>
                        ))}
                      </select>
                      <button type="submit" className="btn btn-secondary text-xs">
                        อัปเดต
                      </button>
                    </form>
                  )}
                </div>
              </div>
            ))}
            {sourcingTargets.length === 0 && (
              <div className="card p-6 text-center text-gray-400 text-sm">ยังไม่มีข้อมูล</div>
            )}
          </div>
        </div>

        <div>
          <h2 className="text-lg font-semibold text-gray-800 mb-3">ตลาด/กลุ่มลูกค้าที่ควรขาย</h2>
          {editable && (
            <div className="card p-6 mb-4">
              <form action={createMarketTargetAction} className="space-y-3">
                <div>
                  <label className="label">กลุ่มลูกค้า/พื้นที่เป้าหมาย</label>
                  <input name="segmentName" required className="input" placeholder="เช่น โรงพยาบาลรัฐภาคอีสาน" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="label">มูลค่าโอกาสทางธุรกิจ (บาท)</label>
                    <input name="potentialValue" type="number" step="0.01" min={0} className="input" />
                  </div>
                  <div>
                    <label className="label">ความสำคัญ</label>
                    <select name="priority" className="input" defaultValue="MEDIUM">
                      {PRIORITY.map((p) => (
                        <option key={p} value={p}>
                          {PRIORITY_LABEL[p]}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
                <div>
                  <label className="label">หมายเหตุ</label>
                  <textarea name="notes" className="input" rows={2} />
                </div>
                <button type="submit" className="btn btn-primary">
                  บันทึก
                </button>
              </form>
            </div>
          )}

          <div className="space-y-3">
            {marketTargets.map((t) => (
              <div key={t.id} className="card p-4">
                <div className="flex items-center justify-between">
                  <div className="font-semibold text-gray-900">{t.segmentName}</div>
                  <span className="badge bg-brand-100 text-brand-700">{PRIORITY_LABEL[t.priority]}</span>
                </div>
                {t.potentialValue != null && (
                  <div className="text-sm text-gray-600 mt-1">
                    มูลค่าโอกาส: {t.potentialValue.toLocaleString("th-TH")} บาท
                  </div>
                )}
                {t.notes && <div className="text-sm text-gray-500 mt-1">{t.notes}</div>}
                <div className="mt-2 flex items-center justify-between">
                  <span className="badge bg-gray-100 text-gray-600">{MARKET_LABEL[t.status]}</span>
                  {editable && (
                    <form action={updateMarketStatusAction.bind(null, t.id)} className="flex gap-2">
                      <select name="status" defaultValue={t.status} className="input text-xs py-1">
                        {MARKET_STATUS.map((s) => (
                          <option key={s} value={s}>
                            {MARKET_LABEL[s]}
                          </option>
                        ))}
                      </select>
                      <button type="submit" className="btn btn-secondary text-xs">
                        อัปเดต
                      </button>
                    </form>
                  )}
                </div>
              </div>
            ))}
            {marketTargets.length === 0 && (
              <div className="card p-6 text-center text-gray-400 text-sm">ยังไม่มีข้อมูล</div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
