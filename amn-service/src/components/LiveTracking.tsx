"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import type * as Leaflet from "leaflet";
import "leaflet/dist/leaflet.css";
import type { FieldStatus, PingDTO } from "@/lib/tracking";

export type LivePerson = Omit<FieldStatus, "id"> & {
  id: string;
  name: string;
  short: string;
  phone: string | null;
  last: PingDTO | null;
  count: number;
};

type Props = {
  date: string;
  isToday: boolean;
  /** Server render time — the stream continues from here. */
  since: string;
  people: LivePerson[];
  initialSelected: string | null;
  initialTrail: PingDTO[];
  liveWindowMs: number;
  sourceLabels: Record<string, string>;
};

const TZ = "Asia/Bangkok";
const fmtTime = new Intl.DateTimeFormat("th-TH", { timeZone: TZ, hour: "2-digit", minute: "2-digit", hour12: false });
const fmtTimeSec = new Intl.DateTimeFormat("th-TH", { timeZone: TZ, hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false });
const hhmm = (iso: string | null) => (iso ? fmtTime.format(new Date(iso)) : "-");

function ago(ms: number) {
  const s = Math.round(ms / 1000);
  if (s < 60) return `${Math.max(s, 1)} วินาทีที่แล้ว`;
  const m = Math.round(s / 60);
  if (m < 60) return `${m} นาทีที่แล้ว`;
  const h = Math.round(m / 60);
  return h < 24 ? `${h} ชม.ที่แล้ว` : `${Math.round(h / 24)} วันที่แล้ว`;
}

type Fresh = { key: "live" | "recent" | "stale" | "old" | "past" | "none"; color: string; label: string };
function freshness(last: PingDTO | null, now: number, isToday: boolean, liveMs: number): Fresh {
  if (!last) return { key: "none", color: "#9ca3af", label: "ไม่มีข้อมูลตำแหน่ง" };
  if (!isToday) return { key: "past", color: "#10866a", label: `ล่าสุด ${hhmm(last.recordedAt)}` };
  const age = now - Date.parse(last.recordedAt);
  if (age <= liveMs) return { key: "live", color: "#0ca30c", label: "LIVE" };
  if (age <= 10 * 60_000) return { key: "recent", color: "#10866a", label: ago(age) };
  if (age <= 60 * 60_000) return { key: "stale", color: "#d97706", label: ago(age) };
  return { key: "old", color: "#6b7280", label: ago(age) };
}

const moving = (p: PingDTO | null) => !!p && p.speedKmh != null && p.speedKmh >= 3;

function pinHtml(f: Fresh, selected: boolean, last: PingDTO) {
  const head =
    moving(last) && last.heading != null
      ? `<div class="amn-head" style="--c:${f.color};transform:rotate(${Math.round(last.heading)}deg)"></div>`
      : "";
  return `<div class="amn-pin">${head}<div class="amn-dot amn-${f.key}${selected ? " amn-sel" : ""}" style="--c:${f.color}"></div></div>`;
}

const esc = (s: string) =>
  s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);

export default function LiveTracking(props: Props) {
  const { date, isToday, liveWindowMs, sourceLabels } = props;
  const [people, setPeople] = useState<LivePerson[]>(props.people);
  const [selected, setSelected] = useState<string | null>(props.initialSelected);
  const [trail, setTrail] = useState<PingDTO[]>(props.initialTrail);
  const [follow, setFollow] = useState(true);
  const [conn, setConn] = useState<"connecting" | "live" | "reconnecting" | "past">(isToday ? "connecting" : "past");
  const [now, setNow] = useState(() => Date.now());
  const [ready, setReady] = useState(false);

  const el = useRef<HTMLDivElement>(null);
  const L = useRef<typeof Leaflet | null>(null);
  const map = useRef<Leaflet.Map | null>(null);
  const markers = useRef(new Map<string, { marker: Leaflet.Marker; html: string; anim: number | null }>());
  const siteLayer = useRef<Leaflet.LayerGroup | null>(null);
  const trailLine = useRef<Leaflet.Polyline | null>(null);
  const trailDots = useRef<Leaflet.LayerGroup | null>(null);
  const seen = useRef(new Set<string>());
  const selectedRef = useRef(selected);
  const fitted = useRef(false);
  selectedRef.current = selected;

  // ---------- clock for "x seconds ago" ----------
  useEffect(() => {
    const id = window.setInterval(() => setNow(Date.now()), 5000);
    return () => window.clearInterval(id);
  }, []);

  // ---------- map (created once) ----------
  useEffect(() => {
    let disposed = false;
    import("leaflet").then((mod) => {
      if (disposed || !el.current) return;
      L.current = mod;
      const m = mod.map(el.current, { zoomControl: true }).setView([13.7563, 100.5018], 11);
      mod
        .tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        })
        .addTo(m);
      siteLayer.current = mod.layerGroup().addTo(m);
      trailLine.current = mod.polyline([], { color: "#0f6b57", weight: 4, opacity: 0.75 }).addTo(m);
      trailDots.current = mod.layerGroup().addTo(m);
      map.current = m;
      setReady(true);
    });
    return () => {
      disposed = true;
      markers.current.forEach((x) => x.anim && cancelAnimationFrame(x.anim));
      markers.current.clear();
      map.current?.remove();
      map.current = null;
    };
  }, []);

  const select = useCallback(
    (id: string | null) => {
      setSelected(id);
      const qs = new URLSearchParams({ date });
      if (id) qs.set("employee", id);
      window.history.replaceState(null, "", `/tracking?${qs.toString()}`);
      if (!id) {
        setTrail([]);
        return;
      }
      setFollow(true);
      fetch(`/api/tracking/trail?employee=${encodeURIComponent(id)}&date=${date}`)
        .then((r) => (r.ok ? r.json() : { points: [] }))
        .then((d: { points: PingDTO[] }) => {
          if (selectedRef.current !== id) return;
          setTrail(d.points);
          const pts = d.points.map((p) => [p.lat, p.lng] as [number, number]);
          if (pts.length > 1) map.current?.fitBounds(pts, { padding: [40, 40], maxZoom: 16 });
          else if (pts.length === 1) map.current?.setView(pts[0], 16);
        })
        .catch(() => {});
    },
    [date]
  );

  // ---------- live stream ----------
  useEffect(() => {
    if (!isToday) return;
    const es = new EventSource(`/api/tracking/stream?since=${encodeURIComponent(props.since)}`);
    es.onopen = () => setConn("live");
    es.onerror = () => setConn("reconnecting");
    es.addEventListener("hello", () => setConn("live"));
    es.addEventListener("ping", (e) => {
      const p = JSON.parse((e as MessageEvent).data) as PingDTO;
      if (seen.current.has(p.id)) return;
      seen.current.add(p.id);
      setPeople((prev) =>
        prev.map((x) =>
          x.id !== p.employeeId
            ? x
            : {
                ...x,
                count: x.count + 1,
                last: !x.last || Date.parse(p.recordedAt) >= Date.parse(x.last.recordedAt) ? p : x.last,
              }
        )
      );
      if (selectedRef.current === p.employeeId) {
        setTrail((prev) => (prev.some((t) => t.id === p.id) ? prev : [...prev, p].sort((a, b) => a.recordedAt.localeCompare(b.recordedAt))));
      }
    });
    es.addEventListener("status", (e) => {
      const list = JSON.parse((e as MessageEvent).data) as FieldStatus[];
      setPeople((prev) =>
        prev.map((x) => {
          const s = list.find((y) => y.id === x.id);
          return s ? { ...x, shiftOpen: s.shiftOpen, shiftStart: s.shiftStart, shiftEnd: s.shiftEnd, visit: s.visit } : x;
        })
      );
    });
    return () => es.close();
  }, [isToday, props.since]);

  // ---------- markers follow the data ----------
  useEffect(() => {
    const mod = L.current;
    const m = map.current;
    if (!ready || !mod || !m) return;
    for (const p of people) {
      if (!p.last) continue;
      const f = freshness(p.last, now, isToday, liveWindowMs);
      const html = pinHtml(f, selected === p.id, p.last);
      const to: [number, number] = [p.last.lat, p.last.lng];
      const entry = markers.current.get(p.id);
      if (!entry) {
        const marker = mod
          .marker(to, {
            icon: mod.divIcon({ className: "", html, iconSize: [36, 36], iconAnchor: [18, 18] }),
            zIndexOffset: selected === p.id ? 1000 : 0,
            title: p.name,
          })
          .bindTooltip(esc(p.short), { permanent: true, direction: "top", offset: [0, -14] })
          .on("click", () => select(p.id))
          .addTo(m);
        markers.current.set(p.id, { marker, html, anim: null });
        continue;
      }
      if (entry.html !== html) {
        entry.marker.setIcon(mod.divIcon({ className: "", html, iconSize: [36, 36], iconAnchor: [18, 18] }));
        entry.html = html;
      }
      entry.marker.setZIndexOffset(selected === p.id ? 1000 : 0);
      const from = entry.marker.getLatLng();
      if (from.lat !== to[0] || from.lng !== to[1]) {
        // Glide to the new position instead of jumping.
        if (entry.anim) cancelAnimationFrame(entry.anim);
        const t0 = performance.now();
        const step = (t: number) => {
          const k = Math.min(1, (t - t0) / 900);
          const e = 1 - (1 - k) * (1 - k);
          entry.marker.setLatLng([from.lat + (to[0] - from.lat) * e, from.lng + (to[1] - from.lng) * e]);
          entry.anim = k < 1 ? requestAnimationFrame(step) : null;
        };
        entry.anim = requestAnimationFrame(step);
        if (follow && selected === p.id) m.panTo(to, { animate: true });
      }
    }
    if (!fitted.current) {
      fitted.current = true;
      const pts = people.filter((p) => p.last).map((p) => [p.last!.lat, p.last!.lng] as [number, number]);
      const all = selected && trail.length ? trail.map((t) => [t.lat, t.lng] as [number, number]) : pts;
      if (all.length > 1) m.fitBounds(all, { padding: [40, 40], maxZoom: 16 });
      else if (all.length === 1) m.setView(all[0], 15);
    }
  }, [ready, people, selected, now, isToday, liveWindowMs, follow, select, trail]);

  // ---------- on-site markers ----------
  useEffect(() => {
    const mod = L.current;
    if (!ready || !mod || !siteLayer.current) return;
    siteLayer.current.clearLayers();
    for (const p of people) {
      if (!p.visit || p.visit.lat == null || p.visit.lng == null) continue;
      mod
        .marker([p.visit.lat, p.visit.lng], {
          icon: mod.divIcon({ className: "", html: '<div class="amn-site"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }),
          interactive: true,
        })
        .bindPopup(`<strong>${esc(p.visit.customer)}</strong><br>หน้างาน ${esc(p.visit.jobNo)} · ${esc(p.short)}<br><a href="/jobs/${esc(p.visit.jobId)}">ดูงาน →</a>`)
        .addTo(siteLayer.current);
    }
  }, [ready, people]);

  // ---------- selected person's trail ----------
  useEffect(() => {
    const mod = L.current;
    if (!ready || !mod || !trailLine.current || !trailDots.current) return;
    trailLine.current.setLatLngs(trail.map((t) => [t.lat, t.lng] as [number, number]));
    trailDots.current.clearLayers();
    for (const t of trail) {
      if (t.source === "TRACKING" || t.source === "BACKGROUND_APP") continue;
      mod
        .circleMarker([t.lat, t.lng], { radius: 6, color: "#fff", weight: 2, fillColor: "#4f46e5", fillOpacity: 1 })
        .bindTooltip(`${hhmm(t.recordedAt)} · ${esc(sourceLabels[t.source] ?? t.source)}${t.jobNo ? ` · ${esc(t.jobNo)}` : ""}`)
        .addTo(trailDots.current);
    }
  }, [ready, trail, sourceLabels]);

  const sorted = useMemo(() => {
    const rank = (p: LivePerson) => {
      const f = freshness(p.last, now, isToday, liveWindowMs).key;
      return f === "live" ? 0 : f === "recent" ? 1 : f === "stale" ? 2 : f === "past" ? 1 : f === "old" ? 3 : 4;
    };
    return [...people].sort(
      (a, b) =>
        rank(a) - rank(b) ||
        (b.last ? Date.parse(b.last.recordedAt) : 0) - (a.last ? Date.parse(a.last.recordedAt) : 0) ||
        a.name.localeCompare(b.name, "th")
    );
  }, [people, now, isToday, liveWindowMs]);

  const sel = people.find((p) => p.id === selected) ?? null;
  const liveCount = people.filter((p) => freshness(p.last, now, isToday, liveWindowMs).key === "live").length;

  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center gap-3 text-sm">
        {conn === "past" ? (
          <span className="badge bg-gray-100 text-gray-700">ข้อมูลย้อนหลัง</span>
        ) : conn === "live" ? (
          <span className="badge bg-emerald-100 text-emerald-800">
            <span className="mr-1.5 inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-600" /> เชื่อมต่อสด
          </span>
        ) : (
          <span className="badge bg-amber-100 text-amber-800">{conn === "connecting" ? "กำลังเชื่อมต่อ…" : "สัญญาณหลุด กำลังเชื่อมต่อใหม่…"}</span>
        )}
        {isToday && (
          <span className="text-gray-600">
            LIVE ตอนนี้ <b className="tabular-nums">{liveCount}</b> / {people.length} คน
          </span>
        )}
        {sel && (
          <button type="button" onClick={() => select(null)} className="link">
            ← ดูทุกคน
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div ref={el} className="z-0 h-[420px] w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-100 lg:h-[540px]" />
          <div className="mt-2 flex flex-wrap gap-4 text-xs text-gray-600">
            <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-[#0ca30c]" /> LIVE (ภายใน {Math.round(liveWindowMs / 1000)} วิ)</span>
            <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-[#10866a]" /> ไม่เกิน 10 นาที</span>
            <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-[#d97706]" /> ไม่เกิน 1 ชม.</span>
            <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-[#6b7280]" /> นานกว่านั้น</span>
            <span className="inline-flex items-center gap-1.5"><span className="amn-site inline-block" /> หน้างานที่กำลังทำ</span>
          </div>

          {sel && (
            <section className="card mt-4 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="font-semibold text-gray-900">{sel.name}</div>
                  <div className="text-sm text-gray-600">
                    {sel.last ? (
                      <>
                        อัปเดต {fmtTimeSec.format(new Date(sel.last.recordedAt))} น.
                        {sel.last.speedKmh != null && ` · ${Math.round(sel.last.speedKmh)} กม./ชม.`}
                        {sel.last.accuracy != null && ` · แม่นยำ ±${Math.round(sel.last.accuracy)} ม.`}
                        {sel.last.battery != null && ` · แบต ${Math.round(sel.last.battery)}%`}
                        {` · ${sourceLabels[sel.last.source] ?? sel.last.source}`}
                      </>
                    ) : (
                      "ยังไม่มีตำแหน่งในวันที่เลือก"
                    )}
                  </div>
                </div>
                <div className="flex flex-wrap gap-2">
                  {sel.phone && (
                    <a href={`tel:${sel.phone}`} className="btn btn-secondary text-xs">
                      📞 โทร
                    </a>
                  )}
                  {sel.last && (
                    <a
                      href={`https://www.google.com/maps/search/?api=1&query=${sel.last.lat},${sel.last.lng}`}
                      target="_blank"
                      rel="noreferrer"
                      className="btn btn-secondary text-xs"
                    >
                      Google Maps
                    </a>
                  )}
                  {isToday && (
                    <label className="btn btn-secondary cursor-pointer text-xs">
                      <input type="checkbox" checked={follow} onChange={(e) => setFollow(e.target.checked)} className="mr-1" />
                      กล้องตามตัว
                    </label>
                  )}
                </div>
              </div>
              {trail.length > 0 && (
                <div className="mt-3 max-h-72 overflow-y-auto">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>เวลา</th>
                        <th>ที่มา</th>
                        <th className="num">ความเร็ว</th>
                        <th className="num">แบต</th>
                        <th>งาน</th>
                        <th>พิกัด</th>
                      </tr>
                    </thead>
                    <tbody>
                      {[...trail]
                        .reverse()
                        .slice(0, 200)
                        .map((t) => (
                          <tr key={t.id}>
                            <td className="tabular-nums">{fmtTimeSec.format(new Date(t.recordedAt))}</td>
                            <td>{sourceLabels[t.source] ?? t.source}</td>
                            <td className="num">{t.speedKmh != null ? `${Math.round(t.speedKmh)} กม./ชม.` : "-"}</td>
                            <td className="num">{t.battery != null ? `${Math.round(t.battery)}%` : "-"}</td>
                            <td>{t.jobNo ?? "-"}</td>
                            <td>
                              <a
                                href={`https://www.google.com/maps/search/?api=1&query=${t.lat},${t.lng}`}
                                target="_blank"
                                rel="noreferrer"
                                className="link"
                              >
                                {t.lat.toFixed(5)}, {t.lng.toFixed(5)}
                              </a>
                            </td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          )}
        </div>

        <ul className="card h-fit divide-y divide-gray-100" aria-label="รายชื่อช่าง">
          {sorted.map((p) => {
            const f = freshness(p.last, now, isToday, liveWindowMs);
            const active = selected === p.id;
            return (
              <li key={p.id}>
                <button
                  type="button"
                  onClick={() => select(active ? null : p.id)}
                  className={`block w-full px-4 py-3 text-left hover:bg-gray-50 ${active ? "bg-brand-50" : ""}`}
                  data-person={p.id}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="flex min-w-0 items-center gap-2 font-semibold text-gray-900">
                      <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ background: f.color }} />
                      <span className="truncate">{p.name}</span>
                    </span>
                    {f.key === "live" ? (
                      <span className="badge bg-emerald-100 text-emerald-800" data-live>
                        LIVE
                      </span>
                    ) : (
                      <span className="shrink-0 text-xs text-gray-500">{p.last ? f.label : ""}</span>
                    )}
                  </div>
                  <div className="mt-0.5 space-x-2 pl-4 text-xs text-gray-500">
                    {p.visit ? (
                      <span className="text-indigo-700">
                        🛠️ อยู่หน้างาน {p.visit.jobNo} · {p.visit.customer}
                      </span>
                    ) : moving(p.last) && f.key !== "old" ? (
                      <span className="text-gray-800">🚗 กำลังเดินทาง {Math.round(p.last!.speedKmh!)} กม./ชม.</span>
                    ) : p.shiftOpen ? (
                      <span>ทำงานอยู่ · เริ่ม {hhmm(p.shiftStart)}</span>
                    ) : p.shiftStart ? (
                      <span>
                        ทำงาน {hhmm(p.shiftStart)}–{hhmm(p.shiftEnd)}
                      </span>
                    ) : (
                      <span>ไม่ได้เริ่มงาน</span>
                    )}
                    {p.last?.battery != null && (
                      <span className={p.last.battery < 20 ? "font-semibold text-red-600" : ""}>🔋 {Math.round(p.last.battery)}%</span>
                    )}
                    {p.last?.source === "BACKGROUND_APP" && <span title="ส่งจากแอปติดตามเบื้องหลัง">📲</span>}
                  </div>
                </button>
              </li>
            );
          })}
          {people.length === 0 && <li className="p-4 text-sm text-gray-400">ยังไม่มีช่างในระบบ</li>}
        </ul>
      </div>
    </div>
  );
}
