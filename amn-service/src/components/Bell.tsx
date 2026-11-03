import Link from "next/link";

export default function Bell({ count, className = "" }: { count: number; className?: string }) {
  return (
    <Link href="/notifications" className={`relative inline-flex rounded-lg p-2 text-gray-700 hover:bg-gray-100 ${className}`} aria-label={`การแจ้งเตือน ${count} รายการที่ยังไม่อ่าน`}>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
      </svg>
      {count > 0 && (
        <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white">
          {count > 99 ? "99+" : count}
        </span>
      )}
    </Link>
  );
}
