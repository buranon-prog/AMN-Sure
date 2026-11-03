import NextAuth from "next-auth";
import Credentials from "next-auth/providers/credentials";
import bcrypt from "bcryptjs";
import type {} from "next-auth/jwt";
import { prisma } from "@/lib/prisma";
import { authConfig } from "@/auth.config";
import { LOCKOUT_MINUTES, MAX_LOGIN_ATTEMPTS } from "@/lib/config";

declare module "next-auth" {
  interface User {
    role?: string;
    employeeCode?: string;
  }
  interface Session {
    user: { id: string; name: string; role: string; employeeCode: string };
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    uid?: string;
    role?: string;
    employeeCode?: string;
  }
}

export function normalizeCode(raw: unknown) {
  return String(raw ?? "").trim().toUpperCase();
}

export const { handlers, auth, signIn, signOut } = NextAuth({
  ...authConfig,
  providers: [
    Credentials({
      credentials: {
        employeeCode: { label: "รหัสพนักงาน", type: "text" },
        password: { label: "รหัสผ่าน", type: "password" },
      },
      authorize: async (credentials) => {
        const code = normalizeCode(credentials?.employeeCode);
        const password = String(credentials?.password ?? "");
        if (!code || !password) return null;

        const emp = await prisma.employee.findUnique({ where: { employeeCode: code } });
        if (!emp || !emp.active) return null;
        if (emp.lockedUntil && emp.lockedUntil > new Date()) return null;

        const valid = await bcrypt.compare(password, emp.passwordHash);
        if (!valid) {
          const failed = emp.failedLoginCount + 1;
          await prisma.employee.update({
            where: { id: emp.id },
            data:
              failed >= MAX_LOGIN_ATTEMPTS
                ? { failedLoginCount: 0, lockedUntil: new Date(Date.now() + LOCKOUT_MINUTES * 60_000) }
                : { failedLoginCount: failed },
          });
          return null;
        }

        if (emp.failedLoginCount > 0 || emp.lockedUntil) {
          await prisma.employee.update({
            where: { id: emp.id },
            data: { failedLoginCount: 0, lockedUntil: null },
          });
        }

        return {
          id: emp.id,
          name: emp.nickname ? `${emp.firstName} (${emp.nickname})` : `${emp.firstName} ${emp.lastName}`,
          role: emp.role,
          employeeCode: emp.employeeCode,
        };
      },
    }),
  ],
  callbacks: {
    ...authConfig.callbacks,
    async jwt({ token, user }) {
      if (user) {
        token.uid = user.id;
        token.role = user.role;
        token.employeeCode = user.employeeCode;
      }
      return token;
    },
    async session({ session, token }) {
      session.user.id = token.uid as string;
      session.user.role = token.role as string;
      session.user.employeeCode = token.employeeCode as string;
      return session;
    },
  },
});
