import Link from "next/link";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { shortName } from "@/lib/people";
import MobileTabs from "@/components/MobileTabs";
import Bell from "@/components/Bell";
import { unreadCount } from "@/lib/notify";

export default async function MobileLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser(can.doFieldWork);
  const unread = await unreadCount(user.id);

  return (
    <div className="mx-auto flex min-h-screen max-w-lg flex-col bg-gray-50">
      <header className="sticky top-0 z-30 border-b border-gray-200 bg-white">
        <div className="flex items-center justify-between px-4 py-3">
          <div>
            <div className="text-xs text-gray-400">AMN Service</div>
            <div className="font-bold text-gray-900">สวัสดี {shortName(user)}</div>
          </div>
          <div className="flex items-center gap-1">
            {can.useOffice(user.role) && (
              <Link href="/dashboard" className="btn btn-secondary px-3 py-1.5 text-xs">
                หน้าออฟฟิศ
              </Link>
            )}
            <Bell count={unread} />
          </div>
        </div>
      </header>
      <main className="flex-1 px-4 pb-24 pt-4">{children}</main>
      <MobileTabs />
    </div>
  );
}
