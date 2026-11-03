import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { PING_SOURCE_LABEL } from "@/lib/constants";
import { TRACKING_END, TRACKING_START } from "@/lib/config";
import { dateKey, dayRange, formatDateLong, formatTime, timeAgo } from "@/lib/datetime";
import { formatDistance, pointUrl } from "@/lib/geo";
import { fullName, shortName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import TrackingMap, { type MapMarker } from "@/components/TrackingMap";
import AutoRefresh from "@/components/AutoRefresh";

function freshness(at: Date | undefined, isToday: boolean) {
  if (!at) return { color: "#9ca3af", label: "ไม่มีข้อมูล" };
  if (!isToday) return { color: "#0f6b57", label: formatTime(at) };
  const min = (Date.now() - at.getTime()) / 60000;
  if (min <= 15) return { color: "#10866a", label: timeAgo(at) };
  if (min <= 60) return { color: "#d97706", label: timeAgo(at) };
  return { color: "#6b7280", label: timeAgo(at) };
}

export default async function TrackingPage({ searchParams }: { searchParams: { employee?: string; date?: string } }) {
  await requireUser(can.viewTracking);
  const date = /^\d{4}-\d{2}-\d{2}$/.test(searchParams.date ?? "") ? searchParams.date! : dateKey();
  const isToday = date === dateKey();
  const { start, end } = dayRange(date);

  const [people, shifts, pings, openVisits] = await Promise.all([
    prisma.employee.findMany({
      where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } },
      orderBy: { firstName: "asc" },
    }),
    prisma.shift.findMany({ where: { workDate: date }, orderBy: { startAt: "asc" } }),
    prisma.locationPing.findMany({
      where: { recordedAt: { gte: start, lt: end } },
      orderBy: { recordedAt: "asc" },
      include: { job: { select: { jobNo: true } } },
    }),
    isToday
      ? prisma.jobVisit.findMany({
          where: { outcome: null, checkOutAt: null },
          include: { job: { include: { machine: { include: { site: { include: { customer: true } } } } } } },
        })
      : [],
  ]);

  const rows = people.map((p) => {
    const mine = pings.filter((x) => x.employeeId === p.id);
    const myShifts = shifts.filter((s) => s.employeeId === p.id);
    return {
      person: p,
      last: mine[mine.length - 1],
      count: mine.length,
      shiftStart: myShifts[0]?.startAt,
      shiftOpen: myShifts.some((s) => !s.endAt),
      shiftEnd: myShifts.length && myShifts.every((s) => s.endAt) ? myShifts[myShifts.length - 1].endAt : null,
      visit: openVisits.find((v) => v.employeeId === p.id),
    };
  });

  const selected = rows.find((r) => r.person.id === searchParams.employee);
  const selectedPings = selected ? pings.filter((x) => x.employeeId === selected.person.id) : [];

  const markers: MapMarker[] = selected
    ? selectedPings.map((x, i) => ({
        id: x.id,
        lat: x.latitude,
        lng: x.longitude,
        title: `${formatTime(x.recordedAt)} · ${PING_SOURCE_LABEL[x.source] ?? x.source}`,
        subtitle: x.job ? `งาน ${x.job.jobNo}` : undefined,
        color: i === selectedPings.length - 1 ? "#10866a" : x.source === "TRACKING" ? "#94a3b8" : "#4f46e5",
        kind: i === selectedPings.length - 1 ? "person" : "point",
      }))
    : rows
        .filter((r) => r.last)
        .map((r) => ({
          id: r.person.id,
          lat: r.last!.latitude,
          lng: r.last!.longitude,
          title: shortName(r.person),
          subtitle: `${freshness(r.last!.recordedAt, isToday).label}${
            r.visit ? ` · อยู่ที่งาน ${r.visit.job.jobNo} ${r.visit.job.machine.site.customer.name}` : ""
          }`,
          color: freshness(r.last!.recordedAt, isToday).color,
          href: `/tracking?employee=${r.person.id}&date=${date}`,
        }));

  // Today's job sites the selected technician is visiting (or everyone's open visits).
  const siteMarkers: MapMarker[] = openVisits
    .filter((v) => (!selected || v.employeeId === selected.person.id) && v.job.machine.site.latitude != null)
    .map((v) => ({
      id: `site-${v.id}`,
      lat: v.job.machine.site.latitude!,
      lng: v.job.machine.site.longitude!,
      title: v.job.machine.site.customer.name,
      subtitle: `หน้างาน ${v.job.jobNo}`,
      color: "#4f46e5",
      kind: "site" as const,
      href: `/jobs/${v.jobId}`,
    }));

  const trail = selected ? selectedPings.map((x) => [x.latitude, x.longitude] as [number, number]) : undefined;

  return (
    <div>
      {isToday && <AutoRefresh everyMs={60_000} />}
      <PageHeader
        title="ติดตามตำแหน่งช่าง"
        subtitle={`บันทึกเฉพาะเวลางาน: ระหว่างช่างกด “เริ่มงาน” ถึง “เลิกงาน” และอยู่ในช่วง ${TRACKING_START}–${TRACKING_END} น.`}
        actions={
          <form className="flex gap-2" action="/tracking">
            {selected && <input type="hidden" name="employee" value={selected.person.id} />}
            <input type="date" name="date" defaultValue={date} max={dateKey()} className="input" aria-label="วันที่" />
            <button className="btn btn-secondary shrink-0 whitespace-nowrap">ดูวันที่นี้</button>
          </form>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          {selected && (
            <div className="mb-2 flex items-center justify-between text-sm">
              <span className="font-semibold text-gray-800">
                เส้นทางของ {fullName(selected.person)} · {formatDateLong(start)}
              </span>
              <Link href={`/tracking?date=${date}`} className="link">
                ← ดูทุกคน
              </Link>
            </div>
          )}
          <TrackingMap markers={[...siteMarkers, ...markers]} trail={trail} height={480} />
          {markers.length === 0 && (
            <p className="mt-2 text-sm text-gray-500">
              {selected ? "ไม่มีข้อมูลตำแหน่งของช่างคนนี้ในวันที่เลือก" : "ยังไม่มีช่างแชร์ตำแหน่งในวันที่เลือก"}
            </p>
          )}
        </div>

        <div className="card h-fit divide-y divide-gray-100">
          {rows.map((r) => {
            const f = freshness(r.last?.recordedAt, isToday);
            const active = selected?.person.id === r.person.id;
            return (
              <Link
                key={r.person.id}
                href={`/tracking?employee=${r.person.id}&date=${date}`}
                className={`block px-4 py-3 hover:bg-gray-50 ${active ? "bg-brand-50" : ""}`}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="flex items-center gap-2 font-semibold text-gray-900">
                    <span className="h-2.5 w-2.5 rounded-full" style={{ background: f.color }} />
                    {fullName(r.person)}
                  </span>
                  <span className="text-xs text-gray-500">{r.last ? f.label : ""}</span>
                </div>
                <div className="mt-0.5 pl-4 text-xs text-gray-500">
                  {r.visit ? (
                    <span className="text-indigo-700">
                      อยู่หน้างาน {r.visit.job.jobNo} · {r.visit.job.machine.site.customer.name}
                    </span>
                  ) : r.shiftOpen ? (
                    `ทำงานอยู่ · เริ่ม ${formatTime(r.shiftStart)}`
                  ) : r.shiftStart ? (
                    `ทำงาน ${formatTime(r.shiftStart)}–${formatTime(r.shiftEnd)}`
                  ) : (
                    "ไม่ได้เริ่มงาน"
                  )}
                  {r.count > 0 && ` · ${r.count} จุด`}
                </div>
              </Link>
            );
          })}
          {rows.length === 0 && <p className="p-4 text-sm text-gray-400">ยังไม่มีช่างในระบบ</p>}
        </div>
      </div>

      {selected && selectedPings.length > 0 && (
        <section className="card mt-6 overflow-x-auto">
          <table className="data-table">
            <thead>
              <tr>
                <th>เวลา</th>
                <th>ที่มา</th>
                <th>งาน</th>
                <th>ความแม่นยำ</th>
                <th>พิกัด</th>
              </tr>
            </thead>
            <tbody>
              {[...selectedPings].reverse().map((x) => (
                <tr key={x.id}>
                  <td>{formatTime(x.recordedAt)}</td>
                  <td>{PING_SOURCE_LABEL[x.source] ?? x.source}</td>
                  <td>{x.job?.jobNo ?? "-"}</td>
                  <td>{x.accuracy != null ? `±${formatDistance(x.accuracy)}` : "-"}</td>
                  <td>
                    <a href={pointUrl(x.latitude, x.longitude)} target="_blank" rel="noreferrer" className="link">
                      {x.latitude.toFixed(5)}, {x.longitude.toFixed(5)}
                    </a>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}
    </div>
  );
}
