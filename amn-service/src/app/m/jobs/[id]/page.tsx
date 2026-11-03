import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { WORKABLE_JOB_STATUS, type JobStatus } from "@/lib/constants";
import { formatDate, formatDateTime, formatTime } from "@/lib/datetime";
import { mapsUrl } from "@/lib/geo";
import { fullName, shortName } from "@/lib/people";
import { PriorityBadge, StatusBadge, TypeLabel } from "@/components/Badges";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import ReportForm from "@/components/ReportForm";
import VisitList from "@/components/VisitList";
import { checkInAction, submitReportAction } from "../../actions";

export default async function MobileJobPage({ params }: { params: { id: string } }) {
  const user = await requireUser(can.doFieldWork);

  // Technicians only ever see jobs assigned to them.
  const job = await prisma.job.findFirst({
    where: { id: params.id, assignments: { some: { employeeId: user.id } } },
    include: {
      machine: { include: { machineModel: true, site: { include: { customer: true } } } },
      assignments: { include: { employee: true }, orderBy: [{ isLead: "desc" }, { assignedAt: "asc" }] },
      visits: { include: { employee: true }, orderBy: { checkInAt: "desc" } },
    },
  });
  if (!job) notFound();
  await prisma.notification.updateMany({ where: { employeeId: user.id, jobId: job.id, readAt: null }, data: { readAt: new Date() } });

  const machineHistory = await prisma.job.findMany({
    where: { machineId: job.machineId, id: { not: job.id }, status: "COMPLETED" },
    orderBy: { completedAt: "desc" },
    take: 5,
    select: { id: true, jobNo: true, completedAt: true, problem: true, resolution: true },
  });

  const { machine } = job;
  const { site } = machine;
  const myOpen = job.visits.find((v) => v.employeeId === user.id && !v.outcome && !v.checkOutAt);
  const workable = WORKABLE_JOB_STATUS.includes(job.status as JobStatus);
  const nav = mapsUrl(site.latitude, site.longitude, [site.address, site.province].filter(Boolean).join(" "));
  const contactPhone = site.contactPhone || job.reporterPhone;

  return (
    <div className="space-y-4">
      <Link href="/m" className="text-sm text-gray-500">
        ← งานของฉัน
      </Link>

      <section className="card p-4">
        <div className="flex items-center justify-between gap-2">
          <span className="text-xs font-semibold text-gray-500">{job.jobNo}</span>
          <span className="flex gap-1">
            <PriorityBadge priority={job.priority} />
            <StatusBadge status={job.status} />
          </span>
        </div>
        <h1 className="mt-1 text-lg font-bold text-gray-900">{site.customer.name}</h1>
        <div className="text-sm text-gray-600">{site.name}</div>
        {(site.address || site.province) && (
          <div className="text-sm text-gray-500">{[site.address, site.province].filter(Boolean).join(" ")}</div>
        )}
        <div className="mt-2 text-sm">
          🕘 นัด <span className="font-semibold">{formatDateTime(job.scheduledAt)}</span>
          {job.estimatedHours != null && <span className="text-gray-500"> · ประมาณ {job.estimatedHours} ชม.</span>}
        </div>
        <div className="mt-3 grid grid-cols-2 gap-2">
          {nav ? (
            <a href={nav} target="_blank" rel="noreferrer" className="btn btn-secondary">
              🧭 นำทาง
            </a>
          ) : (
            <span className="btn btn-secondary opacity-50">ไม่มีพิกัด</span>
          )}
          {contactPhone ? (
            <a href={`tel:${contactPhone}`} className="btn btn-secondary">
              📞 โทรหน้างาน
            </a>
          ) : (
            <span className="btn btn-secondary opacity-50">ไม่มีเบอร์</span>
          )}
        </div>
      </section>

      <section className="card p-4">
        <h2 className="mb-1 text-sm font-semibold text-gray-500">
          <TypeLabel type={job.type} /> · งานที่ต้องทำ
        </h2>
        <p className="whitespace-pre-wrap text-gray-900">{job.problem}</p>
        {job.officeNotes && (
          <p className="mt-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-900">📝 {job.officeNotes}</p>
        )}
        <dl className="mt-3 space-y-1 text-sm">
          <div>
            <dt className="inline text-gray-500">เครื่อง: </dt>
            <dd className="inline font-medium">
              {machine.machineModel.brand} {machine.machineModel.model}
            </dd>
          </div>
          <div>
            <dt className="inline text-gray-500">รหัสเครื่อง: </dt>
            <dd className="inline">
              {machine.machineCode}
              {machine.serialNumber && ` · S/N ${machine.serialNumber}`}
            </dd>
          </div>
          {machine.locationDetail && (
            <div>
              <dt className="inline text-gray-500">ตำแหน่งเครื่อง: </dt>
              <dd className="inline">{machine.locationDetail}</dd>
            </div>
          )}
          {(site.contactName || job.reporterName) && (
            <div>
              <dt className="inline text-gray-500">ผู้ติดต่อ: </dt>
              <dd className="inline">{site.contactName || job.reporterName}</dd>
            </div>
          )}
          <div>
            <dt className="inline text-gray-500">ทีม: </dt>
            <dd className="inline">
              {job.assignments.map((a) => `${a.isLead ? "★" : ""}${shortName(a.employee)}`).join(", ")}
            </dd>
          </div>
        </dl>
      </section>

      {workable && !myOpen && (
        <section className="card p-4">
          <ActionForm action={checkInAction.bind(null, job.id)} captureLocation>
            <SubmitButton className="btn btn-primary btn-lg w-full" pendingText="กำลังบันทึก…">
              📍 ถึงหน้างานแล้ว
            </SubmitButton>
            <p className="mt-2 text-center text-xs text-gray-500">
              กดเมื่อถึงหน้างาน — ระบบจะบันทึกเวลาและตำแหน่งจากโทรศัพท์ แล้วแจ้งออฟฟิศว่าคุณถึงแล้ว
            </p>
          </ActionForm>
        </section>
      )}

      {workable && myOpen && (
        <section className="card border-indigo-200 p-4">
          <h2 className="mb-1 font-semibold text-gray-900">สรุปงาน → เสร็จแล้ว / Hold</h2>
          <p className="mb-3 text-xs text-gray-500">
            ถึงหน้างานเมื่อ {formatTime(myOpen.checkInAt)} น. · เมื่อกดส่ง ระบบจะบันทึกตำแหน่งตอนเสร็จงานด้วย
          </p>
          <ReportForm action={submitReportAction.bind(null, job.id)} />
        </section>
      )}

      <section className="card p-4">
        <h2 className="mb-2 font-semibold text-gray-800">การเข้าหน้างานของงานนี้</h2>
        <VisitList visits={job.visits} showDistance={false} />
      </section>

      {machineHistory.length > 0 && (
        <section className="card p-4">
          <h2 className="mb-2 font-semibold text-gray-800">ประวัติซ่อมเครื่องนี้ (ล่าสุด)</h2>
          <ul className="space-y-3">
            {machineHistory.map((h) => (
              <li key={h.id} className="text-sm">
                <div className="text-xs text-gray-500">
                  {h.jobNo} · {formatDate(h.completedAt)}
                </div>
                <div className="text-gray-700">อาการ: {h.problem}</div>
                {h.resolution && <div className="text-gray-900">แก้ไข: {h.resolution}</div>}
              </li>
            ))}
          </ul>
        </section>
      )}

      {!workable && (
        <p className="text-center text-sm text-gray-500">
          งานนี้{job.status === "COMPLETED" ? `ส่งงานแล้วเมื่อ ${formatDateTime(job.completedAt)}` : "ถูกยกเลิก"} ·{" "}
          {fullName(user)}
        </p>
      )}
    </div>
  );
}
