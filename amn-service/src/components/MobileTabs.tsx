"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const TABS = [
  { href: "/m", label: "งานของฉัน", icon: "🗂️" },
  { href: "/m/history", label: "ประวัติ/สรุป", icon: "📈" },
  { href: "/account", label: "บัญชี", icon: "👤" },
];

export default function MobileTabs() {
  const pathname = usePathname();
  return (
    <nav className="pb-safe fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white">
      <div className="mx-auto flex max-w-lg">
        {TABS.map((t) => {
          const active = t.href === "/m" ? pathname === "/m" || pathname.startsWith("/m/jobs") : pathname.startsWith(t.href);
          return (
            <Link
              key={t.href}
              href={t.href}
              className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-xs font-medium ${
                active ? "text-brand-700" : "text-gray-500"
              }`}
            >
              <span className="text-lg" aria-hidden>
                {t.icon}
              </span>
              {t.label}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
