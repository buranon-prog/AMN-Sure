import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { PING_SOURCE_LABEL } from "@/lib/constants";
import { LIVE_WINDOW_MS, TRACKING_END, TRACKING_START } from "@/lib/config";
import { dateKey, dayRange } from "@/lib/datetime";
import { fullName, shortName } from "@/lib/people";
import { fieldStatus, toDTO, type PingDTO } from "@/lib/tracking";
import PageHeader from "@/components/PageHeader";
import LiveTracking, { type LivePerson } from "@/components/LiveTracking";

export default async function TrackingPage({ searchParams }: { searchParams: { employee?: string; date?: string } }) {
  await requireUser(can.viewTracking);
  const today = dateKey();
  const date = /^\d{4}-\d{2}-\d{2}$/.test(searchParams.date ?? "") && searchParams.date! <= today ? searchParams.date! : today;
  const isToday = date === today;
  const { start, end } = dayRange(date);
  const since = new Date().toISOString();

  const [people, statuses, lastPings, counts] = await Promise.all([
    prisma.employee.findMany({
      where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } },
      orderBy: { firstName: "asc" },
    }),
    fieldStatus(date, isToday),
    // Latest point per employee for the day.
    prisma.locationPing.findMany({
      where: { recordedAt: { gte: start, lt: end } },
      orderBy: { recordedAt: "desc" },
      distinct: ["employeeId"],
      include: { job: { select: { jobNo: true } } },
    }),
    prisma.locationPing.groupBy({ by: ["employeeId"], where: { recordedAt: { gte: start, lt: end } }, _count: true }),
  ]);

  const selected = people.find((p) => p.id === searchParams.employee)?.id ?? null;
  const trail: PingDTO[] = selected
    ? (
        await prisma.locationPing.findMany({
          where: { employeeId: selected, recordedAt: { gte: start, lt: end } },
          orderBy: { recordedAt: "asc" },
          include: { job: { select: { jobNo: true } } },
          take: 5000,
        })
      ).map(toDTO)
    : [];

  const live: LivePerson[] = people.map((p) => {
    const st = statuses.find((s) => s.id === p.id);
    const last = lastPings.find((x) => x.employeeId === p.id);
    return {
      id: p.id,
      name: fullName(p),
      short: shortName(p),
      phone: p.phone,
      shiftOpen: st?.shiftOpen ?? false,
      shiftStart: st?.shiftStart ?? null,
      shiftEnd: st?.shiftEnd ?? null,
      visit: st?.visit ?? null,
      last: last ? toDTO(last) : null,
      count: counts.find((c) => c.employeeId === p.id)?._count ?? 0,
    };
  });

  return (
    <div>
      <PageHeader
        title="ติดตามตำแหน่งช่าง (Realtime)"
        subtitle={`ตำแหน่งอัปเดตสดบนแผนที่ — บันทึกเฉพาะเวลางาน: ระหว่างช่างกด “เริ่มงาน” ถึง “เลิกงาน” และอยู่ในช่วง ${TRACKING_START}–${TRACKING_END} น.`}
        actions={
          <form className="flex gap-2" action="/tracking">
            <input type="date" name="date" defaultValue={date} max={today} className="input" aria-label="วันที่" />
            <button className="btn btn-secondary shrink-0 whitespace-nowrap">ดูวันที่นี้</button>
          </form>
        }
      />
      <LiveTracking
        key={date}
        date={date}
        isToday={isToday}
        since={since}
        people={live}
        initialSelected={selected}
        initialTrail={trail}
        liveWindowMs={LIVE_WINDOW_MS}
        sourceLabels={PING_SOURCE_LABEL}
      />
    </div>
  );
}
