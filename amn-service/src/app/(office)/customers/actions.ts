"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { actionUser, run, UserError } from "@/lib/session";
import type { ActionState } from "@/lib/types";
import { can } from "@/lib/permissions";
import { CUSTOMER_TYPE, MACHINE_STATUS } from "@/lib/constants";
import { parseLocalDate } from "@/lib/datetime";
import { parseCoord } from "@/lib/geo";
import { oneOf, optStr, required, str } from "@/lib/form";

function customerFields(fd: FormData) {
  return {
    name: required(fd, "name", "ชื่อลูกค้า"),
    type: oneOf(str(fd, "type") || "HOSPITAL", CUSTOMER_TYPE, "ประเภทลูกค้า"),
    contactName: optStr(fd, "contactName"),
    contactPhone: optStr(fd, "contactPhone"),
    email: optStr(fd, "email"),
    taxId: optStr(fd, "taxId"),
    notes: optStr(fd, "notes"),
  };
}

/**
 * Accepts either separate lat/lng fields or a pasted "13.75, 100.50" /
 * Google Maps link in the `coords` field.
 */
function siteFields(fd: FormData, prefix = "") {
  let lat = parseCoord(fd.get(`${prefix}latitude`), "lat");
  let lng = parseCoord(fd.get(`${prefix}longitude`), "lng");
  const pasted = str(fd, `${prefix}coords`);
  if (pasted) {
    const m = pasted.match(/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/);
    if (!m) throw new UserError("รูปแบบพิกัดไม่ถูกต้อง — วางเป็น 13.7563, 100.5018 หรือลิงก์ Google Maps");
    lat = parseCoord(m[1], "lat");
    lng = parseCoord(m[2], "lng");
  }
  if ((lat == null) !== (lng == null)) throw new UserError("กรุณากรอกพิกัดให้ครบทั้งละติจูดและลองจิจูด");
  return {
    name: required(fd, `${prefix}siteName`, "ชื่อสถานที่"),
    address: optStr(fd, `${prefix}address`),
    province: optStr(fd, `${prefix}province`),
    latitude: lat,
    longitude: lng,
    contactName: optStr(fd, `${prefix}siteContactName`),
    contactPhone: optStr(fd, `${prefix}siteContactPhone`),
  };
}

function machineFields(fd: FormData) {
  return {
    machineModelId: required(fd, "machineModelId", "รุ่นเครื่อง"),
    serialNumber: optStr(fd, "serialNumber"),
    locationDetail: optStr(fd, "locationDetail"),
    installDate: parseLocalDate(str(fd, "installDate")),
    warrantyEnd: parseLocalDate(str(fd, "warrantyEnd")),
    notes: optStr(fd, "notes"),
  };
}

export async function createCustomerAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  let id = "";
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    const customerCode = required(fd, "customerCode", "รหัสลูกค้า").toUpperCase();
    const data = customerFields(fd);
    const site = siteFields(fd);
    const created = await prisma.customer.create({
      data: { ...data, customerCode, sites: { create: site } },
    });
    id = created.id;
  });
  if (res.error) return res;
  revalidatePath("/customers");
  redirect(`/customers/${id}`);
}

export async function updateCustomerAction(customerId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    await prisma.customer.update({ where: { id: customerId }, data: customerFields(fd) });
    return { ok: true, message: "บันทึกแล้ว" };
  });
  if (res.ok) revalidatePath(`/customers/${customerId}`);
  return res;
}

export async function createSiteAction(customerId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    await prisma.site.create({ data: { ...siteFields(fd), customerId } });
    return { ok: true, message: "เพิ่มสถานที่แล้ว" };
  });
  if (res.ok) revalidatePath(`/customers/${customerId}`);
  return res;
}

export async function updateSiteAction(siteId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    const site = await prisma.site.update({ where: { id: siteId }, data: siteFields(fd) });
    revalidatePath(`/customers/${site.customerId}`);
    return { ok: true, message: "บันทึกสถานที่แล้ว" };
  });
  return res;
}

export async function createMachineAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  let id = "";
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    const siteId = required(fd, "siteId", "สถานที่ติดตั้ง");
    const machineCode = required(fd, "machineCode", "รหัสเครื่องลูกค้า").toUpperCase();
    const site = await prisma.site.findUnique({ where: { id: siteId } });
    if (!site) throw new UserError("ไม่พบสถานที่ที่เลือก");
    const created = await prisma.machine.create({ data: { ...machineFields(fd), siteId, machineCode } });
    id = created.id;
    revalidatePath(`/customers/${site.customerId}`);
  });
  if (res.error) return res;
  revalidatePath("/machines");
  redirect(`/machines/${id}`);
}

export async function updateMachineAction(machineId: string, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    const siteId = required(fd, "siteId", "สถานที่ติดตั้ง");
    const status = oneOf(str(fd, "status") || "ACTIVE", MACHINE_STATUS, "สถานะเครื่อง");
    await prisma.machine.update({ where: { id: machineId }, data: { ...machineFields(fd), siteId, status } });
    return { ok: true, message: "บันทึกข้อมูลเครื่องแล้ว" };
  });
  if (res.ok) {
    revalidatePath(`/machines/${machineId}`);
    revalidatePath("/machines");
  }
  return res;
}

export async function createModelAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    await prisma.machineModel.create({
      data: {
        brand: required(fd, "brand", "ยี่ห้อ"),
        model: required(fd, "model", "รุ่น"),
        category: required(fd, "category", "หมวดเครื่อง"),
        description: optStr(fd, "description"),
      },
    });
    return { ok: true, message: "เพิ่มรุ่นเครื่องแล้ว" };
  });
  if (res.ok) revalidatePath("/models");
  return res;
}

export async function toggleModelAction(modelId: string, _prev: ActionState): Promise<ActionState> {
  const res = await run(async () => {
    await actionUser(can.manageCustomers);
    const m = await prisma.machineModel.findUnique({ where: { id: modelId } });
    if (!m) throw new UserError("ไม่พบรุ่นเครื่อง");
    await prisma.machineModel.update({ where: { id: modelId }, data: { active: !m.active } });
  });
  if (res.ok) revalidatePath("/models");
  return res;
}
