import type { Employee, JobVisit } from "@prisma/client";
import { formatDateTime, formatDuration, formatTime, dateKey } from "@/lib/datetime";
import { CHECKIN_WARN_DISTANCE_M, formatDistance, pointUrl } from "@/lib/geo";
import { fullName } from "@/lib/people";

type Visit = JobVisit & { employee: Employee };

function OutcomeBadge({ v }: { v: Visit }) {
  if (v.outcome === "COMPLETED") return <span className="badge bg-emerald-100 text-emerald-800">ส่งงาน (เสร็จ)</span>;
  if (v.outcome === "HOLD") return <span className="badge bg-orange-100 text-orange-800">Hold</span>;
  if (!v.checkOutAt) return <span className="badge bg-indigo-100 text-indigo-800">อยู่หน้างาน</span>;
  return <span className="badge bg-gray-100 text-gray-600">ปิดโดยระบบ</span>;
}

/** รายงานการเข้าหน้างานแต่ละครั้ง (newest first). */
export default function VisitList({ visits, showDistance = true }: { visits: Visit[]; showDistance?: boolean }) {
  if (visits.length === 0) {
    return <p className="text-sm text-gray-400">ยังไม่มีการเข้าหน้างาน</p>;
  }
  return (
    <ol className="space-y-3">
      {visits.map((v, i) => {
        const far = v.checkInDistanceM != null && v.checkInDistanceM > CHECKIN_WARN_DISTANCE_M;
        const sameDay = v.checkOutAt && dateKey(v.checkOutAt) === dateKey(v.checkInAt);
        return (
          <li key={v.id} className="rounded-lg border border-gray-200 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="text-sm font-semibold text-gray-900">
                ครั้งที่ {visits.length - i} · {fullName(v.employee)}
              </div>
              <OutcomeBadge v={v} />
            </div>
            <div className="mt-1 text-xs text-gray-500">
              เช็คอิน {formatDateTime(v.checkInAt)}
              {v.checkOutAt && (
                <>
                  {" "}
                  → {sameDay ? formatTime(v.checkOutAt) : formatDateTime(v.checkOutAt)} (
                  {formatDuration(v.checkOutAt.getTime() - v.checkInAt.getTime())})
                </>
              )}
              {v.checkInLat != null && v.checkInLng != null && (
                <>
                  {" · "}
                  <a href={pointUrl(v.checkInLat, v.checkInLng)} target="_blank" rel="noreferrer" className="link">
                    พิกัดเช็คอิน
                  </a>
                </>
              )}
              {showDistance && v.checkInDistanceM != null && (
                <span className={far ? "font-semibold text-red-600" : ""}>
                  {" "}
                  · ห่างจากหน้างาน {formatDistance(v.checkInDistanceM)}
                  {far && " ⚠️"}
                </span>
              )}
              {v.checkInLat == null && <span className="text-orange-700"> · ไม่มีพิกัดเช็คอิน</span>}
            </div>
            {v.summary && <p className="mt-2 whitespace-pre-wrap text-sm text-gray-800">{v.summary}</p>}
            {v.partsUsed && (
              <p className="mt-1 text-sm text-gray-600">
                <span className="font-semibold">อะไหล่/วัสดุ:</span> {v.partsUsed}
              </p>
            )}
            {v.outcome === "HOLD" && (
              <p className="mt-1 text-sm text-orange-800">
                <span className="font-semibold">เหตุที่ Hold:</span> {v.holdReason}
                {v.nextVisitAt && <> · นัดเข้าครั้งถัดไป {formatDateTime(v.nextVisitAt)}</>}
              </p>
            )}
          </li>
        );
      })}
    </ol>
  );
}
