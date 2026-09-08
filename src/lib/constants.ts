// Central definitions for the categorical string fields used across the
// schema (kept as plain strings in Prisma for SQLite/Postgres portability).

export const ROLES = ["MANAGER", "EMPLOYEE"] as const;
export type Role = (typeof ROLES)[number];

export const MODULES = [
  "INVENTORY",
  "CONSIGNMENT",
  "FINANCE",
  "HR",
  "SUPPLY_CHAIN",
  "CRM",
  "STRATEGY",
] as const;
export type ModuleName = (typeof MODULES)[number];

export const MODULE_LABELS: Record<ModuleName, string> = {
  INVENTORY: "รับ-จ่ายสินค้า",
  CONSIGNMENT: "สินค้าฝากขาย",
  FINANCE: "การเงิน",
  HR: "บุคคล (HR)",
  SUPPLY_CHAIN: "ซัพพลายเชน / ขนส่ง",
  CRM: "ลูกค้าสัมพันธ์ (CRM)",
  STRATEGY: "กลยุทธ์",
};

export const PRODUCT_CONDITIONS = ["NEW", "USED", "REFURBISHED"] as const;
export const STOCK_SOURCE = ["PURCHASE", "CONSIGNMENT"] as const;
export const STOCK_STATUS = ["IN_STOCK", "SOLD", "RETURNED"] as const;
export const MOVEMENT_TYPE = ["IN", "OUT"] as const;
export const PAYMENT_STATUS = ["PAID", "PENDING", "PARTIAL"] as const;

export const FINANCE_TX_TYPE = ["INCOME", "EXPENSE"] as const;
export const FINANCE_CATEGORY = [
  "SALES",
  "CONSIGNMENT_PAYOUT",
  "PURCHASE",
  "OPERATION_COST",
  "SALARY",
  "OTHER",
] as const;

export const EMPLOYEE_STATUS = ["ACTIVE", "INACTIVE"] as const;
export const ATTENDANCE_STATUS = ["PRESENT", "LATE", "ABSENT", "LEAVE"] as const;
export const KPI_PERIOD = ["MONTHLY", "QUARTERLY", "YEARLY"] as const;
export const PLAN_STATUS = ["PLANNED", "IN_PROGRESS", "COMPLETED", "DELAYED", "CANCELLED"] as const;

export const SHIPMENT_STATUS = [
  "ORDERED",
  "IN_TRANSIT",
  "CUSTOMS",
  "DELIVERED",
  "DELAYED",
  "CANCELLED",
] as const;

export const CUSTOMER_TYPE = ["INDIVIDUAL", "CLINIC", "HOSPITAL", "DEALER"] as const;
export const LOYALTY_TIER = ["BRONZE", "SILVER", "GOLD", "PLATINUM"] as const;
export const INTERACTION_TYPE = [
  "CALL",
  "MEETING",
  "INTEREST",
  "COMPLAINT",
  "PURCHASE_FOLLOWUP",
] as const;

export const PRIORITY = ["LOW", "MEDIUM", "HIGH"] as const;
export const SOURCING_STATUS = ["SEEKING", "NEGOTIATING", "ACQUIRED", "DROPPED"] as const;
export const MARKET_STATUS = ["PLANNED", "IN_PROGRESS", "ACTIVE", "DROPPED"] as const;
