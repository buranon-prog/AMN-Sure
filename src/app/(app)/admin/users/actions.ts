"use server";

import bcrypt from "bcryptjs";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { assertManager } from "@/lib/rbac";
import { MODULES, ROLES, type ModuleName } from "@/lib/constants";

export async function createUserAction(formData: FormData) {
  await assertManager();

  const email = String(formData.get("email") || "").toLowerCase().trim();
  const name = String(formData.get("name") || "").trim();
  const password = String(formData.get("password") || "");
  const role = String(formData.get("role") || "EMPLOYEE");

  if (!email || !name || password.length < 6) {
    throw new Error("กรุณากรอกข้อมูลให้ครบ และรหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร");
  }
  if (!ROLES.includes(role as (typeof ROLES)[number])) {
    throw new Error("บทบาทไม่ถูกต้อง");
  }

  const passwordHash = await bcrypt.hash(password, 10);

  const user = await prisma.user.create({
    data: { email, name, passwordHash, role },
  });

  // Managers implicitly see everything; give employees an empty permission
  // row per module so the admin UI has something to toggle.
  if (role === "EMPLOYEE") {
    await prisma.userPermission.createMany({
      data: MODULES.map((m) => ({ userId: user.id, module: m, canView: false, canEdit: false })),
    });
  }

  revalidatePath("/admin/users");
}

export async function toggleActiveAction(userId: string) {
  await assertManager();
  const user = await prisma.user.findUniqueOrThrow({ where: { id: userId } });
  await prisma.user.update({ where: { id: userId }, data: { active: !user.active } });
  revalidatePath("/admin/users");
}

export async function resetPasswordAction(userId: string, formData: FormData) {
  await assertManager();
  const password = String(formData.get("password") || "");
  if (password.length < 6) throw new Error("รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร");
  const passwordHash = await bcrypt.hash(password, 10);
  await prisma.user.update({ where: { id: userId }, data: { passwordHash } });
  revalidatePath(`/admin/users/${userId}`);
}

export async function updatePermissionsAction(userId: string, formData: FormData) {
  await assertManager();

  await prisma.$transaction(
    MODULES.map((m: ModuleName) =>
      prisma.userPermission.upsert({
        where: { userId_module: { userId, module: m } },
        create: {
          userId,
          module: m,
          canView: formData.get(`view_${m}`) === "on",
          canEdit: formData.get(`edit_${m}`) === "on",
        },
        update: {
          canView: formData.get(`view_${m}`) === "on",
          canEdit: formData.get(`edit_${m}`) === "on",
        },
      })
    )
  );

  revalidatePath(`/admin/users/${userId}`);
  revalidatePath("/admin/users");
}
