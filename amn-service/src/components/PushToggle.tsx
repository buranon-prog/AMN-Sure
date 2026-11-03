"use client";

import { useEffect, useState } from "react";

type State = "loading" | "unsupported" | "ios-install" | "denied" | "off" | "on" | "busy" | "error";

function urlBase64ToUint8Array(base64: string) {
  const padding = "=".repeat((4 - (base64.length % 4)) % 4);
  const raw = atob((base64 + padding).replace(/-/g, "+").replace(/_/g, "/"));
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

function isIos() {
  return /iphone|ipad|ipod/i.test(navigator.userAgent);
}
function isStandalone() {
  return window.matchMedia("(display-mode: standalone)").matches || (navigator as { standalone?: boolean }).standalone === true;
}

/** "เปิดการแจ้งเตือน" — subscribes this phone to Web Push. */
export default function PushToggle({ publicKey, compact }: { publicKey: string | null; compact?: boolean }) {
  const [state, setState] = useState<State>("loading");

  useEffect(() => {
    if (!publicKey) return;
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
      setState(isIos() && !isStandalone() ? "ios-install" : "unsupported");
      return;
    }
    if (Notification.permission === "denied") {
      setState("denied");
      return;
    }
    navigator.serviceWorker
      .register("/sw.js")
      .then((reg) => reg.pushManager.getSubscription())
      .then(async (sub) => {
        if (sub && Notification.permission === "granted") {
          // Re-send so the server always has it under the current user.
          await fetch("/api/push", { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(sub) });
          setState("on");
        } else setState("off");
      })
      .catch(() => setState("error"));
  }, [publicKey]);

  async function enable() {
    if (!publicKey) return;
    setState("busy");
    try {
      const perm = await Notification.requestPermission();
      if (perm !== "granted") {
        setState(perm === "denied" ? "denied" : "off");
        return;
      }
      const reg = await navigator.serviceWorker.register("/sw.js");
      await navigator.serviceWorker.ready;
      const sub =
        (await reg.pushManager.getSubscription()) ??
        (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(publicKey) }));
      const r = await fetch("/api/push", { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(sub) });
      setState(r.ok ? "on" : "error");
    } catch {
      setState("error");
    }
  }

  async function disable() {
    setState("busy");
    try {
      const reg = await navigator.serviceWorker.getRegistration("/sw.js");
      const sub = await reg?.pushManager.getSubscription();
      if (sub) {
        await fetch("/api/push", { method: "DELETE", headers: { "content-type": "application/json" }, body: JSON.stringify({ endpoint: sub.endpoint }) });
        await sub.unsubscribe();
      }
      setState("off");
    } catch {
      setState("error");
    }
  }

  if (!publicKey || state === "loading") return null;

  const box = compact ? "rounded-xl p-3 text-sm" : "card p-4 text-sm";
  if (state === "on") {
    return compact ? null : (
      <div className={`${box} flex items-center justify-between gap-2`}>
        <span className="text-emerald-800">🔔 เปิดการแจ้งเตือนบนเครื่องนี้แล้ว</span>
        <button onClick={disable} className="text-xs text-gray-500 underline">
          ปิด
        </button>
      </div>
    );
  }
  if (state === "ios-install") {
    return (
      <div className={`${box} bg-sky-50 text-sky-900`}>
        🔔 iPhone: กดปุ่ม “แชร์” → “เพิ่มไปยังหน้าจอโฮม” แล้วเปิดแอปจากหน้าจอโฮม เพื่อรับแจ้งเตือนงาน
      </div>
    );
  }
  if (state === "unsupported") {
    return compact ? null : <div className={`${box} text-gray-500`}>เบราว์เซอร์นี้ไม่รองรับการแจ้งเตือน</div>;
  }
  if (state === "denied") {
    return (
      <div className={`${box} bg-red-50 text-red-700`}>
        🔕 การแจ้งเตือนถูกบล็อก — เปิดสิทธิ์ Notifications ให้เว็บนี้ในการตั้งค่าเบราว์เซอร์
      </div>
    );
  }
  return (
    <div className={`${box} flex items-center justify-between gap-3 bg-amber-50`}>
      <span className="text-amber-900">
        {state === "error" ? "เปิดการแจ้งเตือนไม่สำเร็จ ลองอีกครั้ง" : "🔔 เปิดการแจ้งเตือน เพื่อรู้ทันทีเมื่อได้รับงานใหม่"}
      </span>
      <button onClick={enable} disabled={state === "busy"} className="btn btn-primary shrink-0 px-3 py-1.5 text-xs">
        {state === "busy" ? "…" : "เปิด"}
      </button>
    </div>
  );
}
