import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { auth } from "@/auth";
import { can } from "@/lib/permissions";
import { dateKey } from "@/lib/datetime";
import { fieldStatus, toDTO } from "@/lib/tracking";

// Live feed for the office map (Server-Sent Events). New location points are
// pushed within ~2 s of reaching the server; shift / on-site status every 20 s.
// Works across several server instances because it reads from the database.

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

const POLL_MS = 2000;
const STATUS_EVERY = 10; // polls → 20 s
const MAX_LIFETIME_MS = 30 * 60 * 1000; // EventSource reconnects by itself

async function allowed(employeeId: string) {
  const emp = await prisma.employee.findUnique({ where: { id: employeeId }, select: { active: true, role: true } });
  return !!emp?.active && can.viewTracking(emp.role);
}

export async function GET(req: NextRequest) {
  const session = await auth();
  const uid = session?.user?.id;
  if (!uid || !(await allowed(uid))) return new Response("Forbidden", { status: 403 });

  // EventSource resends the last event id after a reconnect: resume from there.
  const sinceParam = req.headers.get("last-event-id") || req.nextUrl.searchParams.get("since");
  const floor = Date.now() - 10 * 60 * 1000;
  let since = new Date(Math.max(floor, sinceParam ? Date.parse(sinceParam) || Date.now() : Date.now()));
  const started = Date.now();
  const encoder = new TextEncoder();
  let timer: ReturnType<typeof setTimeout> | null = null;
  let closed = false;

  const stream = new ReadableStream({
    start(controller) {
      const write = (chunk: string) => {
        if (closed) return;
        try {
          controller.enqueue(encoder.encode(chunk));
        } catch {
          closed = true;
        }
      };
      const send = (event: string, data: unknown) => write(`event: ${event}\ndata: ${JSON.stringify(data)}\n\n`);
      const stop = () => {
        if (closed) return;
        closed = true;
        if (timer) clearTimeout(timer);
        try {
          controller.close();
        } catch {
          /* already closed */
        }
      };
      req.signal.addEventListener("abort", stop);

      write("retry: 3000\n\n");
      send("hello", { at: new Date().toISOString() });

      let tick = 0;
      const loop = async () => {
        if (closed) return;
        try {
          if (Date.now() - started > MAX_LIFETIME_MS) return stop();
          if (tick > 0 && tick % 30 === 0 && !(await allowed(uid))) return stop(); // re-check access every minute

          const pings = await prisma.locationPing.findMany({
            where: { receivedAt: { gt: since } },
            orderBy: { receivedAt: "asc" },
            include: { job: { select: { jobNo: true } } },
            take: 500,
          });
          if (pings.length) {
            since = pings[pings.length - 1].receivedAt;
            for (const p of pings) write(`id: ${p.receivedAt.toISOString()}\nevent: ping\ndata: ${JSON.stringify(toDTO(p))}\n\n`);
          }
          if (tick % STATUS_EVERY === 0) send("status", await fieldStatus(dateKey(), true));
          else if (!pings.length && tick % 5 === 0) write(": keep-alive\n\n");
        } catch (e) {
          console.error("tracking stream", e);
        }
        tick++;
        if (!closed) timer = setTimeout(loop, POLL_MS);
      };
      void loop();
    },
    cancel() {
      closed = true;
      if (timer) clearTimeout(timer);
    },
  });

  return new Response(stream, {
    headers: {
      "Content-Type": "text/event-stream; charset=utf-8",
      "Cache-Control": "no-cache, no-transform",
      Connection: "keep-alive",
      "X-Accel-Buffering": "no", // nginx: don't buffer the stream
    },
  });
}
