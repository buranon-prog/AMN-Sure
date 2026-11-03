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

/**
 * Shares the technician's location while their shift is open and this app is
 * on screen. Web browsers cannot track in the background, so check-in /
 * report / shift actions also record a location every time.
 */
export default function LocationTracker({
  active,
  intervalMs,
  minMoveM,
  windowLabel,
}: {
  active: boolean;
  intervalMs: number;
  minMoveM: number;
  windowLabel: string;
}) {
  const [status, setStatus] = useState<Status>(active ? "starting" : "off");
  const [lastSent, setLastSent] = useState<Date | null>(null);
  const last = useRef<{ lat: number; lng: number; t: number } | null>(null);
  const sending = useRef(false);

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
        if (age < 45_000) return;
        if (age < intervalMs - 5_000 && moved < minMoveM) return;
      }
      if (sending.current) return;
      sending.current = true;
      try {
        const r = await fetch("/api/location", {
          method: "POST",
          headers: { "content-type": "application/json" },
          body: JSON.stringify({ ...here, accuracy: Math.round(pos.coords.accuracy) }),
        });
        const j = (await r.json().catch(() => ({}))) as { accepted?: boolean; reason?: string };
        if (j.accepted) {
          last.current = { ...here, t: now };
          setLastSent(new Date());
          setStatus("tracking");
        } else if (j.reason === "OUTSIDE_HOURS") {
          last.current = { ...here, t: now };
          setStatus("outside");
        } else if (j.reason === "TOO_SOON") {
          // A location was saved moments ago (e.g. by เริ่มงาน / เช็คอิน) — we are tracking.
          last.current = { ...here, t: now };
          setLastSent((prev) => prev ?? new Date());
          setStatus("tracking");
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
    const opts: PositionOptions = { enableHighAccuracy: true, maximumAge: 60_000, timeout: 30_000 };
    const poll = () => navigator.geolocation.getCurrentPosition(send, onErr, opts);

    const watchId = navigator.geolocation.watchPosition(send, onErr, opts);
    const timer = window.setInterval(poll, intervalMs);
    const onVisible = () => document.visibilityState === "visible" && poll();
    document.addEventListener("visibilitychange", onVisible);
    return () => {
      navigator.geolocation.clearWatch(watchId);
      window.clearInterval(timer);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, [active, intervalMs, minMoveM]);

  if (status === "off") return null;

  const time = lastSent?.toLocaleTimeString("th-TH", { hour: "2-digit", minute: "2-digit", hour12: false });
  const view: Record<Exclude<Status, "off">, { cls: string; text: string }> = {
    starting: { cls: "bg-sky-50 text-sky-800", text: "📍 กำลังเริ่มแชร์ตำแหน่ง…" },
    tracking: { cls: "bg-emerald-50 text-emerald-800", text: `📍 แชร์ตำแหน่งระหว่างเวลางาน${time ? ` · ส่งล่าสุด ${time} น.` : ""}` },
    outside: { cls: "bg-gray-100 text-gray-600", text: `📍 นอกช่วงเวลาติดตาม (${windowLabel}) — ไม่ได้บันทึกตำแหน่ง` },
    denied: { cls: "bg-red-50 text-red-700", text: "⚠️ ไม่ได้อนุญาตตำแหน่ง — เปิดสิทธิ์ Location ให้เบราว์เซอร์ แล้วรีเฟรชหน้า" },
    unsupported: { cls: "bg-red-50 text-red-700", text: "⚠️ อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง" },
    error: { cls: "bg-orange-50 text-orange-800", text: "⚠️ ส่งตำแหน่งไม่สำเร็จ จะลองใหม่อัตโนมัติ" },
  };
  const v = view[status];
  return <div className={`px-4 py-1.5 text-center text-xs font-medium ${v.cls}`}>{v.text}</div>;
}
