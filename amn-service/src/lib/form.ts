import { UserError } from "@/lib/session";

// Small helpers to read FormData consistently.

export function str(fd: FormData, key: string): string {
  return String(fd.get(key) ?? "").trim();
}

export function optStr(fd: FormData, key: string): string | null {
  const v = str(fd, key);
  return v === "" ? null : v;
}

export function required(fd: FormData, key: string, label: string): string {
  const v = str(fd, key);
  if (!v) throw new UserError(`กรุณากรอก${label}`);
  return v;
}

export function optNum(fd: FormData, key: string): number | null {
  const v = str(fd, key);
  if (v === "") return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

export function oneOf<T extends string>(value: string, allowed: readonly T[], label: string): T {
  if (!(allowed as readonly string[]).includes(value)) throw new UserError(`${label}ไม่ถูกต้อง`);
  return value as T;
}
