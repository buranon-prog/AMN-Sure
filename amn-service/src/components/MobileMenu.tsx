"use client";

import { useState } from "react";
import OfficeNav, { type NavGroup } from "./OfficeNav";

export default function MobileMenu({ groups, footer }: { groups: NavGroup[]; footer: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="rounded-lg p-2 text-gray-700 hover:bg-gray-100"
        aria-label="เปิดเมนู"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-black/30" onClick={() => setOpen(false)} />
          <div className="absolute inset-y-0 left-0 flex w-72 max-w-[85%] flex-col bg-white shadow-xl">
            <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
              <span className="font-bold text-brand-700">AMN Service</span>
              <button type="button" onClick={() => setOpen(false)} className="p-1 text-gray-500" aria-label="ปิดเมนู">
                ✕
              </button>
            </div>
            <div className="flex-1 overflow-y-auto">
              <OfficeNav groups={groups} onNavigate={() => setOpen(false)} />
            </div>
            <div className="border-t border-gray-100 px-5 py-4">{footer}</div>
          </div>
        </div>
      )}
    </>
  );
}
