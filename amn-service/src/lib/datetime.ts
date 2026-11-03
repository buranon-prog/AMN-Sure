// All timestamps are stored in UTC. The business runs in Thailand
// (Asia/Bangkok, UTC+7, no DST), so every form input and every display is
// interpreted in that zone regardless of the server's own TZ setting.

export const TIME_ZONE = "Asia/Bangkok";
const OFFSET = "+07:00";
const OFFSET_MS = 7 * 60 * 60 * 1000;

/** "2026-11-03T09:30" (from <input type="datetime-local">) → Date */
export function parseLocalDateTime(value: string | null | undefined): Date | null {
  if (!value) return null;
  const v = value.length === 16 ? `${value}:00` : value;
  const d = new Date(`${v}${OFFSET}`);
  return Number.isNaN(d.getTime()) ? null : d;
}

/** "2026-11-03" (from <input type="date">) → Date at 00:00 Bangkok */
export function parseLocalDate(value: string | null | undefined): Date | null {
  if (!value) return null;
  const d = new Date(`${value}T00:00:00${OFFSET}`);
  return Number.isNaN(d.getTime()) ? null : d;
}

function shifted(d: Date) {
  return new Date(d.getTime() + OFFSET_MS);
}

/** YYYY-MM-DD of the given instant in Bangkok. */
export function dateKey(d: Date = new Date()): string {
  return shifted(d).toISOString().slice(0, 10);
}

/** YYYY-MM of the given instant in Bangkok. */
export function monthKey(d: Date = new Date()): string {
  return shifted(d).toISOString().slice(0, 7);
}

/** HH:MM of the given instant in Bangkok. */
export function timeKey(d: Date = new Date()): string {
  return shifted(d).toISOString().slice(11, 16);
}

/** Value for <input type="datetime-local"> in Bangkok time. */
export function toLocalInput(d: Date | null | undefined): string {
  if (!d) return "";
  return shifted(d).toISOString().slice(0, 16);
}

/** [start, end) of a Bangkok calendar day given as YYYY-MM-DD. */
export function dayRange(key: string): { start: Date; end: Date } {
  const start = parseLocalDate(key) ?? parseLocalDate(dateKey())!;
  return { start, end: new Date(start.getTime() + 24 * 60 * 60 * 1000) };
}

/** [start, end) of a Bangkok calendar month given as YYYY-MM. */
export function monthRange(key: string): { start: Date; end: Date } {
  const [y, m] = key.split("-").map(Number);
  const valid = y > 2000 && m >= 1 && m <= 12;
  const [yy, mm] = valid ? [y, m] : monthKey().split("-").map(Number);
  const start = new Date(`${yy}-${String(mm).padStart(2, "0")}-01T00:00:00${OFFSET}`);
  const ny = mm === 12 ? yy + 1 : yy;
  const nm = mm === 12 ? 1 : mm + 1;
  const end = new Date(`${ny}-${String(nm).padStart(2, "0")}-01T00:00:00${OFFSET}`);
  return { start, end };
}

export function addDays(key: string, days: number): string {
  const { start } = dayRange(key);
  return dateKey(new Date(start.getTime() + days * 24 * 60 * 60 * 1000));
}

const fmt = (opts: Intl.DateTimeFormatOptions) =>
  new Intl.DateTimeFormat("th-TH", { timeZone: TIME_ZONE, ...opts });

const F_DATE = fmt({ day: "numeric", month: "short", year: "2-digit" });
const F_DATE_LONG = fmt({ weekday: "short", day: "numeric", month: "short", year: "numeric" });
const F_TIME = fmt({ hour: "2-digit", minute: "2-digit", hour12: false });
const F_MONTH = fmt({ month: "long", year: "numeric" });

export function formatDate(d: Date | null | undefined) {
  return d ? F_DATE.format(d) : "-";
}
export function formatDateLong(d: Date | null | undefined) {
  return d ? F_DATE_LONG.format(d) : "-";
}
export function formatTime(d: Date | null | undefined) {
  return d ? F_TIME.format(d) : "-";
}
export function formatDateTime(d: Date | null | undefined) {
  return d ? `${F_DATE.format(d)} ${F_TIME.format(d)}` : "-";
}
export function formatMonth(key: string) {
  return F_MONTH.format(monthRange(key).start);
}

/** "5 นาทีที่แล้ว" style relative time. */
export function timeAgo(d: Date | null | undefined, now: Date = new Date()) {
  if (!d) return "-";
  const s = Math.round((now.getTime() - d.getTime()) / 1000);
  if (s < 60) return "เมื่อสักครู่";
  const m = Math.round(s / 60);
  if (m < 60) return `${m} นาทีที่แล้ว`;
  const h = Math.round(m / 60);
  if (h < 24) return `${h} ชม.ที่แล้ว`;
  return `${Math.round(h / 24)} วันที่แล้ว`;
}

export function formatDuration(ms: number) {
  if (!Number.isFinite(ms) || ms <= 0) return "-";
  const totalMin = Math.round(ms / 60000);
  const h = Math.floor(totalMin / 60);
  const m = totalMin % 60;
  if (h >= 24) {
    const days = Math.floor(h / 24);
    return `${days} วัน ${h % 24} ชม.`;
  }
  return h > 0 ? `${h} ชม. ${m} นาที` : `${m} นาที`;
}
