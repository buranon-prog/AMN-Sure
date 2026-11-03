"use client";

import { useEffect, useRef } from "react";
import "leaflet/dist/leaflet.css";

export type MapMarker = {
  id: string;
  lat: number;
  lng: number;
  title: string;
  subtitle?: string;
  color: string;
  href?: string;
  kind?: "person" | "site" | "point";
};

const esc = (s: string) =>
  s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);

/** Leaflet + OpenStreetMap. Loaded client-side only (Leaflet needs `window`). Scroll-zoom is off so the page scrolls normally. */
export default function TrackingMap({
  markers,
  trail,
  height = 420,
}: {
  markers: MapMarker[];
  trail?: [number, number][];
  height?: number;
}) {
  const el = useRef<HTMLDivElement>(null);
  // Server refreshes hand us new arrays each time; only rebuild the map when the data really changed.
  const dataKey = JSON.stringify({ markers, trail });

  useEffect(() => {
    let disposed = false;
    let map: import("leaflet").Map | null = null;

    import("leaflet").then((L) => {
      if (disposed || !el.current) return;
      map = L.map(el.current, { scrollWheelZoom: false }).setView([13.7563, 100.5018], 11);
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(map);

      const bounds: [number, number][] = [];
      if (trail && trail.length > 1) {
        L.polyline(trail, { color: "#0f6b57", weight: 4, opacity: 0.7 }).addTo(map);
        bounds.push(...trail);
      }
      for (const m of markers) {
        const popup = `<strong>${esc(m.title)}</strong>${m.subtitle ? `<br>${esc(m.subtitle)}` : ""}${
          m.href ? `<br><a href="${esc(m.href)}">ดูรายละเอียด →</a>` : ""
        }`;
        const marker =
          m.kind === "site"
            ? L.marker([m.lat, m.lng], {
                icon: L.divIcon({
                  className: "",
                  html: `<div style="width:14px;height:14px;background:${m.color};border:2px solid #fff;border-radius:3px;box-shadow:0 0 0 1px rgba(0,0,0,.3)"></div>`,
                  iconSize: [14, 14],
                  iconAnchor: [7, 7],
                }),
              })
            : L.circleMarker([m.lat, m.lng], {
                radius: m.kind === "point" ? 5 : 10,
                color: "#fff",
                weight: 2,
                fillColor: m.color,
                fillOpacity: 1,
              });
        marker.bindPopup(popup);
        if (m.kind !== "point" && m.kind !== "site") {
          marker.bindTooltip(esc(m.title), { permanent: true, direction: "top", offset: [0, -10], className: "" });
        }
        marker.addTo(map);
        bounds.push([m.lat, m.lng]);
      }
      if (bounds.length === 1) map.setView(bounds[0], 15);
      else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
    });

    return () => {
      disposed = true;
      map?.remove();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dataKey]);

  return <div ref={el} style={{ height }} className="z-0 w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-100" />;
}
