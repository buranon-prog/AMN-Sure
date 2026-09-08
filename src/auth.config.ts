import type { NextAuthConfig } from "next-auth";

// Edge-safe config used by middleware. It must not pull in Prisma or
// bcryptjs (Node-only), so the Credentials provider itself lives in
// auth.ts and is added only for the Node.js runtime (route handlers,
// server actions, server components).
export const authConfig = {
  session: { strategy: "jwt" },
  pages: { signIn: "/login" },
  providers: [],
  callbacks: {
    authorized({ auth }) {
      return !!auth?.user;
    },
  },
} satisfies NextAuthConfig;
