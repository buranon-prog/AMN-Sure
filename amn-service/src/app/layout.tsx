import type { Metadata, Viewport } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "AMN Service",
  description: "ระบบงานบริการ AMN — ลงงาน จ่ายงาน รายงานหน้างาน และติดตามทีมช่าง",
  robots: { index: false, follow: false },
  appleWebApp: { capable: true, title: "AMN Service", statusBarStyle: "default" },
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#10866a",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="th">
      <body>{children}</body>
    </html>
  );
}
