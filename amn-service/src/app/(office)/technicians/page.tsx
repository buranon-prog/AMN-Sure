import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { OPEN_JOB_STATUS } from "@/lib/constants";
import { dateKey, formatTime, monthKey, monthRange } from "@/lib/datetime";
import { fullName } from "@/lib/people";
import PageHeader from "@/components/PageHeader";
import { RoleBadge, SkillDots } from "@/components/Badges";

export default async function TechniciansPage() {
  await requireUser(can.useOffice);
  const { start, end } = monthRange(monthKey());

  const [techs, models, assignments, shifts] = await Promise.all([
    prisma.employee.findMany({
      where: { active: true, role: { in: ["TECHNICIAN", "SUPERVISOR"] } },
      include: { skills: true },
      orderBy: [{ role: "asc" }, { firstName: "asc" }],
    }),
    prisma.machineModel.findMany({ where: { active: true }, orderBy: [{ category: "asc" }, { brand: "asc" }, { model: "asc" }] }),
    prisma.jobAssignment.findMany({
      where: {
        OR: [{ job: { status: { in: OPEN_JOB_STATUS } } }, { job: { status: "COMPLETED", completedAt: { gte: start, lt: end } } }],
      },
      select: { employeeId: true, job: { select: { status: true } } },
    }),
    prisma.shift.findMany({ where: { workDate: dateKey() }, orderBy: { startAt: "desc" } }),
  ]);

  const stat = (id: string) => {
    const mine = assignments.filter((a) => a.employeeId === id);
    return {
      open: mine.filter((a) => a.job.status !== "COMPLETED" && a.job.status !== "ON_HOLD").length,
      hold: mine.filter((a) => a.job.status === "ON_HOLD").length,
      done: mine.filter((a) => a.job.status === "COMPLETED").length,
    };
  };

  return (
    <div>
      <PageHeader title="ช่าง & ความถนัด" subtitle="ภาระงานปัจจุบันของช่างแต่ละคน และความถนัดต่อรุ่นเครื่อง (กดชื่อเพื่อดูประวัติการซ่อม / แก้ไขความถนัด)" />

      <div className="card overflow-x-auto">
        <table className="data-table">
          <thead>
            <tr>
              <th>ช่าง</th>
              <th>บทบาท</th>
              <th>วันนี้</th>
              <th className="num">งานรอทำ</th>
              <th className="num">Hold</th>
              <th className="num">เสร็จเดือนนี้</th>
            </tr>
          </thead>
          <tbody>
            {techs.map((t) => {
              const s = stat(t.id);
              const shift = shifts.find((x) => x.employeeId === t.id);
              return (
                <tr key={t.id}>
                  <td>
                    <Link href={`/technicians/${t.id}`} className="link">
                      {fullName(t)}
                    </Link>
                    <div className="text-xs text-gray-500">
                      {t.employeeCode}
                      {t.phone && ` · ${t.phone}`}
                    </div>
                  </td>
                  <td>
                    <RoleBadge role={t.role} />
                  </td>
                  <td className="whitespace-nowrap text-sm">
                    {!shift ? (
                      <span className="text-gray-400">ยังไม่เริ่มงาน</span>
                    ) : shift.endAt ? (
                      <span className="text-gray-500">
                        {formatTime(shift.startAt)}–{formatTime(shift.endAt)}
                      </span>
                    ) : (
                      <span className="text-emerald-700">● ทำงานอยู่ (เริ่ม {formatTime(shift.startAt)})</span>
                    )}
                  </td>
                  <td className="num">{s.open}</td>
                  <td className="num">{s.hold}</td>
                  <td className="num">{s.done}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <h2 className="section-title mt-8">ตารางความถนัด (1 = พอทำได้ … 5 = เชี่ยวชาญ)</h2>
      {models.length === 0 ? (
        <div className="card p-6 text-sm text-gray-500">ยังไม่มีรุ่นเครื่อง</div>
      ) : (
        <div className="card overflow-x-auto">
          <table className="data-table">
            <thead>
              <tr>
                <th className="sticky left-0 z-10">ช่าง</th>
                {models.map((m) => (
                  <th key={m.id} className="text-center">
                    <div className="text-[11px] font-normal text-gray-400">{m.category}</div>
                    {m.brand} {m.model}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {techs.map((t) => (
                <tr key={t.id}>
                  <td className="sticky left-0 z-10 whitespace-nowrap bg-white font-medium">
                    <Link href={`/technicians/${t.id}`} className="hover:underline">
                      {t.nickname || t.firstName}
                    </Link>
                  </td>
                  {models.map((m) => {
                    const lvl = t.skills.find((s) => s.machineModelId === m.id)?.level ?? 0;
                    return (
                      <td key={m.id} className="text-center">
                        {lvl ? <SkillDots level={lvl} /> : <span className="text-gray-300">–</span>}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
