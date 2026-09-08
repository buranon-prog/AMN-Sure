import Link from "next/link";
import { redirect } from "next/navigation";
import { auth } from "@/auth";
import { MODULES, MODULE_LABELS } from "@/lib/constants";
import { canView } from "@/lib/rbac";
import SignOutButton from "@/components/SignOutButton";

const MODULE_HREF: Record<(typeof MODULES)[number], string> = {
  INVENTORY: "/inventory",
  CONSIGNMENT: "/consignment",
  FINANCE: "/finance",
  HR: "/hr",
  SUPPLY_CHAIN: "/supply-chain",
  CRM: "/crm",
  STRATEGY: "/strategy",
};

const MODULE_ICON: Record<(typeof MODULES)[number], string> = {
  INVENTORY: "📦",
  CONSIGNMENT: "🤝",
  FINANCE: "💰",
  HR: "🧑‍💼",
  SUPPLY_CHAIN: "🚚",
  CRM: "📇",
  STRATEGY: "🧭",
};

export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const session = await auth();
  if (!session?.user) redirect("/login");

  const { role, permissions, name, email } = session.user;
  const visibleModules = MODULES.filter((m) => canView(role, permissions, m));

  return (
    <div className="min-h-screen flex">
      <aside className="w-64 shrink-0 bg-white border-r border-gray-200 flex flex-col">
        <div className="px-5 py-5 border-b border-gray-100">
          <div className="text-lg font-bold text-brand-700">AMN-Sure</div>
          <div className="text-xs text-gray-400">ERP ภายในองค์กร</div>
        </div>

        <nav className="flex-1 overflow-y-auto py-3">
          <Link
            href="/dashboard"
            className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700"
          >
            <span>🏠</span> หน้าหลัก
          </Link>

          <div className="mt-3 px-5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
            โมดูล
          </div>
          {visibleModules.map((m) => (
            <Link
              key={m}
              href={MODULE_HREF[m]}
              className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700"
            >
              <span>{MODULE_ICON[m]}</span> {MODULE_LABELS[m]}
            </Link>
          ))}

          <div className="mt-3 px-5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
            ข้อมูล
          </div>
          <Link
            href="/data-warehouse"
            className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700"
          >
            <span>🗄️</span> คลังข้อมูล (Export)
          </Link>

          {role === "MANAGER" && (
            <>
              <div className="mt-3 px-5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                ผู้ดูแลระบบ
              </div>
              <Link
                href="/admin/users"
                className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700"
              >
                <span>👤</span> จัดการผู้ใช้งาน
              </Link>
            </>
          )}
        </nav>

        <div className="px-5 py-4 border-t border-gray-100">
          <div className="text-sm font-semibold text-gray-800 truncate">{name}</div>
          <div className="text-xs text-gray-400 truncate mb-2">{email}</div>
          <div className="flex items-center justify-between">
            <span
              className={`badge ${
                role === "MANAGER" ? "bg-brand-100 text-brand-700" : "bg-gray-100 text-gray-600"
              }`}
            >
              {role === "MANAGER" ? "ผู้จัดการ" : "พนักงาน"}
            </span>
            <SignOutButton />
          </div>
        </div>
      </aside>

      <main className="flex-1 min-w-0">
        <div className="max-w-6xl mx-auto px-6 py-8">{children}</div>
      </main>
    </div>
  );
}
