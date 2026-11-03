import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { can } from "@/lib/permissions";
import { hashToken, ingestPoints, type TrackPoint } from "@/lib/tracking";

// Background tracking endpoint — keeps sending even when the phone screen is
// off or the technician is navigating in Google Maps. Point a background
// tracking app at:  https://<your-domain>/api/track?token=<personal token>
//
// Accepted formats (one endpoint, detected automatically):
//   • OwnTracks (HTTP mode)      JSON {"_type":"location","lat","lon","tst","acc","vel" km/h,"cog","batt"}
//                                token via ?token= or HTTP Basic auth password
//   • OsmAnd / Traccar Client    query or form fields id|deviceid, lat, lon, timestamp,
//                                speed (knots), bearing, accuracy, batt
//   • Traccar Client (JSON)      {"device_id", "location": {"timestamp", "coords": {latitude, longitude,
//                                accuracy, speed m/s, heading}, "battery": {"level": 0–1}}}
// Every format is answered with HTTP 200 + "[]" (what OwnTracks expects) when the
// token is valid, even if the point is dropped (no open shift / outside hours),
// so phones don't retry forever. Responses carry X-Tracking-Result for debugging.

export const dynamic = "force-dynamic";

type Json = Record<string, unknown>;
const KNOT_TO_KMH = 1.852;

const n = (v: unknown) => (v === null || v === undefined || v === "" ? null : Number(v));

function parseTime(v: unknown): Date | null {
  if (v === null || v === undefined || v === "") return null;
  const num = Number(v);
  if (Number.isFinite(num)) return new Date(num > 1e12 ? num : num * 1000); // ms or seconds
  const d = new Date(String(v));
  return Number.isNaN(d.getTime()) ? null : d;
}

function fromOwnTracks(b: Json): TrackPoint | null {
  if (b._type !== "location") return null;
  return {
    lat: n(b.lat)!,
    lng: n(b.lon)!,
    accuracy: n(b.acc),
    speedKmh: n(b.vel),
    heading: n(b.cog),
    battery: n(b.batt),
    at: parseTime(b.tst),
  };
}

function fromTraccarJson(loc: Json): TrackPoint | null {
  const c = (loc.coords ?? {}) as Json;
  if (c.latitude === undefined) return null;
  const speed = n(c.speed);
  const level = n((loc.battery as Json | undefined)?.level);
  return {
    lat: n(c.latitude)!,
    lng: n(c.longitude)!,
    accuracy: n(c.accuracy),
    speedKmh: speed != null && speed >= 0 ? speed * 3.6 : null,
    heading: n(c.heading),
    battery: level != null && level >= 0 ? (level <= 1 ? level * 100 : level) : null,
    at: parseTime(loc.timestamp),
  };
}

function fromOsmAnd(q: URLSearchParams): TrackPoint | null {
  const lat = q.get("lat");
  const lon = q.get("lon") ?? q.get("lng");
  if (!lat || !lon) return null;
  const speed = n(q.get("speed"));
  return {
    lat: Number(lat),
    lng: Number(lon),
    accuracy: n(q.get("accuracy") ?? q.get("hdop")),
    speedKmh: speed != null ? speed * KNOT_TO_KMH : null,
    heading: n(q.get("bearing") ?? q.get("heading")),
    battery: n(q.get("batt") ?? q.get("battery")),
    at: parseTime(q.get("timestamp")),
  };
}

function basicAuth(req: NextRequest): { user: string; pass: string } | null {
  const h = req.headers.get("authorization");
  if (!h?.toLowerCase().startsWith("basic ")) return null;
  try {
    const [user, ...rest] = Buffer.from(h.slice(6), "base64").toString("utf8").split(":");
    return { user, pass: rest.join(":") };
  } catch {
    return null;
  }
}

function reply(status: number, result: string) {
  return new NextResponse(status === 200 ? "[]" : result, {
    status,
    headers: { "Content-Type": status === 200 ? "application/json" : "text/plain", "X-Tracking-Result": result },
  });
}

async function handle(req: NextRequest) {
  const query = new URLSearchParams(req.nextUrl.searchParams);
  let body: unknown = null;
  if (req.method === "POST") {
    const type = req.headers.get("content-type") ?? "";
    const text = await req.text().catch(() => "");
    if (text.length > 512_000) return reply(413, "TOO_LARGE");
    if (type.includes("json") || /^\s*[[{]/.test(text)) {
      try {
        body = JSON.parse(text);
      } catch {
        return reply(400, "BAD_JSON");
      }
    } else if (text) {
      new URLSearchParams(text).forEach((v, k) => query.set(k, v)); // form-encoded OsmAnd
    }
  }

  const auth = basicAuth(req);
  const b = (body && typeof body === "object" && !Array.isArray(body) ? body : {}) as Json;
  const token =
    query.get("token") ?? query.get("id") ?? query.get("deviceid") ?? (b.device_id as string | undefined) ?? auth?.pass ?? auth?.user ?? null;
  if (!token || token.length < 16 || token.length > 200) return reply(401, "NO_TOKEN");

  const emp = await prisma.employee.findUnique({ where: { trackingTokenHash: hashToken(token) } });
  if (!emp || !emp.active || !can.doFieldWork(emp.role)) return reply(401, "BAD_TOKEN");

  const points: TrackPoint[] = [];
  const items = Array.isArray(body) ? (body as Json[]) : body ? [b] : [];
  for (const item of items) {
    if (item?._type) {
      const p = fromOwnTracks(item);
      if (p) points.push(p);
    } else if (item?.location) {
      const locs = Array.isArray(item.location) ? (item.location as Json[]) : [item.location as Json];
      for (const l of locs) {
        const p = fromTraccarJson(l);
        if (p) points.push(p);
      }
    } else if (item && (item as Json).coords) {
      const p = fromTraccarJson(item as Json);
      if (p) points.push(p);
    }
  }
  const q = fromOsmAnd(query);
  if (q) points.push(q);

  if (points.length === 0) return reply(200, "IGNORED"); // e.g. OwnTracks "lwt"/"transition" messages
  const res = await ingestPoints(emp.id, points.slice(0, 500), "BACKGROUND_APP");
  return reply(200, res.accepted ? `STORED:${res.accepted}` : res.reason ?? "DROPPED");
}

export const GET = handle;
export const POST = handle;
