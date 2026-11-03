import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { dateKey, dayRange, formatDateLong, formatTime } from "@/lib/datetime";
import { CHECKIN_WARN_DISTANCE_M, formatDistance, pointUrl } from "@/lib/geo";
import { fullName, shortName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import TrackingMap, { type MapMarker } from "@/components/TrackingMap";
import AutoRefresh from "@/components/AutoRefresh";

type Stop = {
  key: string;
  kind: "ARRIVE" | "FINISH" | "HOLD";
  at: Date;
  lat: number | null;
  lng: number | null;
  distance: number | null;
  jobId: string;
  jobNo: string;
  customer: string;
  siteLat: number | null;
  siteLng: number | null;
};

const KIND_LABEL: Record<Stop["kind"], string> = { ARRIVE: "📍 ถึงหน้างาน", FINISH: "✅ เสร็จงาน", HOLD: "⏸️ Hold" };

/**
 * Where technicians were when they pressed "ถึงหน้างานแล้ว" and "เสร็จแล้ว/Hold".
 * No continuous tracking: these two moments are the only locations recorded.
 */
export default async function TrackingPage({ searchParams }: { searchParams: { employee?: string; date?: string } }) {
  await requireUser(can.viewTracking);
  const today = dateKey();
  const date = /^\d{4}-\d{2}-\d{2}$/.test(searchParams.date ?? "") && searchParams.date! <= today ? searchParams.date! : today;
  const isToday = date === today;
  const { start, end } = dayRange(date);

  const [people, visits] = await Promise.all([
    prisma.employee.findMany({
      where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } },
      orderBy: { firstName: "asc" },
    }),
    prisma.jobVisit.findMany({
      where: { OR: [{ checkInAt: { gte: start, lt: end } }, { checkOutAt: { gte: start, lt: end } }] },
      include: { job: { include: { machine: { include: { site: { include: { customer: true } } } } } } },
      orderBy: { checkInAt: "asc" },
    }),
  ]);

  const stopsOf = (employeeId: string): Stop[] => {
    const out: Stop[] = [];
    for (const v of visits.filter((x) => x.employeeId === employeeId)) {
      const base = {
        jobId: v.jobId,
        jobNo: v.job.jobNo,
        customer: v.job.machine.site.customer.name,
        siteLat: v.job.machine.site.latitude,
        siteLng: v.job.machine.site.longitude,
      };
      if (v.checkInAt >= start && v.checkInAt < end) {
        out.push({ ...base, key: `${v.id}-in`, kind: "ARRIVE", at: v.checkInAt, lat: v.checkInLat, lng: v.checkInLng, distance: v.checkInDistanceM });
      }
      if (v.checkOutAt && v.outcome && v.checkOutAt >= start && v.checkOutAt < end) {
        out.push({
          ...base,
          key: `${v.id}-out`,
          kind: v.outcome === "COMPLETED" ? "FINISH" : "HOLD",
          at: v.checkOutAt,
          lat: v.checkOutLat,
          lng: v.checkOutLng,
          distance: v.checkOutDistanceM,
        });
      }
    }
    return out.sort((a, b) => a.at.getTime() - b.at.getTime());
  };

  const rows = people
    .map((p) => {
      const stops = stopsOf(p.id);
      const last = stops[stops.length - 1];
      const lastLocated = [...stops].reverse().find((s) => s.lat != null);
      return { person: p, stops, last, lastLocated };
    })
    .sort((a, b) => (b.last?.at.getTime() ?? 0) - (a.last?.at.getTime() ?? 0));

  const selected = rows.find((r) => r.person.id === searchParams.employee) ?? null;

  const markers: MapMarker[] = selected
    ? [
        // the sites visited (squares) + each press, numbered in order
        ...[...new Map(selected.stops.filter((s) => s.siteLat != null).map((s) => [s.jobId, s])).values()].map((s) => ({
          id: `site-${s.jobId}`,
          lat: s.siteLat!,
          lng: s.siteLng!,
          title: s.customer,
          subtitle: `หน้างาน ${s.jobNo}`,
          color: "#4f46e5",
          kind: "site" as const,
          href: `/jobs/${s.jobId}`,
        })),
        ...selected.stops
          .filter((s) => s.lat != null)
          .map((s, i) => ({
            id: s.key,
            lat: s.lat!,
            lng: s.lng!,
            title: `${i + 1}. ${KIND_LABEL[s.kind]} ${formatTime(s.at)}`,
            subtitle: `${s.jobNo} · ${s.customer}`,
            color: s.kind === "ARRIVE" ? "#4f46e5" : s.kind === "FINISH" ? "#10866a" : "#d97706",
            kind: "person" as const,
            href: `/jobs/${s.jobId}`,
          })),
      ]
    : rows
        .filter((r) => r.lastLocated)
        .map((r) => ({
          id: r.person.id,
          lat: r.lastLocated!.lat!,
          lng: r.lastLocated!.lng!,
          title: shortName(r.person),
          subtitle: `${KIND_LABEL[r.lastLocated!.kind]} ${formatTime(r.lastLocated!.at)} · ${r.lastLocated!.jobNo} ${r.lastLocated!.customer}`,
          color: r.last?.kind === "ARRIVE" ? "#4f46e5" : "#10866a",
          href: `/tracking?employee=${r.person.id}&date=${date}`,
        }));

  const trail = selected
    ? selected.stops.filter((s) => s.lat != null).map((s) => [s.lat!, s.lng!] as [number, number])
    : undefined;

  return (
    <div>
      {isToday && <AutoRefresh everyMs={60_000} />}
      <PageHeader
        title="ตำแหน่งช่าง (ถึงหน้างาน / เสร็จงาน)"
        subtitle="ตำแหน่งจากโทรศัพท์ของช่าง ณ ตอนที่กด “ถึงหน้างานแล้ว” และ “เสร็จแล้ว/Hold” เท่านั้น — ไม่มีการติดตามระหว่างทาง"
        actions={
          <form className="flex gap-2" action="/tracking">
            {selected && <input type="hidden" name="employee" value={selected.person.id} />}
            <input type="date" name="date" defaultValue={date} max={today} className="input" aria-label="วันที่" />
            <button className="btn btn-secondary shrink-0 whitespace-nowrap">ดูวันที่นี้</button>
          </form>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span className="font-semibold text-gray-800">
              {selected ? `${fullName(selected.person)} · ` : ""}
              {formatDateLong(start)}
            </span>
            {selected && (
              <Link href={`/tracking?date=${date}`} className="link">
                ← ดูทุกคน
              </Link>
            )}
          </div>
          <TrackingMap markers={markers} trail={trail} height={460} />
          <div className="mt-2 flex flex-wrap gap-4 text-xs text-gray-600">
            <span className="inline-flex items-center gap-1.5">
              <span className="h-2.5 w-2.5 rounded-full bg-[#4f46e5]" /> ถึงหน้างาน (กำลังทำ)
            </span>
            <span className="inline-flex items-center gap-1.5">
              <span className="h-2.5 w-2.5 rounded-full bg-[#10866a]" /> เสร็จงาน
            </span>
            {selected && (
              <span className="inline-flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-[#d97706]" /> Hold
              </span>
            )}
          </div>

          {selected && (
            <section className="card mt-4 overflow-x-auto">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>เวลา</th>
                    <th>กด</th>
                    <th>งาน / ลูกค้า</th>
                    <th>ห่างจากหน้างาน</th>
                    <th>ตำแหน่ง</th>
                  </tr>
                </thead>
                <tbody>
                  {selected.stops.map((s, i) => (
                    <tr key={s.key}>
                      <td>{i + 1}</td>
                      <td className="tabular-nums">{formatTime(s.at)}</td>
                      <td className="whitespace-nowrap">{KIND_LABEL[s.kind]}</td>
                      <td>
                        <Link href={`/jobs/${s.jobId}`} className="link">
                          {s.jobNo}
                        </Link>
                        <div className="text-xs text-gray-500">{s.customer}</div>
                      </td>
                      <td className={s.distance != null && s.distance > CHECKIN_WARN_DISTANCE_M ? "font-semibold text-red-600" : ""}>
                        {s.distance != null ? `${formatDistance(s.distance)}${s.distance > CHECKIN_WARN_DISTANCE_M ? " ⚠️" : ""}` : "-"}
                      </td>
                      <td>
                        {s.lat != null ? (
                          <a href={pointUrl(s.lat, s.lng!)} target="_blank" rel="noreferrer" className="link">
                            เปิดแผนที่
                          </a>
                        ) : (
                          <span className="text-orange-700">ไม่มีพิกัด</span>
                        )}
                      </td>
                    </tr>
                  ))}
                  {selected.stops.length === 0 && (
                    <tr>
                      <td colSpan={6} className="py-6 text-center text-gray-400">
                        ไม่มีการกดถึงหน้างาน/เสร็จงานในวันที่เลือก
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </section>
          )}
        </div>

        <ul className="card h-fit divide-y divide-gray-100" aria-label="รายชื่อช่าง">
          {rows.map((r) => {
            const active = selected?.person.id === r.person.id;
            const onSite = r.last?.kind === "ARRIVE";
            return (
              <li key={r.person.id}>
                <Link
                  href={`/tracking?employee=${r.person.id}&date=${date}`}
                  className={`block px-4 py-3 hover:bg-gray-50 ${active ? "bg-brand-50" : ""}`}
                  data-person={r.person.id}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="flex min-w-0 items-center gap-2 font-semibold text-gray-900">
                      <span
                        className="h-2.5 w-2.5 shrink-0 rounded-full"
                        style={{ background: !r.last ? "#d1d5db" : onSite ? "#4f46e5" : "#10866a" }}
                      />
                      <span className="truncate">{fullName(r.person)}</span>
                    </span>
                    {r.last && <span className="shrink-0 text-xs tabular-nums text-gray-500">{formatTime(r.last.at)}</span>}
                  </div>
                  <div className="mt-0.5 pl-4 text-xs text-gray-500">
                    {!r.last ? (
                      "ยังไม่มีการกดถึงหน้างานในวันนี้"
                    ) : onSite ? (
                      <span className="text-indigo-700">
                        อยู่หน้างาน {r.last.jobNo} · {r.last.customer}
                      </span>
                    ) : (
                      <span>
                        {r.last.kind === "FINISH" ? "เสร็จงาน" : "Hold"} {r.last.jobNo} · {r.last.customer}
                      </span>
                    )}
                    {r.last && r.last.lat == null && <span className="text-orange-700"> · ไม่มีพิกัด</span>}
                    {r.stops.length > 0 && ` · ${r.stops.length} ครั้ง`}
                  </div>
                </Link>
              </li>
            );
          })}
          {rows.length === 0 && <li className="p-4 text-sm text-gray-400">ยังไม่มีช่างในระบบ</li>}
        </ul>
      </div>
    </div>
  );
}
