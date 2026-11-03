import {
  JOB_STATUS_COLOR,
  JOB_STATUS_LABEL,
  JOB_TYPE_LABEL,
  PRIORITY_COLOR,
  PRIORITY_LABEL,
  ROLE_LABEL,
  SKILL_LABEL,
  type JobStatus,
  type Role,
} from "@/lib/constants";

export function StatusBadge({ status }: { status: string }) {
  const s = status as JobStatus;
  return <span className={`badge ${JOB_STATUS_COLOR[s] ?? "bg-gray-100 text-gray-600"}`}>{JOB_STATUS_LABEL[s] ?? status}</span>;
}

export function PriorityBadge({ priority }: { priority: string }) {
  const p = priority as keyof typeof PRIORITY_LABEL;
  if (p === "NORMAL") return null;
  return <span className={`badge ${PRIORITY_COLOR[p] ?? ""}`}>{PRIORITY_LABEL[p] ?? priority}</span>;
}

export function TypeLabel({ type }: { type: string }) {
  return <>{JOB_TYPE_LABEL[type as keyof typeof JOB_TYPE_LABEL] ?? type}</>;
}

export function RoleBadge({ role }: { role: string }) {
  const color: Record<string, string> = {
    MANAGER: "bg-violet-100 text-violet-800",
    SUPERVISOR: "bg-brand-100 text-brand-800",
    ADMIN: "bg-sky-100 text-sky-800",
    TECHNICIAN: "bg-gray-100 text-gray-700",
  };
  return <span className={`badge ${color[role] ?? ""}`}>{ROLE_LABEL[role as Role] ?? role}</span>;
}

/** ●●●○○ with an accessible label. */
export function SkillDots({ level, size = "sm" }: { level: number; size?: "sm" | "md" }) {
  const dot = size === "md" ? "h-2.5 w-2.5" : "h-2 w-2";
  return (
    <span className="inline-flex items-center gap-0.5" title={level ? `ระดับ ${level}: ${SKILL_LABEL[level]}` : "ไม่มีข้อมูลความถนัด"}>
      {[1, 2, 3, 4, 5].map((i) => (
        <span key={i} className={`${dot} rounded-full ${i <= level ? "bg-brand-600" : "bg-gray-200"}`} />
      ))}
      <span className="sr-only">{level ? `ระดับ ${level}` : "ไม่มี"}</span>
    </span>
  );
}
