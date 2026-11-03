"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import { actionUser, run } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";
import { hashToken, newToken } from "@/lib/tracking";

export type TokenState = ActionState & { token?: string };

/** Creates (or replaces) this technician's personal background-tracking token. Shown once. */
export async function createTrackerTokenAction(_prev: TokenState): Promise<TokenState> {
  let token = "";
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    token = newToken();
    await prisma.employee.update({
      where: { id: user.id },
      data: { trackingTokenHash: hashToken(token), trackingTokenHint: token.slice(-4), trackingTokenAt: new Date() },
    });
  });
  if (res.error) return res;
  revalidatePath("/m/tracker");
  return { ok: true, token };
}

export async function revokeTrackerTokenAction(_prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    const user = await actionUser(can.doFieldWork);
    await prisma.employee.update({
      where: { id: user.id },
      data: { trackingTokenHash: null, trackingTokenHint: null, trackingTokenAt: null },
    });
    return { ok: true, message: "ยกเลิกการเชื่อมต่อแล้ว — แอปติดตามเดิมจะส่งตำแหน่งเข้าระบบไม่ได้อีก" };
  });
  if (res.ok) revalidatePath("/m/tracker");
  return res;
}
