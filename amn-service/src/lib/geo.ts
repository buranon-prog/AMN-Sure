/** Great-circle distance in metres. */
export function distanceMeters(aLat: number, aLng: number, bLat: number, bLng: number) {
  const R = 6371000;
  const toRad = (x: number) => (x * Math.PI) / 180;
  const dLat = toRad(bLat - aLat);
  const dLng = toRad(bLng - aLng);
  const h =
    Math.sin(dLat / 2) ** 2 + Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

export function formatDistance(m: number | null | undefined) {
  if (m == null) return "-";
  return m >= 1000 ? `${(m / 1000).toFixed(1)} กม.` : `${Math.round(m)} ม.`;
}

/** Google Maps link: directions when coordinates exist, else address search. */
export function mapsUrl(lat?: number | null, lng?: number | null, address?: string | null) {
  if (lat != null && lng != null) {
    return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  }
  if (address) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
  return null;
}

export function pointUrl(lat: number, lng: number) {
  return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
}

/** Parse an optional coordinate from a form field. */
export function parseCoord(raw: FormDataEntryValue | null, kind: "lat" | "lng"): number | null {
  if (raw == null || String(raw).trim() === "") return null;
  const n = Number(raw);
  if (!Number.isFinite(n)) return null;
  const limit = kind === "lat" ? 90 : 180;
  return Math.abs(n) <= limit ? n : null;
}

/** A check-in farther than this from the site is flagged for the office. */
export const CHECKIN_WARN_DISTANCE_M = 500;
