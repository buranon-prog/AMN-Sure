import NextAuth from "next-auth";
import { authConfig } from "@/auth.config";

// Edge-safe middleware: uses auth.config.ts only (no Prisma/bcrypt), so it
// can run in the Edge Runtime. It just checks whether a valid session cookie
// exists; per-module permission checks happen server-side in each page.
export default NextAuth(authConfig).auth;

export const config = {
  matcher: ["/((?!api/auth|login|_next/static|_next/image|favicon.ico).*)"],
};
