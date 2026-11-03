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

function Where({
  lat,
  lng,
  distance,
  showDistance,
}: {
  lat: number | null;
  lng: number | null;
  distance: number | null;
  showDistance: boolean;
}) {
  if (lat == null || lng == null) return <span className="text-orange-700"> · ไม่มีพิกัด</span>;
  const far = distance != null && distance > CHECKIN_WARN_DISTANCE_M;
  return (
    <>
      {" · "}
      <a href={pointUrl(lat, lng)} target="_blank" rel="noreferrer" className="link">
        ดูตำแหน่ง
      </a>
      {showDistance && distance != null && (
        <span className={far ? "font-semibold text-red-600" : ""}>
          {" "}
          · ห่างจากหน้างาน {formatDistance(distance)}
          {far && " ⚠️"}
        </span>
      )}
    </>
  );
}

/** รายงานการเข้าหน้างานแต่ละครั้ง (newest first). */
export default function VisitList({ visits, showDistance = true }: { visits: Visit[]; showDistance?: boolean }) {
  if (visits.length === 0) {
    return <p className="text-sm text-gray-400">ยังไม่มีการเข้าหน้างาน</p>;
  }
  return (
    <ol className="space-y-3">
      {visits.map((v, i) => {
        const sameDay = v.checkOutAt && dateKey(v.checkOutAt) === dateKey(v.checkInAt);
        return (
          <li key={v.id} className="rounded-lg border border-gray-200 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="text-sm font-semibold text-gray-900">
                ครั้งที่ {visits.length - i} · {fullName(v.employee)}
              </div>
              <OutcomeBadge v={v} />
            </div>
            <div className="mt-1 space-y-0.5 text-xs text-gray-500">
              <div>
                📍 ถึง {formatDateTime(v.checkInAt)}
                <Where lat={v.checkInLat} lng={v.checkInLng} distance={v.checkInDistanceM} showDistance={showDistance} />
              </div>
              {v.checkOutAt && (
                <div>
                  {v.outcome === "COMPLETED" ? "✅ เสร็จ" : v.outcome === "HOLD" ? "⏸️ Hold" : "ออก"}{" "}
                  {sameDay ? formatTime(v.checkOutAt) : formatDateTime(v.checkOutAt)} (อยู่หน้างาน{" "}
                  {formatDuration(v.checkOutAt.getTime() - v.checkInAt.getTime())})
                  {v.outcome && (
                    <Where lat={v.checkOutLat} lng={v.checkOutLng} distance={v.checkOutDistanceM} showDistance={showDistance} />
                  )}
                </div>
              )}
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
