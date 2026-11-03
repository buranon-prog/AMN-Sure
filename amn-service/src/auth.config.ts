import type { NextAuthConfig } from "next-auth";

// Edge-safe config used by middleware: no Prisma / bcrypt here. The
// Credentials provider is added in auth.ts (Node runtime only).
export const authConfig = {
  session: { strategy: "jwt", maxAge: 14 * 24 * 60 * 60 },
  pages: { signIn: "/login" },
  providers: [],
  callbacks: {
    authorized({ auth }) {
      return !!auth?.user;
    },
  },
} satisfies NextAuthConfig;
