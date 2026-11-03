import Link from "next/link";
import { headers } from "next/headers";
import { prisma } from "@/lib/prisma";
import { requireUser } from "@/lib/session";
import { can } from "@/lib/permissions";
import { TRACKING_END, TRACKING_START } from "@/lib/config";
import { formatDateTime, timeAgo } from "@/lib/datetime";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import TrackerToken from "@/components/TrackerToken";
import AutoRefresh from "@/components/AutoRefresh";
import { createTrackerTokenAction, revokeTrackerTokenAction } from "./actions";

function originFromHeaders() {
  const h = headers();
  const host = h.get("x-forwarded-host") ?? h.get("host") ?? "localhost:3000";
  const proto = h.get("x-forwarded-proto") ?? (/^(localhost|127\.)/.test(host) ? "http" : "https");
  return `${proto}://${host}`;
}

/** ตั้งค่าแอปติดตามเบื้องหลัง — keeps sending location when the screen is off. */
export default async function TrackerSetupPage() {
  const user = await requireUser(can.doFieldWork);
  const lastBg = await prisma.locationPing.findFirst({
    where: { employeeId: user.id, source: "BACKGROUND_APP" },
    orderBy: { recordedAt: "desc" },
  });
  const hasToken = !!user.trackingTokenHash;

  return (
    <div className="space-y-4">
      <AutoRefresh everyMs={20_000} />
      <Link href="/m" className="text-sm text-gray-500">
        ← งานของฉัน
      </Link>
      <section className="card space-y-3 p-4">
        <h1 className="text-lg font-bold text-gray-900">ติดตามตำแหน่งแม้ปิดหน้าจอ</h1>
        <p className="text-sm text-gray-700">
          แอป AMN Service บนเว็บส่งตำแหน่งได้เฉพาะตอนเปิดหน้าจออยู่ ถ้าล็อกจอหรือสลับไปใช้ Google Maps นำทาง
          ให้ติดตั้งแอปติดตามฟรีเพิ่ม 1 ตัว แอปจะส่งตำแหน่งแบบ realtime เบื้องหลังเข้ามาที่ระบบนี้โดยตรง
        </p>
        <p className="rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
          ระบบรับตำแหน่ง <b>เฉพาะหลังจากกด “เริ่มงาน”</b> จนถึง “เลิกงาน” และอยู่ในช่วง {TRACKING_START}–{TRACKING_END} น.
          เท่านั้น นอกเวลางานระบบจะทิ้งข้อมูลทันทีโดยไม่บันทึก (เพื่อประหยัดแบต ปิดแอปติดตามหลังเลิกงานได้)
        </p>
        <div className="rounded-lg bg-gray-50 p-3 text-sm">
          <div className="font-semibold text-gray-900">สถานะการเชื่อมต่อ</div>
          {!hasToken ? (
            <div className="text-gray-600">ยังไม่ได้เชื่อมแอปติดตาม</div>
          ) : (
            <div className="text-gray-700">
              รหัสลงท้าย ••••{user.trackingTokenHint} · สร้างเมื่อ {formatDateTime(user.trackingTokenAt)}
              <br />
              {lastBg ? (
                <span className="text-emerald-700">✅ ได้รับตำแหน่งจากแอปติดตามล่าสุด {timeAgo(lastBg.recordedAt)}</span>
              ) : (
                <span className="text-orange-700">ยังไม่เคยได้รับตำแหน่งจากแอปติดตาม (ต้องกดเริ่มงานก่อน)</span>
              )}
            </div>
          )}
        </div>
        <TrackerToken
          action={createTrackerTokenAction}
          origin={originFromHeaders()}
          employeeCode={user.employeeCode}
          hasToken={hasToken}
        />
        {hasToken && (
          <ActionForm action={revokeTrackerTokenAction} confirm="ยกเลิกการเชื่อมต่อแอปติดตาม?">
            <SubmitButton className="btn btn-danger w-full text-xs">ยกเลิกการเชื่อมต่อแอปติดตาม</SubmitButton>
          </ActionForm>
        )}
      </section>
    </div>
  );
}
