import Link from "next/link";

const TABS = [
  { href: "/hr", label: "พนักงาน" },
  { href: "/hr/attendance", label: "การลงเวลาทำงาน" },
  { href: "/hr/kpi", label: "KPI" },
  { href: "/hr/planning", label: "แผนงานพนักงาน" },
  { href: "/hr/reports", label: "รายงาน" },
  { href: "/hr/costs", label: "ค่าใช้จ่ายดำเนินงาน" },
];

export default function HrTabs({ active }: { active: string }) {
  return (
    <div className="flex flex-wrap gap-1 border-b border-gray-200 mt-4 mb-6">
      {TABS.map((t) => (
        <Link
          key={t.href}
          href={t.href}
          className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px ${
            active === t.href
              ? "border-brand-600 text-brand-700"
              : "border-transparent text-gray-500 hover:text-gray-800"
          }`}
        >
          {t.label}
        </Link>
      ))}
    </div>
  );
}
