"use client";

import { useEffect, useRef, useState } from "react";

type Status = "off" | "starting" | "tracking" | "outside" | "denied" | "unsupported" | "error";

function meters(a: { lat: number; lng: number }, b: { lat: number; lng: number }) {
  const R = 6371000;
  const toRad = (x: number) => (x * Math.PI) / 180;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

type BatteryManager = { level: number; addEventListener: (t: string, f: () => void) => void };
type WakeLockSentinel = { release: () => Promise<void>; addEventListener: (t: string, f: () => void) => void };

const TRAVEL_KEY = "amn.travelMode";

/**
 * Real-time location sharing while the technician's shift is open:
 *   moving   → a point every ~8 s once they moved ≥ 20 m
 *   standing → a heartbeat every 60 s
 * Browsers pause web pages when the screen is off or another app is in front,
 * so "โหมดเดินทาง" keeps the screen awake, and the background tracking app
 * (see /m/tracker) covers the time the phone is locked.
 */
export default function LocationTracker({
  active,
  movingMs,
  heartbeatMs,
  minMoveM,
  windowLabel,
}: {
  active: boolean;
  movingMs: number;
  heartbeatMs: number;
  minMoveM: number;
  windowLabel: string;
}) {
  const [status, setStatus] = useState<Status>(active ? "starting" : "off");
  const [lastSent, setLastSent] = useState<Date | null>(null);
  const [travel, setTravel] = useState(false);
  const [wakeOk, setWakeOk] = useState<boolean | null>(null);
  const last = useRef<{ lat: number; lng: number; t: number } | null>(null);
  const sending = useRef(false);
  const battery = useRef<number | null>(null);
  const wake = useRef<WakeLockSentinel | null>(null);

  // Battery level (Android Chrome) — shown to the office so they know why a phone went quiet.
  useEffect(() => {
    const nav = navigator as Navigator & { getBattery?: () => Promise<BatteryManager> };
    nav.getBattery?.()
      .then((b) => {
        battery.current = Math.round(b.level * 100);
        b.addEventListener("levelchange", () => (battery.current = Math.round(b.level * 100)));
      })
      .catch(() => {});
    try {
      setTravel(localStorage.getItem(TRAVEL_KEY) === "1");
    } catch {
      /* storage blocked */
    }
  }, []);

  // ---------- โหมดเดินทาง: keep the screen on so tracking never pauses ----------
  useEffect(() => {
    const wl = (navigator as Navigator & { wakeLock?: { request: (t: "screen") => Promise<WakeLockSentinel> } }).wakeLock;
    if (!active || !travel) {
      wake.current?.release().catch(() => {});
      wake.current = null;
      return;
    }
    if (!wl) {
      setWakeOk(false);
      return;
    }
    const acquire = () =>
      wl
        .request("screen")
        .then((s) => {
          wake.current = s;
          // The OS drops the lock when the screen turns off / tab hides; re-acquired on return.
          s.addEventListener("release", () => {
            if (wake.current === s) wake.current = null;
          });
          setWakeOk(true);
        })
        .catch(() => setWakeOk(false));
    acquire();
    const onVisible = () => document.visibilityState === "visible" && !wake.current && acquire();
    document.addEventListener("visibilitychange", onVisible);
    return () => {
      document.removeEventListener("visibilitychange", onVisible);
      wake.current?.release().catch(() => {});
      wake.current = null;
    };
  }, [active, travel]);

  function toggleTravel() {
    const next = !travel;
    setTravel(next);
    try {
      localStorage.setItem(TRAVEL_KEY, next ? "1" : "0");
    } catch {
      /* storage blocked */
    }
  }

  // ---------- location stream ----------
  useEffect(() => {
    if (!active) {
      setStatus("off");
      return;
    }
    if (!("geolocation" in navigator)) {
      setStatus("unsupported");
      return;
    }
    setStatus("starting");

    const send = async (pos: GeolocationPosition) => {
      const here = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      const now = Date.now();
      const prev = last.current;
      if (prev) {
        const age = now - prev.t;
        const moved = meters(prev, here);
        const due = (moved >= minMoveM && age >= movingMs) || age >= heartbeatMs - 2000;
        if (!due) return;
      }
      if (sending.current) return;
      sending.current = true;
      const speed = pos.coords.speed;
      const heading = pos.coords.heading;
      try {
        const r = await fetch("/api/location", {
          method: "POST",
          headers: { "content-type": "application/json" },
          keepalive: true,
          body: JSON.stringify({
            ...here,
            accuracy: Math.round(pos.coords.accuracy),
            speedKmh: speed != null && speed >= 0 ? Math.round(speed * 3.6 * 10) / 10 : null,
            heading: heading != null && !Number.isNaN(heading) ? Math.round(heading) : null,
            battery: battery.current,
            at: new Date(pos.timestamp || now).toISOString(),
          }),
        });
        const j = (await r.json().catch(() => ({}))) as { accepted?: boolean; reason?: string };
        if (j.accepted || j.reason === "TOO_SOON") {
          // TOO_SOON: a point was saved moments ago (e.g. by เริ่มงาน / เช็คอิน) — still live.
          last.current = { ...here, t: now };
          setLastSent(new Date());
          setStatus("tracking");
        } else if (j.reason === "OUTSIDE_HOURS") {
          last.current = { ...here, t: now };
          setStatus("outside");
        } else if (r.status === 401) {
          window.location.href = "/login";
        } else {
          setStatus("error");
        }
      } catch {
        setStatus("error");
      } finally {
        sending.current = false;
      }
    };

    const onErr = (err: GeolocationPositionError) => setStatus(err.code === 1 ? "denied" : "error");
    const opts: PositionOptions = { enableHighAccuracy: true, maximumAge: 5_000, timeout: 30_000 };
    const poll = () => navigator.geolocation.getCurrentPosition(send, onErr, opts);

    const watchId = navigator.geolocation.watchPosition(send, onErr, opts);
    const timer = window.setInterval(poll, heartbeatMs);
    const onVisible = () => document.visibilityState === "visible" && poll();
    document.addEventListener("visibilitychange", onVisible);
    return () => {
      navigator.geolocation.clearWatch(watchId);
      window.clearInterval(timer);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, [active, movingMs, heartbeatMs, minMoveM]);

  if (status === "off") return null;

  const time = lastSent?.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false });
  const view: Record<Exclude<Status, "off">, { cls: string; text: string }> = {
    starting: { cls: "bg-sky-50 text-sky-800", text: "📍 กำลังเริ่มแชร์ตำแหน่ง…" },
    tracking: { cls: "bg-emerald-50 text-emerald-800", text: `📍 แชร์ตำแหน่งสด${time ? ` · ล่าสุด ${time}` : ""}` },
    outside: { cls: "bg-gray-100 text-gray-600", text: `📍 นอกช่วงเวลาติดตาม (${windowLabel}) — ไม่ได้บันทึกตำแหน่ง` },
    denied: { cls: "bg-red-50 text-red-700", text: "⚠️ ไม่ได้อนุญาตตำแหน่ง — เปิดสิทธิ์ Location ให้เบราว์เซอร์ แล้วรีเฟรชหน้า" },
    unsupported: { cls: "bg-red-50 text-red-700", text: "⚠️ อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง" },
    error: { cls: "bg-orange-50 text-orange-800", text: "⚠️ ส่งตำแหน่งไม่สำเร็จ จะลองใหม่อัตโนมัติ" },
  };
  const v = view[status];
  return (
    <div className={`flex items-center justify-between gap-2 px-4 py-1.5 text-xs font-medium ${v.cls}`}>
      <span>{v.text}</span>
      <button
        type="button"
        onClick={toggleTravel}
        className={`shrink-0 rounded-full border px-2 py-0.5 ${travel ? "border-emerald-600 bg-emerald-600 text-white" : "border-current"}`}
        title="เปิดจอค้างไว้ระหว่างเดินทาง เพื่อให้ส่งตำแหน่งได้ต่อเนื่อง"
      >
        🚗 โหมดเดินทาง{travel ? (wakeOk === false ? " (เครื่องไม่รองรับ)" : " เปิด") : ""}
      </button>
    </div>
  );
}
