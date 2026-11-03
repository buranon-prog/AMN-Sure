import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { MACHINE_STATUS_LABEL, OPEN_JOB_STATUS } from "@/lib/constants";
import { formatDate } from "@/lib/datetime";
import PageHeader from "@/components/PageHeader";

export default async function MachinesPage({ searchParams }: { searchParams: { q?: string } }) {
  await requireUser(can.useOffice);
  const q = (searchParams.q ?? "").trim();
  const machines = await prisma.machine.findMany({
    where: q
      ? {
          OR: [
            { machineCode: { contains: q } },
            { serialNumber: { contains: q } },
            { machineModel: { model: { contains: q } } },
            { machineModel: { brand: { contains: q } } },
            { site: { customer: { name: { contains: q } } } },
          ],
        }
      : undefined,
    include: {
      machineModel: true,
      site: { include: { customer: true } },
      jobs: { select: { status: true, completedAt: true }, orderBy: { scheduledAt: "desc" } },
    },
    orderBy: { machineCode: "asc" },
    take: 500,
  });

  return (
    <div>
      <PageHeader
        title="เครื่องลูกค้า"
        subtitle="ทุกเครื่องที่ดูแล — กดรหัสเครื่องเพื่อดูประวัติการซ่อม (เพิ่มเครื่องได้จากหน้าลูกค้า)"
      />
      <form className="mb-4 flex gap-2" action="/machines">
        <input name="q" defaultValue={q} placeholder="ค้นหา รหัสเครื่อง / S/N / รุ่น / ลูกค้า" className="input max-w-md" />
        <button className="btn btn-secondary">ค้นหา</button>
      </form>
      <div className="card overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>รหัสเครื่อง</th>
              <th>รุ่น</th>
              <th>ลูกค้า / สถานที่</th>
              <th>S/N</th>
              <th>ประกันถึง</th>
              <th className="num">ซ่อมแล้ว</th>
              <th className="num">งานค้าง</th>
              <th>ซ่อมล่าสุด</th>
            </tr>
          </thead>
          <tbody>
            {machines.map((m) => {
              const done = m.jobs.filter((j) => j.status === "COMPLETED");
              const open = m.jobs.filter((j) => (OPEN_JOB_STATUS as string[]).includes(j.status)).length;
              const lastDone = done.map((j) => j.completedAt).filter(Boolean).sort((a, b) => b!.getTime() - a!.getTime())[0];
              return (
                <tr key={m.id} className={m.status !== "ACTIVE" ? "opacity-60" : ""}>
                  <td className="whitespace-nowrap">
                    <Link href={`/machines/${m.id}`} className="link">
                      {m.machineCode}
                    </Link>
                    {m.status !== "ACTIVE" && (
                      <div className="text-xs text-gray-500">{MACHINE_STATUS_LABEL[m.status as keyof typeof MACHINE_STATUS_LABEL]}</div>
                    )}
                  </td>
                  <td>
                    {m.machineModel.brand} {m.machineModel.model}
                    <div className="text-xs text-gray-500">{m.machineModel.category}</div>
                  </td>
                  <td>
                    <Link href={`/customers/${m.site.customerId}`} className="hover:underline">
                      {m.site.customer.name}
                    </Link>
                    <div className="text-xs text-gray-500">
                      {m.site.name}
                      {m.locationDetail ? ` · ${m.locationDetail}` : ""}
                    </div>
                  </td>
                  <td>{m.serialNumber ?? "-"}</td>
                  <td className="whitespace-nowrap">{formatDate(m.warrantyEnd)}</td>
                  <td className="num">{done.length}</td>
                  <td className="num">{open || "-"}</td>
                  <td className="whitespace-nowrap">{formatDate(lastDone)}</td>
                </tr>
              );
            })}
            {machines.length === 0 && (
              <tr>
                <td colSpan={8} className="py-6 text-center text-gray-400">
                  ไม่พบเครื่อง
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
