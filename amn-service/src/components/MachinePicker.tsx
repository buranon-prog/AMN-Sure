"use client";

import { useMemo, useState } from "react";

export type PickerMachine = {
  id: string;
  code: string;
  model: string;
  serial: string | null;
  customer: string;
  site: string;
  address: string | null;
  location: string | null;
  contact: string | null;
};

/** Searchable machine selector for "ลงงาน" — search by customer, code, model or serial. */
export default function MachinePicker({ machines, defaultId }: { machines: PickerMachine[]; defaultId?: string }) {
  const [q, setQ] = useState("");
  const [selected, setSelected] = useState(defaultId ?? "");

  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (!needle) return machines;
    return machines.filter((m) =>
      [m.code, m.model, m.serial, m.customer, m.site].some((v) => v?.toLowerCase().includes(needle))
    );
  }, [q, machines]);

  const groups = useMemo(() => {
    const map = new Map<string, PickerMachine[]>();
    for (const m of filtered) map.set(m.customer, [...(map.get(m.customer) ?? []), m]);
    return [...map.entries()];
  }, [filtered]);

  const current = machines.find((m) => m.id === selected);

  return (
    <div className="space-y-2">
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="🔎 ค้นหาลูกค้า / รหัสเครื่อง / รุ่น / S/N"
        className="input"
        aria-label="ค้นหาเครื่อง"
      />
      <select
        name="machineId"
        required
        value={selected}
        onChange={(e) => setSelected(e.target.value)}
        className="input"
        size={Math.min(8, Math.max(4, filtered.length + groups.length))}
      >
        {groups.map(([customer, list]) => (
          <optgroup key={customer} label={customer}>
            {list.map((m) => (
              <option key={m.id} value={m.id}>
                {m.code} — {m.model} · {m.site}
              </option>
            ))}
          </optgroup>
        ))}
      </select>
      {filtered.length === 0 && <p className="text-xs text-gray-500">ไม่พบเครื่องที่ค้นหา</p>}
      {current && (
        <div className="rounded-lg bg-brand-50 p-3 text-sm text-gray-700">
          <div className="font-semibold text-gray-900">
            {current.customer} — {current.site}
          </div>
          <div>
            {current.model} · รหัส {current.code}
            {current.serial ? ` · S/N ${current.serial}` : ""}
          </div>
          {current.location && <div>ตำแหน่งเครื่อง: {current.location}</div>}
          {current.address && <div className="text-gray-500">{current.address}</div>}
          {current.contact && <div className="text-gray-500">ผู้ติดต่อหน้างาน: {current.contact}</div>}
        </div>
      )}
    </div>
  );
}
