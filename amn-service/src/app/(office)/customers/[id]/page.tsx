import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { CUSTOMER_TYPE_LABEL, MACHINE_STATUS_LABEL } from "@/lib/constants";
import { mapsUrl } from "@/lib/geo";
import { jobListInclude } from "@/lib/jobs";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import CustomerFields from "@/components/CustomerFields";
import SiteFields from "@/components/SiteFields";
import MachineFields from "@/components/MachineFields";
import JobList from "@/components/JobList";
import { createMachineAction, createSiteAction, updateCustomerAction, updateSiteAction } from "../actions";

export default async function CustomerDetailPage({ params }: { params: { id: string } }) {
  const user = await requireUser(can.useOffice);
  const customer = await prisma.customer.findUnique({
    where: { id: params.id },
    include: {
      sites: {
        orderBy: { createdAt: "asc" },
        include: { machines: { include: { machineModel: true, _count: { select: { jobs: true } } }, orderBy: { machineCode: "asc" } } },
      },
    },
  });
  if (!customer) notFound();
  const editable = can.manageCustomers(user.role);

  const [models, jobs] = await Promise.all([
    editable ? prisma.machineModel.findMany({ where: { active: true }, orderBy: [{ brand: "asc" }, { model: "asc" }] }) : [],
    prisma.job.findMany({
      where: { machine: { site: { customerId: customer.id } } },
      include: jobListInclude,
      orderBy: { scheduledAt: "desc" },
      take: 30,
    }),
  ]);

  return (
    <div>
      <Link href="/customers" className="text-sm text-gray-500 hover:text-gray-800">
        ← ลูกค้า
      </Link>
      <PageHeader
        title={customer.name}
        subtitle={`${customer.customerCode} · ${CUSTOMER_TYPE_LABEL[customer.type as keyof typeof CUSTOMER_TYPE_LABEL] ?? customer.type}${
          customer.contactName ? ` · ติดต่อ ${customer.contactName}` : ""
        }${customer.contactPhone ? ` ${customer.contactPhone}` : ""}`}
      />

      <div className="space-y-4">
        {customer.sites.map((s) => {
          const nav = mapsUrl(s.latitude, s.longitude, [s.address, s.province].filter(Boolean).join(" "));
          return (
            <section key={s.id} className="card p-5">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h2 className="font-semibold text-gray-900">📍 {s.name}</h2>
                  <div className="text-sm text-gray-600">{[s.address, s.province].filter(Boolean).join(" ") || "ไม่มีที่อยู่"}</div>
                  <div className="text-xs text-gray-500">
                    {s.latitude != null ? `พิกัด ${s.latitude.toFixed(5)}, ${s.longitude?.toFixed(5)}` : "ยังไม่มีพิกัด"}
                    {s.contactName && ` · ผู้ติดต่อ ${s.contactName}`}
                    {s.contactPhone && ` ${s.contactPhone}`}
                  </div>
                </div>
                {nav && (
                  <a href={nav} target="_blank" rel="noreferrer" className="btn btn-secondary text-xs">
                    🧭 แผนที่
                  </a>
                )}
              </div>
              <div className="mt-3 overflow-x-auto">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>รหัสเครื่อง</th>
                      <th>รุ่น</th>
                      <th>S/N</th>
                      <th>ตำแหน่ง</th>
                      <th>สถานะ</th>
                      <th className="num">งานทั้งหมด</th>
                    </tr>
                  </thead>
                  <tbody>
                    {s.machines.map((m) => (
                      <tr key={m.id}>
                        <td>
                          <Link href={`/machines/${m.id}`} className="link">
                            {m.machineCode}
                          </Link>
                        </td>
                        <td>
                          {m.machineModel.brand} {m.machineModel.model}
                        </td>
                        <td>{m.serialNumber ?? "-"}</td>
                        <td>{m.locationDetail ?? "-"}</td>
                        <td>{MACHINE_STATUS_LABEL[m.status as keyof typeof MACHINE_STATUS_LABEL] ?? m.status}</td>
                        <td className="num">{m._count.jobs}</td>
                      </tr>
                    ))}
                    {s.machines.length === 0 && (
                      <tr>
                        <td colSpan={6} className="py-4 text-center text-gray-400">
                          ยังไม่มีเครื่องที่สถานที่นี้
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
              {editable && (
                <details className="mt-3">
                  <summary className="cursor-pointer text-sm text-gray-600">แก้ไขสถานที่นี้</summary>
                  <ActionForm action={updateSiteAction.bind(null, s.id)} className="mt-3 space-y-3">
                    <SiteFields site={s} />
                    <SubmitButton className="btn btn-secondary">บันทึกสถานที่</SubmitButton>
                  </ActionForm>
                </details>
              )}
            </section>
          );
        })}
      </div>

      {editable && (
        <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
          <details className="card p-5" open={customer.sites.every((s) => s.machines.length === 0)}>
            <summary className="cursor-pointer font-semibold text-gray-800">➕ เพิ่มเครื่องของลูกค้า</summary>
            {models.length === 0 ? (
              <p className="mt-3 text-sm text-gray-600">
                ยังไม่มีรุ่นเครื่องในระบบ —{" "}
                <Link href="/models" className="link">
                  เพิ่มรุ่นเครื่องก่อน
                </Link>
              </p>
            ) : (
              <ActionForm action={createMachineAction} className="mt-4 space-y-3">
                <MachineFields models={models} sites={customer.sites} withCode />
                <SubmitButton>บันทึกเครื่อง</SubmitButton>
              </ActionForm>
            )}
          </details>
          <div className="space-y-4">
            <details className="card p-5">
              <summary className="cursor-pointer font-semibold text-gray-800">➕ เพิ่มสถานที่ / สาขา</summary>
              <ActionForm action={createSiteAction.bind(null, customer.id)} resetOnSuccess className="mt-4 space-y-3">
                <SiteFields site={{ name: "" }} />
                <SubmitButton>เพิ่มสถานที่</SubmitButton>
              </ActionForm>
            </details>
            <details className="card p-5">
              <summary className="cursor-pointer font-semibold text-gray-800">✏️ แก้ไขข้อมูลลูกค้า</summary>
              <ActionForm action={updateCustomerAction.bind(null, customer.id)} className="mt-4 space-y-3">
                <CustomerFields customer={customer} />
                <SubmitButton>บันทึก</SubmitButton>
              </ActionForm>
            </details>
          </div>
        </div>
      )}

      <section className="mt-8">
        <h2 className="section-title">งานบริการของลูกค้ารายนี้ (ล่าสุด 30 งาน)</h2>
        <JobList jobs={jobs} empty="ยังไม่มีงาน" />
      </section>
    </div>
  );
}
