"use client";

import { useState } from "react";
import ActionForm from "@/components/ActionForm";
import SubmitButton from "@/components/SubmitButton";
import type { ActionState } from "@/lib/types";

/** สรุปงานหลังเข้าหน้างาน: ยังไม่เสร็จ (Hold) หรือ เสร็จแล้ว (ส่งงาน). */
export default function ReportForm({
  action,
}: {
  action: (state: ActionState, formData: FormData) => Promise<ActionState>;
}) {
  const [outcome, setOutcome] = useState<"HOLD" | "COMPLETED" | "">("");

  return (
    <ActionForm action={action} captureLocation className="space-y-4">
      <div>
        <label className="label" htmlFor="summary">
          สรุปงานที่ทำ *
        </label>
        <textarea
          id="summary"
          name="summary"
          required
          minLength={5}
          rows={5}
          className="input text-base"
          placeholder="ตรวจพบอะไร แก้ไขอะไรไปแล้วบ้าง ผลการทดสอบหลังซ่อม"
        />
      </div>
      <div>
        <label className="label" htmlFor="partsUsed">
          อะไหล่ / วัสดุที่ใช้
        </label>
        <input id="partsUsed" name="partsUsed" className="input text-base" placeholder="เช่น ฟิวส์ 2A x2, สายโพรบ" />
      </div>

      <fieldset>
        <legend className="label">ผลการทำงาน *</legend>
        <div className="grid grid-cols-2 gap-2">
          <label
            className={`flex cursor-pointer flex-col items-center rounded-xl border-2 p-3 text-center ${
              outcome === "HOLD" ? "border-orange-500 bg-orange-50" : "border-gray-200 bg-white"
            }`}
          >
            <input type="radio" name="outcome" value="HOLD" required className="sr-only" onChange={() => setOutcome("HOLD")} />
            <span className="text-xl">⏸️</span>
            <span className="font-semibold text-gray-900">ยังไม่เสร็จ</span>
            <span className="text-xs text-gray-500">Hold ไว้ เข้าใหม่ครั้งหน้า</span>
          </label>
          <label
            className={`flex cursor-pointer flex-col items-center rounded-xl border-2 p-3 text-center ${
              outcome === "COMPLETED" ? "border-emerald-600 bg-emerald-50" : "border-gray-200 bg-white"
            }`}
          >
            <input type="radio" name="outcome" value="COMPLETED" required className="sr-only" onChange={() => setOutcome("COMPLETED")} />
            <span className="text-xl">✅</span>
            <span className="font-semibold text-gray-900">เสร็จแล้ว</span>
            <span className="text-xs text-gray-500">ส่งงาน ปิดงานนี้</span>
          </label>
        </div>
      </fieldset>

      {outcome === "HOLD" && (
        <div className="space-y-3 rounded-xl bg-orange-50 p-3">
          <div>
            <label className="label" htmlFor="holdReason">
              เหตุที่ยังไม่เสร็จ *
            </label>
            <input
              id="holdReason"
              name="holdReason"
              required
              className="input text-base"
              placeholder="เช่น รออะไหล่จากต่างประเทศ, ลูกค้าไม่สะดวก"
            />
          </div>
          <div>
            <label className="label" htmlFor="nextVisitAt">
              นัดเข้าครั้งถัดไป (ถ้าทราบ)
            </label>
            <input id="nextVisitAt" name="nextVisitAt" type="datetime-local" className="input text-base" />
          </div>
        </div>
      )}

      <SubmitButton
        className={`btn btn-lg w-full ${outcome === "HOLD" ? "btn-warn" : "btn-primary"}`}
        pendingText="กำลังส่ง…"
      >
        {outcome === "HOLD" ? "บันทึก Hold" : outcome === "COMPLETED" ? "ส่งงาน" : "บันทึกสรุปงาน"}
      </SubmitButton>
    </ActionForm>
  );
}
