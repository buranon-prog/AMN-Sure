import type { Role } from "@/lib/constants";

// Single source of truth for "who can do what". Pages and server actions
// both check these — hiding a button is never the only guard.

const is =
  (...roles: Role[]) =>
  (role: string) =>
    (roles as string[]).includes(role);

export const can = {
  /** Office screens: jobs board, customers, machines, dashboard. */
  useOffice: is("MANAGER", "SUPERVISOR", "ADMIN"),
  /** ลงงาน: create/edit/reschedule/cancel jobs. */
  manageJobs: is("MANAGER", "SUPERVISOR", "ADMIN"),
  /** จ่ายงาน: pick technicians for a job. */
  assignJobs: is("MANAGER", "SUPERVISOR"),
  manageCustomers: is("MANAGER", "SUPERVISOR", "ADMIN"),
  manageSkills: is("MANAGER", "SUPERVISOR"),
  viewTracking: is("MANAGER", "SUPERVISOR", "ADMIN"),
  manageEmployees: is("MANAGER"),
  exportData: is("MANAGER", "SUPERVISOR", "ADMIN"),
  /** Location history is personal data — narrower than the live map. */
  exportLocations: is("MANAGER"),
  /** Can be assigned to jobs and use the mobile field screens. */
  doFieldWork: is("SUPERVISOR", "TECHNICIAN"),
};

export function homeFor(role: string) {
  return role === "TECHNICIAN" ? "/m" : "/dashboard";
}

/** Human-readable access matrix, shown to the manager on the employees page. */
export const PERMISSION_MATRIX: { label: string; check: (role: string) => boolean }[] = [
  { label: "ดู Dashboard / งานทั้งหมด / ข้อมูลลูกค้า", check: can.useOffice },
  { label: "ลงงาน แก้ไข เลื่อนนัด ยกเลิกงาน", check: can.manageJobs },
  { label: "จ่ายงานให้ช่าง (เลือกตามความถนัด)", check: can.assignJobs },
  { label: "เพิ่ม/แก้ไข ลูกค้า สถานที่ เครื่อง", check: can.manageCustomers },
  { label: "ตั้งค่าความถนัดของช่าง", check: can.manageSkills },
  { label: "ดูตำแหน่งช่างแบบสด (Tracking)", check: can.viewTracking },
  { label: "Export ข้อมูลงาน/ลูกค้า", check: can.exportData },
  { label: "Export ประวัติตำแหน่ง (ข้อมูลส่วนบุคคล)", check: can.exportLocations },
  { label: "จัดการบัญชีพนักงาน / สิทธิ์ / รีเซ็ตรหัสผ่าน", check: can.manageEmployees },
  { label: "รับงาน: เห็นงานของตัวเองบนมือถือ เช็คอิน เขียนสรุปงาน", check: can.doFieldWork },
];
