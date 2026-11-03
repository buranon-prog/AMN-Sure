"use client";

import { useEffect, useRef, useState } from "react";
import { useFormState } from "react-dom";
import type { ActionState } from "@/lib/types";

type Props = {
  action: (state: ActionState, formData: FormData) => Promise<ActionState>;
  children: React.ReactNode;
  className?: string;
  /** Read the phone's GPS and send it as lat / lng / accuracy fields. */
  captureLocation?: boolean;
  /** Ask before submitting. */
  confirm?: string;
  resetOnSuccess?: boolean;
};

function getPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!("geolocation" in navigator)) {
      reject(new Error("อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง"));
      return;
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 30000,
    });
  });
}

function geoErrorText(err: unknown) {
  const code = (err as GeolocationPositionError)?.code;
  if (code === 1) return "ไม่ได้รับอนุญาตให้เข้าถึงตำแหน่ง (เปิดสิทธิ์ Location ให้เบราว์เซอร์)";
  if (code === 2) return "หาสัญญาณ GPS ไม่ได้";
  if (code === 3) return "หาตำแหน่งนานเกินไป";
  return (err as Error)?.message || "ระบุตำแหน่งไม่ได้";
}

/**
 * <form> wired to a server action via useFormState, showing the returned
 * error/message inline. Optionally captures GPS first (check-in, reports).
 */
export default function ActionForm({ action, children, className, captureLocation, confirm, resetOnSuccess }: Props) {
  const [state, formAction] = useFormState(action, {} as ActionState);
  const formRef = useRef<HTMLFormElement>(null);
  const bypass = useRef(false);
  const [locating, setLocating] = useState(false);
  const [geoNote, setGeoNote] = useState<string | null>(null);

  useEffect(() => {
    if (state.ok && resetOnSuccess) formRef.current?.reset();
  }, [state, resetOnSuccess]);

  function setField(form: HTMLFormElement, name: string, value: string) {
    const el = form.elements.namedItem(name) as HTMLInputElement | null;
    if (el) el.value = value;
  }

  function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    if (bypass.current) {
      bypass.current = false;
      return;
    }
    if (confirm && !window.confirm(confirm)) {
      e.preventDefault();
      return;
    }
    if (!captureLocation) return;

    e.preventDefault();
    const form = e.currentTarget;
    const submitter = (e.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null;
    setLocating(true);
    setGeoNote(null);

    getPosition()
      .then((pos) => {
        setField(form, "lat", String(pos.coords.latitude));
        setField(form, "lng", String(pos.coords.longitude));
        setField(form, "accuracy", String(Math.round(pos.coords.accuracy)));
        return true;
      })
      .catch((err) => {
        setField(form, "lat", "");
        setField(form, "lng", "");
        setField(form, "accuracy", "");
        const text = geoErrorText(err);
        setGeoNote(text);
        return window.confirm(`${text}\n\nต้องการส่งโดยไม่มีพิกัดหรือไม่?`);
      })
      .then((go) => {
        setLocating(false);
        if (!go) return;
        bypass.current = true;
        if (submitter) form.requestSubmit(submitter);
        else form.requestSubmit();
      });
  }

  return (
    <form ref={formRef} action={formAction} onSubmit={onSubmit} className={className}>
      {captureLocation && (
        <>
          <input type="hidden" name="lat" />
          <input type="hidden" name="lng" />
          <input type="hidden" name="accuracy" />
        </>
      )}
      {children}
      {locating && <p className="mt-2 text-sm text-sky-700">📍 กำลังระบุตำแหน่ง GPS…</p>}
      {geoNote && !locating && <p className="mt-2 text-xs text-orange-700">{geoNote}</p>}
      {state.error && (
        <p role="alert" className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
          {state.error}
        </p>
      )}
      {state.ok && state.message && (
        <p role="status" className="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {state.message}
        </p>
      )}
    </form>
  );
}
