import NextAuth from "next-auth";
import { authConfig } from "@/auth.config";

// Only checks that a session cookie exists (pages only — every /api route
// authenticates itself and answers 401/403 instead of redirecting). Role checks happen server-side
// in every page and server action (see src/lib/session.ts).
export default NextAuth(authConfig).auth;

export const config = {
  matcher: [
    "/((?!api/|login|_next/static|_next/image|favicon.ico|manifest.webmanifest|sw.js|icon|apple-icon).*)",
  ],
};
