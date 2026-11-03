// Central definitions for the categorical string fields in the schema.

export const ROLES = ["MANAGER", "SUPERVISOR", "ADMIN", "TECHNICIAN"] as const;
export type Role = (typeof ROLES)[number];
export const ROLE_LABEL: Record<Role, string> = {
  MANAGER: "ผู้จัดการ",
  SUPERVISOR: "หัวหน้าช่าง",
  ADMIN: "แอดมิน",
  TECHNICIAN: "ช่าง",
};
export const ROLE_HINT: Record<Role, string> = {
  MANAGER: "ทำได้ทุกอย่าง รวมถึงจัดการบัญชีพนักงาน",
  SUPERVISOR: "จ่ายงานให้ช่างตามความถนัด, ตั้งค่าความถนัด, รับงานเองได้",
  ADMIN: "ลงงาน (ลูกค้า เครื่อง สถานที่ วันเวลา), จัดการข้อมูลลูกค้า",
  TECHNICIAN: "เห็นเฉพาะงานของตัวเองบนมือถือ, เช็คอิน, เขียนสรุปงาน",
};

export const JOB_STATUS = ["NEW", "ASSIGNED", "IN_PROGRESS", "ON_HOLD", "COMPLETED", "CANCELLED"] as const;
export type JobStatus = (typeof JOB_STATUS)[number];
export const JOB_STATUS_LABEL: Record<JobStatus, string> = {
  NEW: "รอจ่ายงาน",
  ASSIGNED: "จ่ายงานแล้ว",
  IN_PROGRESS: "กำลังทำ",
  ON_HOLD: "Hold (ยังไม่เสร็จ)",
  COMPLETED: "เสร็จแล้ว",
  CANCELLED: "ยกเลิก",
};
export const JOB_STATUS_COLOR: Record<JobStatus, string> = {
  NEW: "bg-amber-100 text-amber-800",
  ASSIGNED: "bg-sky-100 text-sky-800",
  IN_PROGRESS: "bg-indigo-100 text-indigo-800",
  ON_HOLD: "bg-orange-100 text-orange-800",
  COMPLETED: "bg-emerald-100 text-emerald-800",
  CANCELLED: "bg-gray-200 text-gray-600",
};
/** Jobs that still need someone to do something. */
export const OPEN_JOB_STATUS: JobStatus[] = ["NEW", "ASSIGNED", "IN_PROGRESS", "ON_HOLD"];
/** Jobs a technician can check in to. */
export const WORKABLE_JOB_STATUS: JobStatus[] = ["ASSIGNED", "IN_PROGRESS", "ON_HOLD"];

export const JOB_TYPE = ["REPAIR", "PM", "INSTALL", "INSPECTION", "OTHER"] as const;
export const JOB_TYPE_LABEL: Record<(typeof JOB_TYPE)[number], string> = {
  REPAIR: "ซ่อม",
  PM: "บำรุงรักษา (PM)",
  INSTALL: "ติดตั้ง",
  INSPECTION: "ตรวจเช็ค",
  OTHER: "อื่นๆ",
};

export const PRIORITY = ["LOW", "NORMAL", "HIGH", "URGENT"] as const;
export const PRIORITY_LABEL: Record<(typeof PRIORITY)[number], string> = {
  LOW: "ต่ำ",
  NORMAL: "ปกติ",
  HIGH: "สูง",
  URGENT: "ด่วนมาก",
};
export const PRIORITY_COLOR: Record<(typeof PRIORITY)[number], string> = {
  LOW: "bg-gray-100 text-gray-600",
  NORMAL: "bg-sky-50 text-sky-700",
  HIGH: "bg-orange-100 text-orange-800",
  URGENT: "bg-red-100 text-red-700",
};

export const CUSTOMER_TYPE = ["HOSPITAL", "CLINIC", "LAB", "COMPANY", "GOVERNMENT", "OTHER"] as const;
export const CUSTOMER_TYPE_LABEL: Record<(typeof CUSTOMER_TYPE)[number], string> = {
  HOSPITAL: "โรงพยาบาล",
  CLINIC: "คลินิก",
  LAB: "แล็บ / ห้องปฏิบัติการ",
  COMPANY: "บริษัท / โรงงาน",
  GOVERNMENT: "หน่วยงานรัฐ",
  OTHER: "อื่นๆ",
};

export const MACHINE_STATUS = ["ACTIVE", "INACTIVE"] as const;
export const MACHINE_STATUS_LABEL: Record<(typeof MACHINE_STATUS)[number], string> = {
  ACTIVE: "ใช้งานอยู่",
  INACTIVE: "เลิกใช้/ย้ายออก",
};

export const SKILL_LEVELS = [1, 2, 3, 4, 5] as const;
export const SKILL_LABEL: Record<number, string> = {
  1: "พอทำได้",
  2: "ทำได้ (มีคนช่วย)",
  3: "ทำได้เอง",
  4: "ชำนาญ",
  5: "เชี่ยวชาญ/สอนได้",
};

export const VISIT_OUTCOME = ["HOLD", "COMPLETED"] as const;

export const EVENT_LABEL: Record<string, string> = {
  CREATED: "ลงงาน",
  UPDATED: "แก้ไขงาน",
  ASSIGNED: "จ่ายงาน",
  UNASSIGNED: "ถอนช่าง",
  CHECK_IN: "เช็คอินหน้างาน",
  HOLD: "Hold",
  COMPLETED: "ส่งงาน",
  RESCHEDULED: "เลื่อนนัด",
  CANCELLED: "ยกเลิก",
  REOPENED: "เปิดงานใหม่",
};

export const PING_SOURCE_LABEL: Record<string, string> = {
  TRACKING: "แอปบนเว็บ",
  BACKGROUND_APP: "แอปติดตามเบื้องหลัง",
  SHIFT_START: "เริ่มงาน",
  SHIFT_END: "เลิกงาน",
  CHECK_IN: "เช็คอินหน้างาน",
  CHECK_OUT: "ส่งรายงาน",
};

export const MIN_PASSWORD = 8;
