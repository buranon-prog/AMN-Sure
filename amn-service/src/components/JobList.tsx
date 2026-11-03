import Link from "next/link";
import type { JobListItem } from "@/lib/jobs";
import { formatDateTime } from "@/lib/datetime";
import { shortName } from "@/lib/people";
import { PriorityBadge, StatusBadge, TypeLabel } from "@/components/Badges";

function Techs({ job }: { job: JobListItem }) {
  if (job.assignments.length === 0) return <span className="text-amber-700">ยังไม่จ่ายงาน</span>;
  return (
    <span>
      {job.assignments.map((a, i) => (
        <span key={a.id}>
          {i > 0 && ", "}
          {a.isLead && <span title="หัวหน้างาน">★</span>}
          {shortName(a.employee)}
        </span>
      ))}
    </span>
  );
}

/** Job list: cards on phones, a table from md up. */
export default function JobList({
  jobs,
  hrefBase = "/jobs",
  empty = "ไม่มีงาน",
  showTechs = true,
  variant = "auto",
}: {
  jobs: JobListItem[];
  hrefBase?: string;
  empty?: string;
  showTechs?: boolean;
  /** "cards" forces the phone layout at every width (used by the /m screens). */
  variant?: "auto" | "cards";
}) {
  if (jobs.length === 0) {
    return <div className="card p-8 text-center text-sm text-gray-400">{empty}</div>;
  }
  return (
    <>
      <ul className={`space-y-2 ${variant === "auto" ? "md:hidden" : ""}`}>
        {jobs.map((j) => (
          <li key={j.id}>
            <Link href={`${hrefBase}/${j.id}`} className="card block p-4 active:bg-gray-50">
              <div className="flex items-center justify-between gap-2">
                <span className="text-xs font-semibold text-gray-500">{j.jobNo}</span>
                <span className="flex gap-1">
                  <PriorityBadge priority={j.priority} />
                  <StatusBadge status={j.status} />
                </span>
              </div>
              <div className="mt-1 font-semibold text-gray-900">{j.machine.site.customer.name}</div>
              <div className="text-sm text-gray-600">
                {j.machine.machineModel.brand} {j.machine.machineModel.model} · {j.machine.machineCode}
              </div>
              <div className="mt-1 line-clamp-2 text-sm text-gray-500">{j.problem}</div>
              <div className="mt-2 flex flex-wrap justify-between gap-2 text-xs text-gray-500">
                <span>🕘 {formatDateTime(j.scheduledAt)}</span>
                {showTechs && (
                  <span>
                    🧑‍🔧 <Techs job={j} />
                  </span>
                )}
              </div>
            </Link>
          </li>
        ))}
      </ul>

      {variant === "auto" && (
      <div className="card hidden overflow-x-auto md:block">
        <table className="data-table">
          <thead>
            <tr>
              <th>เลขที่งาน</th>
              <th>นัดเข้า</th>
              <th>ลูกค้า / สถานที่</th>
              <th>เครื่อง</th>
              <th>งาน</th>
              {showTechs && <th>ช่าง</th>}
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody>
            {jobs.map((j) => (
              <tr key={j.id}>
                <td className="whitespace-nowrap">
                  <Link href={`${hrefBase}/${j.id}`} className="link">
                    {j.jobNo}
                  </Link>
                </td>
                <td className="whitespace-nowrap">{formatDateTime(j.scheduledAt)}</td>
                <td>
                  <div className="font-medium text-gray-900">{j.machine.site.customer.name}</div>
                  <div className="text-xs text-gray-500">
                    {j.machine.site.name}
                    {j.machine.site.province ? ` · ${j.machine.site.province}` : ""}
                  </div>
                </td>
                <td>
                  <div>
                    {j.machine.machineModel.brand} {j.machine.machineModel.model}
                  </div>
                  <div className="text-xs text-gray-500">{j.machine.machineCode}</div>
                </td>
                <td className="max-w-xs">
                  <div className="text-xs font-semibold text-gray-500">
                    <TypeLabel type={j.type} />
                  </div>
                  <div className="line-clamp-2">{j.problem}</div>
                </td>
                {showTechs && (
                  <td className="whitespace-nowrap">
                    <Techs job={j} />
                  </td>
                )}
                <td>
                  <div className="flex flex-col items-start gap-1">
                    <StatusBadge status={j.status} />
                    <PriorityBadge priority={j.priority} />
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      )}
    </>
  );
}
