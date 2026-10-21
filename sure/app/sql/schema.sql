-- AMN SURE — โครงสร้างฐานข้อมูล (MySQL 5.7+ / MariaDB 10.3+)
-- ตารางธุรกิจ 8 ตาราง + รายการตัวเลือก/ค่าตั้งต้น ตามสเปกหัวข้อ 2 และ 9
-- ฟิลด์ "คำนวณ/ดึงอัตโนมัติ" ไม่เก็บเป็นคอลัมน์ — คำนวณตอนอ่าน (app/lib/compute.php)
-- ชื่อคอลัมน์ = field_key ในสเปก ยกเว้นคำสงวนของ MySQL: by → by_name, usage → usage_info, close → close_date
-- ทุกตารางมี audit fields: created_at, updated_at, created_by, updated_by

-- ---------------------------------------------------------------- infrastructure (บัญชีผู้ใช้และสิทธิ์)

CREATE TABLE users (
  id CHAR(36) NOT NULL PRIMARY KEY,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(190) NULL,
  name VARCHAR(190) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'EMPLOYEE',          -- MANAGER | EMPLOYEE
  active TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  session_version INT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  activated_at DATETIME NULL,                            -- ตั้งรหัสผ่านผ่านลิงก์ในอีเมลแล้ว (NULL = รอเปิดใช้งาน)
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สิทธิ์ต่อโมดูลของแต่ละผู้ใช้ (canView / canEdit) — ระบบนี้มีโมดูลเดียวคือ SURE
CREATE TABLE user_permissions (
  user_id CHAR(36) NOT NULL,
  module VARCHAR(20) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  PRIMARY KEY (user_id, module),
  CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- บันทึกการล็อกอิน ใช้จำกัดการเดารหัสผ่าน
CREATE TABLE login_attempts (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_login_attempts_login (login, created_at),
  KEY ix_login_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ลิงก์เปิดใช้งาน (purpose = activate) และรหัสยืนยันเข้าสู่ระบบทางอีเมล (purpose = login)
-- เก็บเฉพาะค่า hash ของลิงก์/รหัส ไม่เก็บตัวจริง
CREATE TABLE auth_tokens (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  purpose VARCHAR(20) NOT NULL,
  secret_hash CHAR(64) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME(6) NOT NULL,
  KEY ix_auth_tokens_user (user_id, purpose, created_at),
  KEY ix_auth_tokens_hash (secret_hash),
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- L: รายการตัวเลือก + ค่าตั้งต้น

CREATE TABLE sure_options (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  list_key VARCHAR(20) NOT NULL,
  value VARCHAR(255) NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  is_system TINYINT(1) NOT NULL DEFAULT 0,               -- คำที่ logic ใช้: ลบ/เปลี่ยนชื่อไม่ได้
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  UNIQUE KEY uq_options (list_key, value),
  KEY ix_options_list (list_key, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sure_settings (
  setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- C: ลูกค้า

CREATE TABLE sure_customers (
  id VARCHAR(7) NOT NULL PRIMARY KEY,                    -- รหัสลูกค้า SURE001
  since DATE NOT NULL,
  name VARCHAR(255) NOT NULL,
  ctype VARCHAR(255) NOT NULL,
  legal VARCHAR(255) NULL,
  taxid VARCHAR(13) NULL,
  contact VARCHAR(255) NOT NULL,
  pos VARCHAR(255) NULL,
  addr VARCHAR(255) NULL,
  subd VARCHAR(255) NULL,
  dist VARCHAR(255) NULL,
  prov VARCHAR(255) NOT NULL,
  zip VARCHAR(255) NULL,
  phone VARCHAR(255) NOT NULL,
  phone2 VARCHAR(255) NULL,
  line VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  social VARCHAR(500) NULL,
  docs VARCHAR(500) NULL,
  source VARCHAR(255) NULL,
  amn VARCHAR(255) NULL,
  sales VARCHAR(255) NULL,
  status VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- B: สาขา

CREATE TABLE sure_branches (
  id VARCHAR(10) NOT NULL PRIMARY KEY,                   -- รหัสสาขา SURE001-01
  cust VARCHAR(7) NOT NULL,
  bname VARCHAR(255) NOT NULL,
  taxbranch VARCHAR(255) NULL,
  since DATE NOT NULL,
  addr VARCHAR(255) NOT NULL,
  subd VARCHAR(255) NULL,
  dist VARCHAR(255) NULL,
  prov VARCHAR(255) NOT NULL,
  zip VARCHAR(255) NULL,
  maps VARCHAR(500) NULL,
  contact VARCHAR(255) NULL,
  phone VARCHAR(255) NULL,
  line VARCHAR(255) NULL,
  hours VARCHAR(255) NULL,
  status VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  KEY ix_branches_cust (cust),
  CONSTRAINT fk_branches_cust FOREIGN KEY (cust) REFERENCES sure_customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- D: เครื่อง

CREATE TABLE sure_devices (
  id VARCHAR(9) NOT NULL PRIMARY KEY,                    -- รหัสเครื่อง SURE26001
  regdate DATE NOT NULL,
  cat VARCHAR(255) NOT NULL,
  brand VARCHAR(255) NOT NULL,
  model VARCHAR(255) NOT NULL,
  serial VARCHAR(255) NOT NULL,
  year INT NULL,
  fda VARCHAR(255) NULL,
  acc VARCHAR(500) NULL,
  usage_info VARCHAR(255) NULL,                          -- field_key: usage
  grade VARCHAR(255) NULL,
  photos VARCHAR(500) NULL,
  acq VARCHAR(255) NOT NULL,
  seller VARCHAR(7) NULL,
  sourcer VARCHAR(255) NULL,
  inspect DATE NULL,
  evalform VARCHAR(255) NULL,
  approver VARCHAR(255) NULL,
  buyform VARCHAR(255) NULL,
  listprice DECIMAL(14,2) NULL,
  priceform VARCHAR(255) NULL,
  stock VARCHAR(255) NULL,
  loc VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  UNIQUE KEY uq_devices_serial (serial),
  KEY ix_devices_seller (seller),
  CONSTRAINT fk_devices_seller FOREIGN KEY (seller) REFERENCES sure_customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- A: ติดตามลูกค้า (id อัตโนมัติ บันทึกต่อท้าย)

CREATE TABLE sure_followups (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  cust VARCHAR(7) NOT NULL,
  branch VARCHAR(10) NULL,
  channel VARCHAR(255) NULL,
  topic VARCHAR(255) NOT NULL,
  interest VARCHAR(255) NULL,
  model VARCHAR(255) NULL,
  budget DECIMAL(14,2) NULL,
  dev VARCHAR(9) NULL,
  detail TEXT NULL,
  result VARCHAR(255) NOT NULL,
  nextdate DATE NULL,
  by_name VARCHAR(255) NOT NULL,                         -- field_key: by
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  KEY ix_followups_cust (cust),
  KEY ix_followups_branch (branch),
  KEY ix_followups_dev (dev),
  CONSTRAINT fk_followups_cust FOREIGN KEY (cust) REFERENCES sure_customers (id),
  CONSTRAINT fk_followups_branch FOREIGN KEY (branch) REFERENCES sure_branches (id),
  CONSTRAINT fk_followups_dev FOREIGN KEY (dev) REFERENCES sure_devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- S: การขาย (1 แถว = 1 เครื่อง)

CREATE TABLE sure_sales (
  id VARCHAR(7) NOT NULL PRIMARY KEY,                    -- เลขที่การขาย SO26001
  date DATE NOT NULL,
  status VARCHAR(255) NOT NULL,
  dev VARCHAR(9) NOT NULL,
  cust VARCHAR(7) NOT NULL,
  branch VARCHAR(10) NOT NULL,
  channel VARCHAR(255) NOT NULL,
  agent VARCHAR(255) NULL,
  sales VARCHAR(255) NOT NULL,
  docno VARCHAR(255) NULL,
  price DECIMAL(14,2) NOT NULL,
  terms VARCHAR(255) NULL,
  plandate DATE NULL,
  deliv DATE NULL,
  eng VARCHAR(255) NULL,
  moveform VARCHAR(255) NULL,
  trained VARCHAR(255) NULL,
  wmonths INT NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  KEY ix_sales_dev (dev),
  KEY ix_sales_cust (cust),
  KEY ix_sales_branch (branch),
  CONSTRAINT fk_sales_dev FOREIGN KEY (dev) REFERENCES sure_devices (id),
  CONSTRAINT fk_sales_cust FOREIGN KEY (cust) REFERENCES sure_customers (id),
  CONSTRAINT fk_sales_branch FOREIGN KEY (branch) REFERENCES sure_branches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- P: รับชำระเงิน

CREATE TABLE sure_payments (
  receipt VARCHAR(50) NOT NULL PRIMARY KEY,              -- เลขที่ใบเสร็จ / ใบกำกับภาษี (ตามเอกสารจริง)
  date DATE NOT NULL,
  so VARCHAR(7) NOT NULL,
  type VARCHAR(255) NOT NULL,
  method VARCHAR(255) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,                         -- ติดลบได้ (คืนเงิน)
  ref VARCHAR(255) NULL,
  by_name VARCHAR(255) NOT NULL,                         -- field_key: by
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  KEY ix_payments_so (so),
  CONSTRAINT fk_payments_so FOREIGN KEY (so) REFERENCES sure_sales (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- V: บริการ

CREATE TABLE sure_services (
  id VARCHAR(7) NOT NULL PRIMARY KEY,                    -- เลขที่งานบริการ SV26001
  date DATE NOT NULL,
  dev VARCHAR(9) NOT NULL,
  type VARCHAR(255) NOT NULL,
  detail TEXT NULL,
  eng VARCHAR(255) NOT NULL,
  appt DATE NULL,
  close_date DATE NULL,                                  -- field_key: close
  jstatus VARCHAR(255) NOT NULL,
  fee DECIMAL(14,2) NULL,
  payst VARCHAR(255) NULL,
  maend DATE NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  KEY ix_services_dev (dev),
  CONSTRAINT fk_services_dev FOREIGN KEY (dev) REFERENCES sure_devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- T: ต้นทุนเครื่อง (MANAGER เท่านั้น, 1 เครื่อง = 1 แถว)

CREATE TABLE sure_costs (
  dev VARCHAR(9) NOT NULL PRIMARY KEY,
  cost DECIMAL(14,2) NOT NULL,
  refurb DECIMAL(14,2) NULL,
  other DECIMAL(14,2) NULL,
  paidseller DATE NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  created_by CHAR(36) NULL,
  updated_by CHAR(36) NULL,
  CONSTRAINT fk_costs_dev FOREIGN KEY (dev) REFERENCES sure_devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
