"use client";

export default function ErrorPage({ reset }: { error: Error; reset: () => void }) {
  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center">
      <div className="text-3xl">⚠️</div>
      <h1 className="mt-3 text-lg font-bold text-gray-900">เกิดข้อผิดพลาด</h1>
      <p className="mt-1 text-sm text-gray-500">ลองใหม่อีกครั้ง ถ้ายังไม่ได้ให้แจ้งแอดมิน</p>
      <div className="mt-5 flex justify-center gap-2">
        <button onClick={reset} className="btn btn-primary">
          ลองใหม่
        </button>
        <a href="/" className="btn btn-secondary">
          กลับหน้าหลัก
        </a>
      </div>
    </div>
  );
}
