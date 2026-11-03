import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import JobFields from "@/components/JobFields";
import MachinePicker, { type PickerMachine } from "@/components/MachinePicker";
import { createJobAction } from "../actions";

function nextHour() {
  const d = new Date();
  d.setMinutes(0, 0, 0);
  d.setHours(d.getHours() + 1);
  return d;
}

export default async function NewJobPage({ searchParams }: { searchParams: { machine?: string } }) {
  await requireUser(can.manageJobs);

  const machines = await prisma.machine.findMany({
    where: { status: "ACTIVE" },
    include: { machineModel: true, site: { include: { customer: true } } },
    orderBy: [{ site: { customer: { name: "asc" } } }, { machineCode: "asc" }],
  });

  const options: PickerMachine[] = machines.map((m) => ({
    id: m.id,
    code: m.machineCode,
    model: `${m.machineModel.brand} ${m.machineModel.model}`,
    serial: m.serialNumber,
    customer: m.site.customer.name,
    site: m.site.name,
    address: [m.site.address, m.site.province].filter(Boolean).join(" "),
    location: m.locationDetail,
    contact: [m.site.contactName, m.site.contactPhone].filter(Boolean).join(" ") || null,
  }));

  return (
    <div className="max-w-3xl">
      <PageHeader
        title="ลงงานใหม่"
        subtitle="แอดมินลงงาน: เลือกเครื่องของลูกค้า และนัดวันเวลาเข้าหน้างาน — หัวหน้าช่างจะเป็นผู้จ่ายงานให้ช่างตามความถนัด"
      />
      {options.length === 0 ? (
        <div className="card p-6 text-sm text-gray-600">
          ยังไม่มีเครื่องของลูกค้าในระบบ —{" "}
          <Link href="/customers" className="link">
            เพิ่มลูกค้าและเครื่องก่อน
          </Link>
        </div>
      ) : (
        <ActionForm action={createJobAction} className="card space-y-5 p-5 sm:p-6">
          <section>
            <h2 className="section-title">1. เครื่องของลูกค้า</h2>
            <MachinePicker machines={options} defaultId={searchParams.machine} />
            <p className="mt-2 text-xs text-gray-500">
              ไม่มีเครื่องในรายการ?{" "}
              <Link href="/customers" className="link">
                เพิ่มลูกค้า / เครื่องใหม่
              </Link>
            </p>
          </section>
          <section>
            <h2 className="section-title">2. รายละเอียดงาน</h2>
            <JobFields job={{ scheduledAt: nextHour() }} />
          </section>
          <SubmitButton>บันทึกงาน</SubmitButton>
        </ActionForm>
      )}
    </div>
  );
}
