import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { MACHINE_STATUS_LABEL } from "@/lib/constants";
import { formatDate, formatDateTime } from "@/lib/datetime";
import { fullName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import MachineFields from "@/components/MachineFields";
import { StatusBadge, TypeLabel } from "@/components/Badges";
import { updateMachineAction } from "../../customers/actions";

/** ประวัติการซ่อมของเครื่อง */
export default async function MachineDetailPage({ params }: { params: { id: string } }) {
  const user = await requireUser(can.useOffice);
  const machine = await prisma.machine.findUnique({
    where: { id: params.id },
    include: {
      machineModel: true,
      site: { include: { customer: { include: { sites: true } } } },
      jobs: {
        orderBy: { scheduledAt: "desc" },
        include: {
          assignments: { include: { employee: true } },
          visits: { include: { employee: true }, orderBy: { checkInAt: "asc" } },
        },
      },
    },
  });
  if (!machine) notFound();
  const editable = can.manageCustomers(user.role);
  const models = editable ? await prisma.machineModel.findMany({ orderBy: [{ brand: "asc" }, { model: "asc" }] }) : [];
  const completed = machine.jobs.filter((j) => j.status === "COMPLETED").length;

  return (
    <div>
      <Link href="/machines" className="text-sm text-gray-500 hover:text-gray-800">
        ← เครื่องลูกค้า
      </Link>
      <PageHeader
        title={`${machine.machineCode} · ${machine.machineModel.brand} ${machine.machineModel.model}`}
        subtitle={
          <>
            <Link href={`/customers/${machine.site.customerId}`} className="link">
              {machine.site.customer.name}
            </Link>{" "}
            · {machine.site.name}
            {machine.locationDetail && ` · ${machine.locationDetail}`}
          </>
        }
        actions={
          can.manageJobs(user.role) &&
          machine.status === "ACTIVE" && (
            <Link href={`/jobs/new?machine=${machine.id}`} className="btn btn-primary">
              ➕ ลงงานให้เครื่องนี้
            </Link>
          )
        }
      />

      <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          { label: "หมวด", value: machine.machineModel.category },
          { label: "S/N", value: machine.serialNumber ?? "-" },
          { label: "ประกันถึง", value: formatDate(machine.warrantyEnd) },
          { label: "งานทั้งหมด / เสร็จ", value: `${machine.jobs.length} / ${completed}` },
        ].map((s) => (
          <div key={s.label} className="card p-3">
            <div className="text-xs text-gray-500">{s.label}</div>
            <div className="font-semibold text-gray-900">{s.value}</div>
          </div>
        ))}
      </div>

      <section className="card p-5">
        <h2 className="section-title">ประวัติการซ่อมของเครื่อง</h2>
        {machine.jobs.length === 0 ? (
          <p className="text-sm text-gray-400">ยังไม่เคยมีงานบริการ</p>
        ) : (
          <ol className="space-y-4">
            {machine.jobs.map((j) => (
              <li key={j.id} className="rounded-lg border border-gray-200 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="text-sm">
                    <Link href={`/jobs/${j.id}`} className="link">
                      {j.jobNo}
                    </Link>{" "}
                    · <TypeLabel type={j.type} /> · {formatDateTime(j.scheduledAt)}
                  </div>
                  <StatusBadge status={j.status} />
                </div>
                <p className="mt-1 text-sm text-gray-800">
                  <span className="font-semibold">อาการ:</span> {j.problem}
                </p>
                {j.visits.map((v, i) => (
                  <div key={v.id} className="mt-2 border-l-2 border-gray-200 pl-3 text-sm">
                    <div className="text-xs text-gray-500">
                      ครั้งที่ {i + 1} · {formatDateTime(v.checkInAt)} · {fullName(v.employee)}
                      {v.outcome === "HOLD" && <span className="text-orange-700"> · Hold: {v.holdReason}</span>}
                      {v.outcome === "COMPLETED" && <span className="text-emerald-700"> · ส่งงาน</span>}
                    </div>
                    {v.summary && <p className="whitespace-pre-wrap text-gray-800">{v.summary}</p>}
                    {v.partsUsed && <p className="text-gray-600">อะไหล่: {v.partsUsed}</p>}
                  </div>
                ))}
                {j.visits.length === 0 && (
                  <p className="mt-1 text-xs text-gray-500">
                    ช่าง: {j.assignments.map((a) => fullName(a.employee)).join(", ") || "ยังไม่จ่ายงาน"}
                  </p>
                )}
              </li>
            ))}
          </ol>
        )}
      </section>

      {editable && (
        <details className="card mt-6 p-5">
          <summary className="cursor-pointer font-semibold text-gray-800">✏️ แก้ไขข้อมูลเครื่อง</summary>
          <ActionForm action={updateMachineAction.bind(null, machine.id)} className="mt-4 space-y-3">
            <MachineFields models={models} sites={machine.site.customer.sites} machine={machine} withStatus />
            <p className="text-xs text-gray-500">สถานะปัจจุบัน: {MACHINE_STATUS_LABEL[machine.status as keyof typeof MACHINE_STATUS_LABEL]}</p>
            <SubmitButton>บันทึก</SubmitButton>
          </ActionForm>
        </details>
      )}
    </div>
  );
}
