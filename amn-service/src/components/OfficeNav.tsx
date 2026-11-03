"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

export type NavItem = { href: string; label: string; icon: string; badge?: number };
export type NavGroup = { title?: string; items: NavItem[] };

export default function OfficeNav({ groups, onNavigate }: { groups: NavGroup[]; onNavigate?: () => void }) {
  const pathname = usePathname();
  const isActive = (href: string) => pathname === href || (href !== "/dashboard" && pathname.startsWith(href + "/"));

  return (
    <nav className="py-3">
      {groups.map((g, gi) => (
        <div key={gi} className={gi > 0 ? "mt-3" : ""}>
          {g.title && (
            <div className="px-5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{g.title}</div>
          )}
          {g.items.map((item) => {
            const active = isActive(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={onNavigate}
                className={`mx-2 flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium ${
                  active ? "bg-brand-50 text-brand-800" : "text-gray-700 hover:bg-gray-50"
                }`}
              >
                <span aria-hidden className="w-5 text-center">
                  {item.icon}
                </span>
                <span className="flex-1">{item.label}</span>
                {!!item.badge && (
                  <span className="badge bg-amber-100 text-amber-800" title="งานรอดำเนินการ">
                    {item.badge}
                  </span>
                )}
              </Link>
            );
          })}
        </div>
      ))}
    </nav>
  );
}
