import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "AMN-Sure ERP",
  description: "ระบบ ERP ภายในสำหรับธุรกิจเครื่องมือแพทย์มือสอง",
  robots: { index: false, follow: false },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="th">
      <body>{children}</body>
    </html>
  );
}
