import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { homeFor } from "@/lib/permissions";
import { formatDateTime, timeAgo } from "@/lib/datetime";
import { pushPublicKey } from "@/lib/notify";
import PushToggle from "@/components/PushToggle";

const ICON: Record<string, string> = {
  JOB_ASSIGNED: "🆕",
  JOB_UNASSIGNED: "↩️",
  JOB_RESCHEDULED: "🕘",
  JOB_CANCELLED: "✖️",
  JOB_NEW: "📥",
  JOB_HOLD: "⏸️",
  JOB_COMPLETED: "✅",
};

export default async function NotificationsPage() {
  const user = await requireUser();
  const items = await prisma.notification.findMany({
    where: { employeeId: user.id },
    orderBy: { createdAt: "desc" },
    take: 100,
  });
  // Opening the list marks everything as read.
  await prisma.notification.updateMany({ where: { employeeId: user.id, readAt: null }, data: { readAt: new Date() } });

  return (
    <div className="mx-auto min-h-screen max-w-lg px-4 py-6">
      <Link href={homeFor(user.role)} className="text-sm text-gray-500">
        ← กลับ
      </Link>
      <h1 className="mb-3 mt-2 text-xl font-bold text-gray-900">การแจ้งเตือน</h1>
      <div className="mb-4">
        <PushToggle publicKey={pushPublicKey} />
      </div>
      {items.length === 0 ? (
        <div className="card p-8 text-center text-sm text-gray-400">ยังไม่มีการแจ้งเตือน</div>
      ) : (
        <ul className="space-y-2">
          {items.map((n) => (
            <li key={n.id}>
              <Link href={n.url} className={`card flex gap-3 p-3 ${n.readAt ? "" : "border-brand-300 bg-brand-50"}`}>
                <span className="text-xl" aria-hidden>
                  {ICON[n.type] ?? "🔔"}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold text-gray-900">{n.title}</span>
                  <span className="block text-sm text-gray-700">{n.body}</span>
                  <span className="block text-xs text-gray-400" title={formatDateTime(n.createdAt)}>
                    {timeAgo(n.createdAt)}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
