import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { prisma } from "@/lib/prisma";
import { fullName } from "@/lib/people";
import OfficeNav, { type NavGroup } from "@/components/OfficeNav";
import MobileMenu from "@/components/MobileMenu";
import SignOutButton from "@/components/SignOutButton";
import { RoleBadge } from "@/components/Badges";
import Bell from "@/components/Bell";
import { unreadCount } from "@/lib/notify";

export default async function OfficeLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser(can.useOffice);
  const role = user.role;

  const [unassigned, unread] = await Promise.all([
    can.assignJobs(role) ? prisma.job.count({ where: { status: "NEW" } }) : 0,
    unreadCount(user.id),
  ]);

  const groups: NavGroup[] = [
    {
      items: [
        { href: "/dashboard", label: "ภาพรวม (Dashboard)", icon: "📊" },
        { href: "/jobs", label: "งานบริการ", icon: "🗂️", badge: unassigned },
        ...(can.manageJobs(role) ? [{ href: "/jobs/new", label: "ลงงานใหม่", icon: "➕" }] : []),
      ],
    },
    {
      title: "ข้อมูลลูกค้า",
      items: [
        { href: "/customers", label: "ลูกค้า", icon: "🏥" },
        { href: "/machines", label: "เครื่องลูกค้า", icon: "🩺" },
        { href: "/models", label: "รุ่นเครื่อง", icon: "📚" },
      ],
    },
    {
      title: "ทีมช่าง",
      items: [
        { href: "/technicians", label: "ช่าง & ความถนัด", icon: "🧑‍🔧" },
        ...(can.viewTracking(role) ? [{ href: "/tracking", label: "ติดตามตำแหน่ง", icon: "📍" }] : []),
      ],
    },
    {
      title: "ระบบ",
      items: [
        ...(can.manageEmployees(role) ? [{ href: "/employees", label: "พนักงาน & บัญชี", icon: "👤" }] : []),
        ...(can.exportData(role) ? [{ href: "/export", label: "Export ข้อมูล", icon: "🗄️" }] : []),
        ...(can.doFieldWork(role) ? [{ href: "/m", label: "งานของฉัน (มือถือ)", icon: "📱" }] : []),
        { href: "/account", label: "เปลี่ยนรหัสผ่าน", icon: "🔑" },
      ],
    },
  ];

  const footer = (
    <>
      <div className="truncate text-sm font-semibold text-gray-800">{fullName(user)}</div>
      <div className="mb-2 truncate text-xs text-gray-400">{user.employeeCode}</div>
      <div className="flex items-center justify-between">
        <RoleBadge role={role} />
        <SignOutButton />
      </div>
    </>
  );

  return (
    <div className="flex min-h-screen">
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-gray-200 bg-white lg:flex">
        <div className="flex items-center justify-between border-b border-gray-100 py-4 pl-5 pr-3">
          <div>
            <div className="text-lg font-bold text-brand-700">AMN Service</div>
            <div className="text-xs text-gray-400">ระบบงานบริการ</div>
          </div>
          <Bell count={unread} />
        </div>
        <div className="flex-1 overflow-y-auto">
          <OfficeNav groups={groups} />
        </div>
        <div className="border-t border-gray-100 px-5 py-4">{footer}</div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-40 flex items-center gap-2 border-b border-gray-200 bg-white px-3 py-2 lg:hidden">
          <MobileMenu groups={groups} footer={footer} />
          <span className="flex-1 font-bold text-brand-700">AMN Service</span>
          <Bell count={unread} />
        </header>
        <main className="min-w-0 flex-1">
          <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-8">{children}</div>
        </main>
      </div>
    </div>
  );
}
