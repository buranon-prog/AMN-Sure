import webpush from "web-push";
import { prisma } from "@/lib/prisma";

const PUBLIC_KEY = process.env.WEB_PUSH_PUBLIC_KEY ?? "";
const PRIVATE_KEY = process.env.WEB_PUSH_PRIVATE_KEY ?? "";
const SUBJECT = process.env.WEB_PUSH_SUBJECT || "mailto:admin@example.com";

export const pushEnabled = Boolean(PUBLIC_KEY && PRIVATE_KEY);
export const pushPublicKey = pushEnabled ? PUBLIC_KEY : null;

if (pushEnabled) webpush.setVapidDetails(SUBJECT, PUBLIC_KEY, PRIVATE_KEY);

export type NotifyInput = {
  type: string;
  title: string;
  body: string;
  url: string;
  jobId?: string;
};

/**
 * Stores an in-app notification for each recipient and pushes it to their
 * phones. Call AFTER the database transaction has committed. Never throws:
 * a failed push must not fail the action that triggered it.
 */
export async function notify(employeeIds: (string | null | undefined)[], n: NotifyInput, exceptId?: string) {
  const ids = [...new Set(employeeIds.filter((id): id is string => !!id && id !== exceptId))];
  if (ids.length === 0) return;
  try {
    await prisma.notification.createMany({
      data: ids.map((employeeId) => ({ employeeId, ...n, jobId: n.jobId ?? null })),
    });
    if (!pushEnabled) return;

    const subs = await prisma.pushSubscription.findMany({ where: { employeeId: { in: ids } } });
    const payload = JSON.stringify({ title: n.title, body: n.body, url: n.url, tag: n.jobId ?? n.type });
    const results = await Promise.allSettled(
      subs.map((s) =>
        webpush.sendNotification({ endpoint: s.endpoint, keys: { p256dh: s.p256dh, auth: s.auth } }, payload, {
          TTL: 24 * 60 * 60,
          urgency: "high",
          timeout: 10_000,
        })
      )
    );
    // Phones that uninstalled / revoked permission answer 404 or 410: forget them.
    const gone = subs.filter((s, i) => {
      const r = results[i];
      const code = r.status === "rejected" ? (r.reason as { statusCode?: number })?.statusCode : undefined;
      return code === 404 || code === 410;
    });
    if (gone.length) await prisma.pushSubscription.deleteMany({ where: { id: { in: gone.map((s) => s.id) } } });
  } catch (e) {
    console.error("notify failed", e);
  }
}

/** Active supervisors — they receive "new job waiting to be assigned". */
export async function supervisorIds() {
  const rows = await prisma.employee.findMany({ where: { active: true, role: "SUPERVISOR" }, select: { id: true } });
  return rows.map((r) => r.id);
}

export async function unreadCount(employeeId: string) {
  return prisma.notification.count({ where: { employeeId, readAt: null } });
}
