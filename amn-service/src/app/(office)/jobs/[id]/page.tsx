import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { CUSTOMER_TYPE_LABEL, SKILL_LABEL } from "@/lib/constants";
import { formatDate, formatDateTime, formatTime, toLocalInput } from "@/lib/datetime";
import { mapsUrl } from "@/lib/geo";
import { rankCandidates } from "@/lib/jobs";
import { fullName } from "@/lib/people";
import { PriorityBadge, SkillDots, StatusBadge, TypeLabel } from "@/components/Badges";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import JobFields from "@/components/JobFields";
import VisitList from "@/components/VisitList";
import EventLog from "@/components/EventLog";
import {
  assignAction,
  cancelJobAction,
  reopenJobAction,
  setLeadAction,
  unassignAction,
  updateJobAction,
} from "../actions";

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 gap-2 py-1.5 text-sm">
      <dt className="text-gray-500">{label}</dt>
      <dd className="col-span-2 text-gray-900">{children}</dd>
    </div>
  );
}

export default async function JobDetailPage({ params, searchParams }: { params: { id: string }; searchParams: { created?: string } }) {
  const user = await requireUser(can.useOffice);
  const job = await prisma.job.findUnique({
    where: { id: params.id },
    include: {
      machine: { include: { machineModel: true, site: { include: { customer: true } } } },
      assignments: { include: { employee: true }, orderBy: [{ isLead: "desc" }, { assignedAt: "asc" }] },
      visits: { include: { employee: true }, orderBy: { checkInAt: "desc" } },
      events: { include: { actor: true }, orderBy: { createdAt: "desc" } },
      createdBy: true,
    },
  });
  if (!job) notFound();
  await prisma.notification.updateMany({ where: { employeeId: user.id, jobId: job.id, readAt: null }, data: { readAt: new Date() } });

  const { machine } = job;
  const { site } = machine;
  const closed = job.status === "COMPLETED" || job.status === "CANCELLED";
  const canAssign = can.assignJobs(user.role) && !closed;
  const canManage = can.manageJobs(user.role);
  const assignedIds = new Set(job.assignments.map((a) => a.employeeId));
  const onSite = new Set(job.visits.filter((v) => !v.outcome && !v.checkOutAt).map((v) => v.employeeId));
  const candidates = canAssign ? (await rankCandidates(job)).filter((c) => !assignedIds.has(c.employee.id)) : [];
  const nav = mapsUrl(site.latitude, site.longitude, [site.address, site.province].filter(Boolean).join(" "));
  const prevJobs = await prisma.job.count({ where: { machineId: machine.id, id: { not: job.id } } });

  return (
    <div>
      {searchParams.created && (
        <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          ลงงาน {job.jobNo} เรียบร้อย — {canAssign ? "เลือกช่างด้านขวาเพื่อจ่ายงาน" : "รอหัวหน้าช่างจ่ายงาน"}
        </div>
      )}

      <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link href="/jobs" className="text-sm text-gray-500 hover:text-gray-800">
            ← งานบริการ
          </Link>
          <h1 className="mt-1 text-xl font-bold text-gray-900 sm:text-2xl">
            {job.jobNo} · {site.customer.name}
          </h1>
          <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
            <StatusBadge status={job.status} />
            <PriorityBadge priority={job.priority} />
            <span className="badge bg-gray-100 text-gray-700">
              <TypeLabel type={job.type} />
            </span>
            <span>นัดเข้า {formatDateTime(job.scheduledAt)}</span>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <section className="card p-5">
            <h2 className="section-title">งานที่ต้องทำ</h2>
            <p className="whitespace-pre-wrap text-gray-900">{job.problem}</p>
            <dl className="mt-3 divide-y divide-gray-100">
              {(job.reporterName || job.reporterPhone) && (
                <Row label="ผู้แจ้ง">
                  {job.reporterName}{" "}
                  {job.reporterPhone && (
                    <a href={`tel:${job.reporterPhone}`} className="link">
                      {job.reporterPhone}
                    </a>
                  )}
                </Row>
              )}
              {job.estimatedHours != null && <Row label="ใช้เวลาประมาณ">{job.estimatedHours} ชม.</Row>}
              {job.officeNotes && <Row label="หมายเหตุ">{job.officeNotes}</Row>}
              <Row label="ลงงานโดย">
                {fullName(job.createdBy)} · {formatDateTime(job.createdAt)}
              </Row>
              {job.status === "COMPLETED" && (
                <Row label="ส่งงานเมื่อ">{formatDateTime(job.completedAt)}</Row>
              )}
              {job.status === "CANCELLED" && <Row label="เหตุที่ยกเลิก">{job.cancelReason}</Row>}
            </dl>
            {job.resolution && (
              <div className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                <div className="font-semibold">สรุปการแก้ไข</div>
                <p className="whitespace-pre-wrap">{job.resolution}</p>
              </div>
            )}
          </section>

          <section className="card p-5">
            <h2 className="section-title">ลูกค้า · สถานที่ · เครื่อง</h2>
            <dl className="divide-y divide-gray-100">
              <Row label="ลูกค้า">
                <Link href={`/customers/${site.customer.id}`} className="link">
                  {site.customer.name}
                </Link>{" "}
                <span className="text-gray-500">
                  ({CUSTOMER_TYPE_LABEL[site.customer.type as keyof typeof CUSTOMER_TYPE_LABEL] ?? site.customer.type})
                </span>
              </Row>
              <Row label="สถานที่">
                {site.name}
                {(site.address || site.province) && (
                  <div className="text-gray-500">{[site.address, site.province].filter(Boolean).join(" ")}</div>
                )}
                {nav && (
                  <a href={nav} target="_blank" rel="noreferrer" className="link text-sm">
                    🧭 เปิดแผนที่นำทาง
                  </a>
                )}
              </Row>
              {(site.contactName || site.contactPhone) && (
                <Row label="ผู้ติดต่อหน้างาน">
                  {site.contactName}{" "}
                  {site.contactPhone && (
                    <a href={`tel:${site.contactPhone}`} className="link">
                      {site.contactPhone}
                    </a>
                  )}
                </Row>
              )}
              <Row label="เครื่อง">
                <Link href={`/machines/${machine.id}`} className="link">
                  {machine.machineCode}
                </Link>{" "}
                — {machine.machineModel.brand} {machine.machineModel.model}
                <div className="text-gray-500">
                  {machine.machineModel.category}
                  {machine.serialNumber && ` · S/N ${machine.serialNumber}`}
                </div>
              </Row>
              {machine.locationDetail && <Row label="ตำแหน่งเครื่อง">{machine.locationDetail}</Row>}
              {machine.warrantyEnd && (
                <Row label="ประกัน">
                  ถึง {formatDate(machine.warrantyEnd)}
                  {machine.warrantyEnd < new Date() ? (
                    <span className="ml-1 badge bg-gray-100 text-gray-600">หมดประกัน</span>
                  ) : (
                    <span className="ml-1 badge bg-emerald-100 text-emerald-800">ในประกัน</span>
                  )}
                </Row>
              )}
              <Row label="ประวัติการซ่อม">
                <Link href={`/machines/${machine.id}`} className="link">
                  {prevJobs} งานก่อนหน้า
                </Link>
              </Row>
            </dl>
          </section>

          <section className="card p-5">
            <h2 className="section-title">รายงานการเข้าหน้างาน ({job.visits.length} ครั้ง)</h2>
            <VisitList visits={job.visits} />
          </section>

          {canManage && !closed && (
            <details className="card p-5">
              <summary className="cursor-pointer font-semibold text-gray-800">✏️ แก้ไขงาน / เลื่อนนัด</summary>
              <ActionForm action={updateJobAction.bind(null, job.id)} className="mt-4 space-y-4">
                <JobFields job={job} />
                <SubmitButton>บันทึกการแก้ไข</SubmitButton>
              </ActionForm>
            </details>
          )}

          <section className="card p-5">
            <h2 className="section-title">ประวัติการดำเนินการ</h2>
            <EventLog events={job.events} />
          </section>
        </div>

        <div className="space-y-6">
          <section className="card p-5">
            <h2 className="section-title">ช่างที่รับผิดชอบ</h2>
            {job.assignments.length === 0 ? (
              <p className="text-sm text-amber-700">
                ยังไม่ได้จ่ายงาน{!canAssign && !closed && " — รอหัวหน้าช่างเลือกช่าง"}
              </p>
            ) : (
              <ul className="space-y-2">
                {job.assignments.map((a) => (
                  <li key={a.id} className="flex items-start justify-between gap-2 rounded-lg bg-gray-50 p-2.5">
                    <div className="min-w-0 text-sm">
                      <div className="font-semibold text-gray-900">
                        {a.isLead && <span title="หัวหน้างาน">★ </span>}
                        <Link href={`/technicians/${a.employeeId}`} className="hover:underline">
                          {fullName(a.employee)}
                        </Link>
                      </div>
                      <div className="text-xs text-gray-500">
                        {a.employee.phone && (
                          <a href={`tel:${a.employee.phone}`} className="link">
                            {a.employee.phone}
                          </a>
                        )}
                        {onSite.has(a.employeeId) && <span className="ml-1 text-indigo-700">· อยู่หน้างาน</span>}
                      </div>
                    </div>
                    {canAssign && (
                      <div className="flex shrink-0 gap-1">
                        {!a.isLead && (
                          <ActionForm action={setLeadAction.bind(null, job.id, a.employeeId)}>
                            <SubmitButton className="btn btn-secondary px-2 py-1 text-xs" pendingText="…">
                              ตั้งหัวหน้างาน
                            </SubmitButton>
                          </ActionForm>
                        )}
                        <ActionForm
                          action={unassignAction.bind(null, job.id, a.employeeId)}
                          confirm={`ถอน ${fullName(a.employee)} ออกจากงานนี้?`}
                        >
                          <SubmitButton className="btn btn-danger px-2 py-1 text-xs" pendingText="…">
                            ถอน
                          </SubmitButton>
                        </ActionForm>
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </section>

          {canAssign && (
            <section className="card p-5">
              <h2 className="section-title">จ่ายงานตามความถนัด</h2>
              <p className="mb-3 text-xs text-gray-500">
                เรียงจากความถนัดกับรุ่น {machine.machineModel.brand} {machine.machineModel.model} → ความถนัดในหมวด “
                {machine.machineModel.category}” → งานวันเดียวกันน้อยที่สุด
              </p>
              {candidates.length === 0 ? (
                <p className="text-sm text-gray-400">ไม่มีช่างที่ว่างให้เลือก</p>
              ) : (
                <ul className="divide-y divide-gray-100">
                  {candidates.map((c) => (
                    <li key={c.employee.id} className="py-2.5">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 text-sm">
                          <div className="font-semibold text-gray-900">{fullName(c.employee)}</div>
                          <div className="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                            <SkillDots level={c.exactLevel} />
                            <span>
                              {c.exactLevel
                                ? SKILL_LABEL[c.exactLevel]
                                : c.categoryLevel
                                  ? `ไม่เคยทำรุ่นนี้ · หมวดเดียวกันระดับ ${c.categoryLevel}`
                                  : "ไม่มีข้อมูลความถนัด"}
                            </span>
                          </div>
                          <div className="mt-0.5 text-xs text-gray-500">
                            งานค้าง {c.openCount} งาน
                            {c.sameDay.length > 0 && (
                              <span className="text-orange-700">
                                {" "}
                                · วันเดียวกัน {c.sameDay.length} งาน (
                                {c.sameDay.map((j) => formatTime(j.scheduledAt)).join(", ")})
                              </span>
                            )}
                          </div>
                        </div>
                        <ActionForm action={assignAction.bind(null, job.id, c.employee.id)}>
                          <SubmitButton className="btn btn-primary px-3 py-1.5 text-xs" pendingText="…">
                            จ่ายงาน
                          </SubmitButton>
                        </ActionForm>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}

          {canManage && !closed && (
            <details className="card p-5">
              <summary className="cursor-pointer text-sm font-semibold text-red-700">ยกเลิกงาน</summary>
              <ActionForm action={cancelJobAction.bind(null, job.id)} confirm="ยืนยันยกเลิกงานนี้?" className="mt-3 space-y-2">
                <textarea name="cancelReason" required rows={2} placeholder="เหตุผลที่ยกเลิก" className="input" />
                <SubmitButton className="btn btn-danger">ยกเลิกงาน</SubmitButton>
              </ActionForm>
            </details>
          )}

          {canManage && closed && (
            <details className="card p-5">
              <summary className="cursor-pointer text-sm font-semibold text-gray-800">เปิดงานใหม่</summary>
              <ActionForm action={reopenJobAction.bind(null, job.id)} className="mt-3 space-y-2">
                <textarea name="reason" required rows={2} placeholder="เหตุผล เช่น ลูกค้าแจ้งว่าอาการเดิมกลับมา" className="input" />
                <label className="label">นัดเข้าใหม่</label>
                <input type="datetime-local" name="scheduledAt" defaultValue={toLocalInput(job.scheduledAt)} className="input" />
                <SubmitButton className="btn btn-secondary">เปิดงานใหม่</SubmitButton>
              </ActionForm>
            </details>
          )}
        </div>
      </div>
    </div>
  );
}
