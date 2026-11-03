import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { CUSTOMER_TYPE_LABEL, OPEN_JOB_STATUS } from "@/lib/constants";
import PageHeader from "@/components/PageHeader";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import CustomerFields from "@/components/CustomerFields";
import SiteFields from "@/components/SiteFields";
import { createCustomerAction } from "./actions";

export default async function CustomersPage({ searchParams }: { searchParams: { q?: string } }) {
  const user = await requireUser(can.useOffice);
  const q = (searchParams.q ?? "").trim();

  const customers = await prisma.customer.findMany({
    where: q ? { OR: [{ name: { contains: q } }, { customerCode: { contains: q } }, { contactName: { contains: q } }] } : undefined,
    include: { sites: { include: { _count: { select: { machines: true } } } } },
    orderBy: { name: "asc" },
  });
  const openByCustomer = await prisma.job.findMany({
    where: { status: { in: OPEN_JOB_STATUS } },
    select: { machine: { select: { site: { select: { customerId: true } } } } },
  });
  const openCount = new Map<string, number>();
  for (const j of openByCustomer) {
    const id = j.machine.site.customerId;
    openCount.set(id, (openCount.get(id) ?? 0) + 1);
  }

  return (
    <div>
      <PageHeader title="ลูกค้า" subtitle="ลูกค้าคือใคร ที่อยู่ สถานที่ (location) และเครื่องที่ติดตั้ง" />

      <form className="mb-4 flex gap-2" action="/customers">
        <input name="q" defaultValue={q} placeholder="ค้นหาชื่อ / รหัสลูกค้า / ผู้ติดต่อ" className="input max-w-md" />
        <button className="btn btn-secondary">ค้นหา</button>
      </form>

      <div className="card overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>รหัส</th>
              <th>ลูกค้า</th>
              <th>ประเภท</th>
              <th>ผู้ติดต่อ</th>
              <th>สถานที่</th>
              <th className="num">เครื่อง</th>
              <th className="num">งานค้าง</th>
            </tr>
          </thead>
          <tbody>
            {customers.map((c) => (
              <tr key={c.id}>
                <td className="whitespace-nowrap text-gray-500">{c.customerCode}</td>
                <td>
                  <Link href={`/customers/${c.id}`} className="link">
                    {c.name}
                  </Link>
                </td>
                <td>{CUSTOMER_TYPE_LABEL[c.type as keyof typeof CUSTOMER_TYPE_LABEL] ?? c.type}</td>
                <td>
                  {c.contactName}
                  {c.contactPhone && <div className="text-xs text-gray-500">{c.contactPhone}</div>}
                </td>
                <td>
                  {c.sites.map((s) => s.name + (s.province ? ` (${s.province})` : "")).join(", ")}
                </td>
                <td className="num">{c.sites.reduce((n, s) => n + s._count.machines, 0)}</td>
                <td className="num">{openCount.get(c.id) ?? 0}</td>
              </tr>
            ))}
            {customers.length === 0 && (
              <tr>
                <td colSpan={7} className="py-6 text-center text-gray-400">
                  ไม่พบลูกค้า
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {can.manageCustomers(user.role) && (
        <details className="card mt-6 p-5" open={customers.length === 0}>
          <summary className="cursor-pointer font-semibold text-gray-800">➕ เพิ่มลูกค้าใหม่</summary>
          <ActionForm action={createCustomerAction} className="mt-4 space-y-5">
            <div className="max-w-xs">
              <label className="label">รหัสลูกค้า *</label>
              <input name="customerCode" required className="input uppercase" placeholder="เช่น C-0001" />
            </div>
            <CustomerFields />
            <div>
              <h3 className="mb-2 text-sm font-semibold text-gray-700">สถานที่หลัก (ที่อยู่ / Location)</h3>
              <SiteFields />
            </div>
            <SubmitButton>บันทึกลูกค้า</SubmitButton>
          </ActionForm>
        </details>
      )}
    </div>
  );
}
