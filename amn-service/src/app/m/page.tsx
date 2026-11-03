import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { OPEN_JOB_STATUS } from "@/lib/constants";
import { addDays, dateKey, dayRange, formatDateLong, formatTime, monthKey, monthRange } from "@/lib/datetime";
import { jobListInclude } from "@/lib/jobs";
import { getOpenShift } from "@/lib/shift";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import JobList from "@/components/JobList";
import PushToggle from "@/components/PushToggle";
import AutoRefresh from "@/components/AutoRefresh";
import { pushPublicKey } from "@/lib/notify";
import { endShiftAction, startShiftAction } from "./actions";

export default async function MyJobsPage({ searchParams }: { searchParams: { done?: string } }) {
  const user = await requireUser(can.doFieldWork);
  const today = dateKey();
  const { start: todayStart, end: todayEnd } = dayRange(today);
  const { start: monthStart, end: monthEnd } = monthRange(monthKey());

  const [shift, openVisit, jobs, completedThisMonth, visitsToday] = await Promise.all([
    getOpenShift(user.id),
    prisma.jobVisit.findFirst({
      where: { employeeId: user.id, outcome: null, checkOutAt: null },
      include: { job: { include: jobListInclude } },
    }),
    prisma.job.findMany({
      where: { status: { in: OPEN_JOB_STATUS }, assignments: { some: { employeeId: user.id } } },
      include: jobListInclude,
      orderBy: { scheduledAt: "asc" },
    }),
    prisma.job.count({
      where: { status: "COMPLETED", completedAt: { gte: monthStart, lt: monthEnd }, assignments: { some: { employeeId: user.id } } },
    }),
    prisma.jobVisit.count({ where: { employeeId: user.id, checkInAt: { gte: todayStart, lt: todayEnd } } }),
  ]);

  const others = jobs.filter((j) => j.id !== openVisit?.jobId);
  const todayJobs = others.filter((j) => j.scheduledAt >= todayStart && j.scheduledAt < todayEnd);
  const pending = others.filter((j) => j.scheduledAt < todayStart);
  const upcoming = others.filter((j) => j.scheduledAt >= todayEnd && j.scheduledAt < dayRange(addDays(today, 14)).end);
  const later = others.length - todayJobs.length - pending.length - upcoming.length;

  return (
    <div className="space-y-5">
      <AutoRefresh />
      <PushToggle publicKey={pushPublicKey} compact />
      {searchParams.done && (
        <div role="status" className="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          ✅ {searchParams.done}
        </div>
      )}

      <section className="card p-4">
        <div className="flex items-center justify-between">
          <div>
            <div className="text-xs text-gray-500">{formatDateLong(new Date())}</div>
            <div className="font-semibold text-gray-900">
              {shift ? `เริ่มงานแล้ว ${formatTime(shift.startAt)} น.` : "ยังไม่ได้เริ่มงาน"}
            </div>
          </div>
          <div className="text-right text-xs text-gray-500">
            เข้าหน้างานวันนี้ {visitsToday} ครั้ง
            <br />
            เสร็จเดือนนี้ {completedThisMonth} งาน
          </div>
        </div>
        {shift ? (
          <ActionForm action={endShiftAction} captureLocation confirm="ยืนยันเลิกงาน? ระบบจะหยุดแชร์ตำแหน่ง" className="mt-3">
            <SubmitButton className="btn btn-secondary w-full" pendingText="กำลังบันทึก…">
              🏁 เลิกงาน
            </SubmitButton>
          </ActionForm>
        ) : (
          <ActionForm action={startShiftAction} captureLocation className="mt-3">
            <SubmitButton className="btn btn-primary btn-lg w-full" pendingText="กำลังบันทึก…">
              ▶️ เริ่มงาน
            </SubmitButton>
            <p className="mt-2 text-center text-xs text-gray-500">
              เมื่อเริ่มงาน ระบบจะแชร์ตำแหน่งของคุณกับออฟฟิศเฉพาะระหว่างเวลางาน จนกว่าจะกดเลิกงาน
            </p>
          </ActionForm>
        )}
      </section>

      {shift && !user.trackingTokenHash && (
        <Link href="/m/tracker" className="block rounded-xl bg-sky-50 p-3 text-sm text-sky-900">
          📲 ล็อกจอหรือใช้ Google Maps แล้วตำแหน่งหยุดส่ง? <b className="underline">ตั้งค่าติดตามแม้ปิดหน้าจอ →</b>
        </Link>
      )}

      {openVisit && (
        <Link href={`/m/jobs/${openVisit.jobId}`} className="block rounded-xl border-2 border-indigo-300 bg-indigo-50 p-4">
          <div className="text-xs font-semibold text-indigo-700">กำลังทำงานอยู่ · เช็คอิน {formatTime(openVisit.checkInAt)} น.</div>
          <div className="mt-1 font-bold text-gray-900">{openVisit.job.machine.site.customer.name}</div>
          <div className="text-sm text-gray-700">
            {openVisit.job.jobNo} · {openVisit.job.machine.machineModel.brand} {openVisit.job.machine.machineModel.model}
          </div>
          <div className="mt-2 text-sm font-semibold text-indigo-700">✍️ เขียนสรุปงาน / ส่งงาน →</div>
        </Link>
      )}

      <section>
        <h2 className="mb-2 font-semibold text-gray-800">งานวันนี้ ({todayJobs.length})</h2>
        <JobList jobs={todayJobs} hrefBase="/m/jobs" variant="cards" empty="วันนี้ไม่มีงานนัด" />
      </section>

      {pending.length > 0 && (
        <section>
          <h2 className="mb-2 font-semibold text-orange-800">งานค้าง / Hold ({pending.length})</h2>
          <JobList jobs={pending} hrefBase="/m/jobs" variant="cards" />
        </section>
      )}

      <section>
        <h2 className="mb-2 font-semibold text-gray-800">งานล่วงหน้า 14 วัน ({upcoming.length})</h2>
        <JobList jobs={upcoming} hrefBase="/m/jobs" variant="cards" empty="ยังไม่มีงานล่วงหน้า" />
        {later > 0 && <p className="mt-2 text-xs text-gray-500">และอีก {later} งานที่นัดหลังจากนั้น</p>}
      </section>
    </div>
  );
}
