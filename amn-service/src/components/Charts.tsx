// Minimal, dependency-free chart pieces for the dashboard.
// Colours: categorical slots 1–3 of the validated default palette, in fixed order.

export const SERIES = {
  done: "#2a78d6", // slot 1 — เสร็จ
  hold: "#eb6834", // slot 2 — Hold
  open: "#1baf7a", // slot 3 — รอทำ / กำลังทำ
};

export function StatTile({
  label,
  value,
  hint,
  tone,
  href,
}: {
  label: string;
  value: React.ReactNode;
  hint?: React.ReactNode;
  tone?: "warn";
  href?: string;
}) {
  const body = (
    <>
      <div className="text-xs font-semibold text-gray-500">{label}</div>
      <div className={`mt-1 text-2xl font-bold tabular-nums ${tone === "warn" ? "text-amber-700" : "text-gray-900"}`}>{value}</div>
      {hint && <div className="mt-0.5 text-xs text-gray-500">{hint}</div>}
    </>
  );
  return href ? (
    <a href={href} className="card block p-4 transition-colors hover:border-brand-300">
      {body}
    </a>
  ) : (
    <div className="card p-4">{body}</div>
  );
}

/** Single-series horizontal bars with the value printed at the end. */
export function HBars({ rows, unit = "" }: { rows: { label: string; value: number }[]; unit?: string }) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  if (rows.length === 0) return <p className="text-sm text-gray-400">ไม่มีข้อมูล</p>;
  return (
    <ul className="space-y-2.5">
      {rows.map((r) => (
        <li key={r.label} className="grid grid-cols-[minmax(0,13rem)_1fr_2.5rem] items-center gap-3 text-sm" title={`${r.label}: ${r.value}${unit}`}>
          <span className="truncate text-gray-700">{r.label}</span>
          <span className="h-3 rounded-r bg-gray-100">
            <span className="block h-3 rounded-r" style={{ width: `${(r.value / max) * 100}%`, background: SERIES.done, minWidth: r.value ? 4 : 0 }} />
          </span>
          <span className="text-right tabular-nums text-gray-900">{r.value}</span>
        </li>
      ))}
    </ul>
  );
}

/** One row of a stacked bar (segments separated by a 2px surface gap). */
export function StackedBar({
  parts,
  max,
}: {
  parts: { key: keyof typeof SERIES; label: string; value: number }[];
  max: number;
}) {
  const total = parts.reduce((n, p) => n + p.value, 0);
  return (
    <div className="flex h-3 w-full gap-[2px]" role="img" aria-label={parts.map((p) => `${p.label} ${p.value}`).join(", ")}>
      {parts
        .filter((p) => p.value > 0)
        .map((p, i, arr) => (
          <span
            key={p.key}
            title={`${p.label}: ${p.value}`}
            className={`${i === 0 ? "rounded-l" : ""} ${i === arr.length - 1 ? "rounded-r" : ""}`}
            style={{ width: `${(p.value / Math.max(1, max)) * 100}%`, background: SERIES[p.key] }}
          />
        ))}
      {total === 0 && <span className="text-xs text-gray-300">—</span>}
    </div>
  );
}

export function Legend({ items }: { items: { key: keyof typeof SERIES; label: string }[] }) {
  return (
    <div className="flex flex-wrap gap-4 text-xs text-gray-600">
      {items.map((i) => (
        <span key={i.key} className="inline-flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-sm" style={{ background: SERIES[i.key] }} />
          {i.label}
        </span>
      ))}
    </div>
  );
}
