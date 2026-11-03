"use client";

import { useState } from "react";
import { useFormState } from "react-dom";
import SubmitButton from "@/components/SubmitButton";
import type { TokenState } from "@/app/m/tracker/actions";

function Copy({ value, label }: { value: string; label: string }) {
  const [done, setDone] = useState(false);
  return (
    <div className="space-y-1">
      <div className="text-xs font-semibold text-gray-600">{label}</div>
      <div className="flex gap-2">
        <input readOnly value={value} className="input font-mono text-xs" onFocus={(e) => e.currentTarget.select()} />
        <button
          type="button"
          className="btn btn-secondary shrink-0 px-3 text-xs"
          onClick={() =>
            navigator.clipboard?.writeText(value).then(
              () => {
                setDone(true);
                setTimeout(() => setDone(false), 1500);
              },
              () => {}
            )
          }
        >
          {done ? "คัดลอกแล้ว" : "คัดลอก"}
        </button>
      </div>
    </div>
  );
}

/** Generates the personal token and shows the exact values to paste into the tracking app. */
export default function TrackerToken({
  action,
  origin,
  employeeCode,
  hasToken,
}: {
  action: (state: TokenState) => Promise<TokenState>;
  origin: string;
  employeeCode: string;
  hasToken: boolean;
}) {
  const [state, formAction] = useFormState(action, {} as TokenState);
  const token = state.token;

  return (
    <div className="space-y-4">
      <form action={formAction}>
        <SubmitButton className={`btn w-full ${hasToken ? "btn-secondary" : "btn-primary btn-lg"}`} pendingText="กำลังสร้าง…">
          {hasToken ? "🔄 สร้างรหัสเชื่อมต่อใหม่ (รหัสเดิมจะใช้ไม่ได้)" : "🔑 สร้างรหัสเชื่อมต่อแอปติดตาม"}
        </SubmitButton>
        {state.error && <p className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{state.error}</p>}
      </form>

      {token && (
        <div className="space-y-4 rounded-xl border-2 border-brand-300 bg-brand-50 p-4">
          <p className="text-sm font-semibold text-brand-900">
            คัดลอกค่าด้านล่างไปใส่ในแอปติดตามตอนนี้เลย — รหัสนี้จะแสดงครั้งเดียว
          </p>

          <div className="space-y-3">
            <div className="font-semibold text-gray-900">แบบที่ 1: OwnTracks (แนะนำ · iPhone และ Android · ฟรี)</div>
            <ol className="list-decimal space-y-1 pl-5 text-sm text-gray-700">
              <li>ติดตั้งแอป “OwnTracks” จาก App Store / Play Store</li>
              <li>อนุญาตตำแหน่งแบบ “ตลอดเวลา (Always)” และปิดโหมดประหยัดแบตสำหรับแอปนี้</li>
              <li>
                ตั้งค่า → Connection → Mode เลือก <b>HTTP</b>
              </li>
              <li>ช่อง URL / Host ใส่ค่านี้:</li>
            </ol>
            <Copy label="URL" value={`${origin}/api/track?token=${token}`} />
            <ol start={5} className="list-decimal space-y-1 pl-5 text-sm text-gray-700">
              <li>
                Identification → Username ใส่ <b>{employeeCode}</b> (รหัสผ่านเว้นว่างได้)
              </li>
              <li>
                หน้าหลักของแอป เลือกโหมด <b>Move</b> เพื่อส่งตำแหน่งถี่ระหว่างเดินทาง
              </li>
            </ol>
          </div>

          <div className="space-y-3 border-t border-brand-200 pt-3">
            <div className="font-semibold text-gray-900">แบบที่ 2: Traccar Client (Android / iPhone · ฟรี)</div>
            <Copy label="Server URL" value={`${origin}/api/track`} />
            <Copy label="Device identifier" value={token} />
            <p className="text-sm text-gray-700">ตั้ง Frequency 10–15 วินาที, Accuracy: High แล้วเปิด Service status</p>
          </div>
        </div>
      )}
    </div>
  );
}
