import NextAuth from "next-auth";
import Credentials from "next-auth/providers/credentials";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";
import type { ModuleName } from "@/lib/constants";
import type {} from "next-auth/jwt";
import { authConfig } from "@/auth.config";

export type SessionPermissions = Partial<Record<ModuleName, { canView: boolean; canEdit: boolean }>>;

declare module "next-auth" {
  interface User {
    role?: string;
    permissions?: SessionPermissions;
  }
  interface Session {
    user: {
      id: string;
      email: string;
      name: string;
      role: string;
      permissions: SessionPermissions;
    };
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    uid?: string;
    role?: string;
    permissions?: SessionPermissions;
  }
}

export const { handlers, auth, signIn, signOut } = NextAuth({
  ...authConfig,
  providers: [
    Credentials({
      credentials: {
        email: { label: "Email", type: "email" },
        password: { label: "Password", type: "password" },
      },
      authorize: async (credentials) => {
        const email = credentials?.email as string | undefined;
        const password = credentials?.password as string | undefined;
        if (!email || !password) return null;

        const user = await prisma.user.findUnique({
          where: { email: email.toLowerCase().trim() },
          include: { permissions: true },
        });
        if (!user || !user.active) return null;

        const valid = await bcrypt.compare(password, user.passwordHash);
        if (!valid) return null;

        const permissions: SessionPermissions = {};
        for (const p of user.permissions) {
          permissions[p.module as ModuleName] = { canView: p.canView, canEdit: p.canEdit };
        }

        return {
          id: user.id,
          email: user.email,
          name: user.name,
          role: user.role,
          permissions,
        };
      },
    }),
  ],
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.uid = user.id;
        token.role = user.role;
        token.permissions = user.permissions;
      }
      return token;
    },
    async session({ session, token }) {
      session.user.id = token.uid as string;
      session.user.role = token.role as string;
      session.user.permissions = (token.permissions as SessionPermissions) ?? {};
      return session;
    },
  },
});
