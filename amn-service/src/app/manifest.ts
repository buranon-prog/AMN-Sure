import type { MetadataRoute } from "next";

// Lets technicians "Add to Home Screen" so the app opens full-screen like a
// native app on their phone.
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "AMN Service",
    short_name: "AMN Service",
    description: "งานบริการ AMN สำหรับทีมช่าง",
    start_url: "/",
    display: "standalone",
    background_color: "#f3f5f7",
    theme_color: "#10866a",
    lang: "th",
    icons: [
      { src: "/icon.svg", sizes: "any", type: "image/svg+xml", purpose: "any" },
      { src: "/apple-icon", sizes: "180x180", type: "image/png" },
    ],
  };
}
