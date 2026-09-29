-- AMN Sure CRM v1 — database schema (MySQL 5.7+ / MariaDB 10.3+)
-- ตาราง 37 ตารางตามสเปก + 6 ตารางเสริม (ดู docs/crm-v1/01-erd-schema.md)
-- ทุก id เป็น UUID (CHAR 36); เลขอ้างอิงที่คนอ่านเก็บใน ref_no แยก
-- ห้ามลบข้อมูลการเงิน/อนุมัติ/ธุรกรรมจริง: ใช้ archived_at / voided_at แทน

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- สิทธิ์ผู้ใช้

CREATE TABLE users (
  id CHAR(36) NOT NULL PRIMARY KEY,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  session_version INT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id CHAR(36) NOT NULL,
  role_id CHAR(36) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ลูกค้า

CREATE TABLE organizations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'CLINIC',
  tax_id VARCHAR(20) NULL,
  branch VARCHAR(100) NULL,
  phone VARCHAR(50) NULL,
  line_id VARCHAR(100) NULL,
  email VARCHAR(190) NULL,
  address TEXT NULL,
  province VARCHAR(100) NULL,
  account_owner_id CHAR(36) NULL,
  notes TEXT NULL,
  archived_at DATETIME NULL, archived_by CHAR(36) NULL, archive_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_org_ref (ref_no),
  KEY ix_org_name (name(100)),
  KEY ix_org_phone (phone),
  CONSTRAINT fk_org_owner FOREIGN KEY (account_owner_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
  id CHAR(36) NOT NULL PRIMARY KEY,
  organization_id CHAR(36) NULL,
  name VARCHAR(150) NOT NULL,
  position VARCHAR(100) NULL,
  phone VARCHAR(50) NULL,
  line_id VARCHAR(100) NULL,
  email VARCHAR(190) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  archived_at DATETIME NULL, archived_by CHAR(36) NULL, archive_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_contact_org (organization_id),
  KEY ix_contact_name (name),
  KEY ix_contact_phone (phone),
  CONSTRAINT fk_contact_org FOREIGN KEY (organization_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ลีด = หัวเรื่องการติดต่อ (ผู้ขาย/ผู้ซื้อ) งานติดตามจริงอยู่ที่ดีลซื้อ/ดีลขายภายใต้ลีด
CREATE TABLE leads (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  type VARCHAR(10) NOT NULL,
  organization_id CHAR(36) NOT NULL,
  contact_id CHAR(36) NULL,
  source VARCHAR(80) NULL,
  owner_id CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  summary TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_lead_ref (ref_no),
  KEY ix_lead_org (organization_id),
  KEY ix_lead_owner (owner_id, status),
  CONSTRAINT fk_lead_org FOREIGN KEY (organization_id) REFERENCES organizations (id),
  CONSTRAINT fk_lead_contact FOREIGN KEY (contact_id) REFERENCES contacts (id),
  CONSTRAINT fk_lead_owner FOREIGN KEY (owner_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- เครื่อง

CREATE TABLE devices (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  brand VARCHAR(100) NOT NULL,
  model VARCHAR(150) NOT NULL,
  category VARCHAR(100) NULL,
  serial_number VARCHAR(120) NULL,
  serial_normalized VARCHAR(120) NULL,
  serial_missing_reason VARCHAR(255) NULL,
  manufacture_year SMALLINT NULL,
  installation_year SMALLINT NULL,
  owned_by_amn TINYINT(1) NOT NULL DEFAULT 0,
  current_owner_org_id CHAR(36) NULL,
  current_location VARCHAR(255) NULL,
  usage_value DECIMAL(14,2) NULL,
  usage_unit VARCHAR(10) NULL,
  usage_recorded_at DATE NULL,
  commercial_status VARCHAR(30) NOT NULL DEFAULT 'EXTERNAL',
  technical_status VARCHAR(30) NOT NULL DEFAULT 'UNKNOWN',
  accessories TEXT NULL,
  notes TEXT NULL,
  archived_at DATETIME NULL, archived_by CHAR(36) NULL, archive_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_dev_ref (ref_no),
  UNIQUE KEY uq_dev_serial (serial_normalized),
  KEY ix_dev_brand_model (brand, model),
  KEY ix_dev_status (commercial_status),
  CONSTRAINT fk_dev_owner FOREIGN KEY (current_owner_org_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_ownerships (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_id CHAR(36) NOT NULL,
  organization_id CHAR(36) NULL,
  owned_by_amn TINYINT(1) NOT NULL DEFAULT 0,
  from_date DATE NOT NULL,
  to_date DATE NULL,
  source VARCHAR(20) NOT NULL,
  acquisition_id CHAR(36) NULL,
  sales_transaction_id CHAR(36) NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_own_device (device_id, from_date),
  CONSTRAINT fk_own_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_own_org FOREIGN KEY (organization_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_opportunities (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  device_id CHAR(36) NOT NULL,
  lead_id CHAR(36) NOT NULL,
  seller_org_id CHAR(36) NOT NULL,
  seller_contact_id CHAR(36) NULL,
  owner_id CHAR(36) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'NEW_SELLER_LEAD',
  asking_price DECIMAL(14,2) NULL,
  expected_price DECIMAL(14,2) NULL,
  reason_for_sale VARCHAR(255) NULL,
  location VARCHAR(255) NULL,
  final_negotiated_price DECIMAL(14,2) NULL,
  next_action VARCHAR(255) NULL,
  next_follow_up_date DATE NULL,
  lost_reason VARCHAR(255) NULL,
  closed_at DATETIME NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_do_ref (ref_no),
  KEY ix_do_device (device_id),
  KEY ix_do_status (status),
  KEY ix_do_followup (owner_id, next_follow_up_date),
  CONSTRAINT fk_do_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_do_lead FOREIGN KEY (lead_id) REFERENCES leads (id),
  CONSTRAINT fk_do_seller FOREIGN KEY (seller_org_id) REFERENCES organizations (id),
  CONSTRAINT fk_do_contact FOREIGN KEY (seller_contact_id) REFERENCES contacts (id),
  CONSTRAINT fk_do_owner FOREIGN KEY (owner_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspections (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_id CHAR(36) NOT NULL,
  device_opportunity_id CHAR(36) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
  requested_by CHAR(36) NULL,
  requested_at DATETIME(6) NOT NULL,
  preferred_date DATE NULL,
  request_note TEXT NULL,
  engineer_id CHAR(36) NULL,
  started_at DATETIME(6) NULL,
  completed_at DATETIME(6) NULL,
  overall_result VARCHAR(20) NULL,
  overall_condition VARCHAR(20) NULL,
  usage_reading VARCHAR(100) NULL,
  issues TEXT NULL,
  required_repair TEXT NULL,
  recommended_repair TEXT NULL,
  missing_accessories TEXT NULL,
  technical_risk VARCHAR(10) NULL,
  est_repair_days INT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_insp_device (device_id),
  KEY ix_insp_opp (device_opportunity_id),
  KEY ix_insp_status (status),
  CONSTRAINT fk_insp_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_insp_opp FOREIGN KEY (device_opportunity_id) REFERENCES device_opportunities (id),
  CONSTRAINT fk_insp_engineer FOREIGN KEY (engineer_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inspection_items (
  id CHAR(36) NOT NULL PRIMARY KEY,
  inspection_id CHAR(36) NOT NULL,
  category VARCHAR(30) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  result VARCHAR(20) NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_ii_insp (inspection_id, sort),
  CONSTRAINT fk_ii_insp FOREIGN KEY (inspection_id) REFERENCES inspections (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_service_history (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_id CHAR(36) NOT NULL,
  service_date DATE NOT NULL,
  type VARCHAR(30) NOT NULL,
  description TEXT NOT NULL,
  parts_replaced TEXT NULL,
  performed_by VARCHAR(150) NULL,
  cost DECIMAL(14,2) NULL,
  is_repeat_failure TINYINT(1) NOT NULL DEFAULT 0,
  source VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
  technical_job_id CHAR(36) NULL,
  archived_at DATETIME NULL, archived_by CHAR(36) NULL, archive_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_dsh_device (device_id, service_date),
  CONSTRAINT fk_dsh_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ma_records (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_id CHAR(36) NOT NULL,
  provider VARCHAR(150) NOT NULL,
  contract_no VARCHAR(100) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  coverage TEXT NULL,
  cost DECIMAL(14,2) NULL,
  notes TEXT NULL,
  archived_at DATETIME NULL, archived_by CHAR(36) NULL, archive_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_ma_device (device_id, end_date),
  CONSTRAINT fk_ma_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cost_sheets (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_opportunity_id CHAR(36) NOT NULL,
  inspection_id CHAR(36) NOT NULL,
  version_no INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  total_estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  submitted_at DATETIME NULL,
  submitted_by CHAR(36) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_cs_version (device_opportunity_id, version_no),
  CONSTRAINT fk_cs_opp FOREIGN KEY (device_opportunity_id) REFERENCES device_opportunities (id),
  CONSTRAINT fk_cs_insp FOREIGN KEY (inspection_id) REFERENCES inspections (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cost_sheet_items (
  id CHAR(36) NOT NULL PRIMARY KEY,
  cost_sheet_id CHAR(36) NOT NULL,
  category VARCHAR(30) NOT NULL,
  description VARCHAR(255) NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_csi_sheet (cost_sheet_id, sort),
  CONSTRAINT fk_csi_sheet FOREIGN KEY (cost_sheet_id) REFERENCES cost_sheets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BR-06: valuation เป็น record แยก อ้างอิง cost sheet และไม่เขียนทับ
CREATE TABLE valuations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_opportunity_id CHAR(36) NOT NULL,
  cost_sheet_id CHAR(36) NOT NULL,
  asking_price DECIMAL(14,2) NULL,
  total_cost_snapshot DECIMAL(14,2) NOT NULL,
  acquisition_assumption_snapshot DECIMAL(14,2) NOT NULL DEFAULT 0,
  market_selling_price DECIMAL(14,2) NULL,
  historical_selling_price DECIMAL(14,2) NULL,
  device_condition VARCHAR(20) NULL,
  age_years DECIMAL(5,1) NULL,
  usage_note VARCHAR(150) NULL,
  demand VARCHAR(10) NULL,
  technical_risk VARCHAR(10) NULL,
  expected_days_to_sell INT NULL,
  fair_market_value DECIMAL(14,2) NOT NULL,
  recommended_acq_price DECIMAL(14,2) NOT NULL,
  max_acq_price DECIMAL(14,2) NOT NULL,
  target_selling_price DECIMAL(14,2) NOT NULL,
  min_selling_price DECIMAL(14,2) NOT NULL,
  expected_gp DECIMAL(14,2) NOT NULL,
  expected_gp_margin DECIMAL(9,4) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_val_opp (device_opportunity_id, created_at),
  CONSTRAINT fk_val_opp FOREIGN KEY (device_opportunity_id) REFERENCES device_opportunities (id),
  CONSTRAINT fk_val_cs FOREIGN KEY (cost_sheet_id) REFERENCES cost_sheets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE negotiations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  device_opportunity_id CHAR(36) NOT NULL,
  party VARCHAR(20) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  offered_at DATETIME(6) NOT NULL,
  user_id CHAR(36) NOT NULL,
  note TEXT NULL,
  is_final TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_neg_opp (device_opportunity_id, offered_at),
  CONSTRAINT fk_neg_opp FOREIGN KEY (device_opportunity_id) REFERENCES device_opportunities (id),
  CONSTRAINT fk_neg_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approvals (
  id CHAR(36) NOT NULL PRIMARY KEY,
  subject_type VARCHAR(30) NOT NULL,
  subject_id CHAR(36) NOT NULL,
  requested_by CHAR(36) NOT NULL,
  requested_at DATETIME(6) NOT NULL,
  request_note TEXT NULL,
  decision VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  approver_id CHAR(36) NULL,
  decided_at DATETIME(6) NULL,
  condition_text TEXT NULL,
  comment TEXT NULL,
  approved_amount DECIMAL(14,2) NULL,
  return_to VARCHAR(20) NULL,
  package_snapshot LONGTEXT NOT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_appr_subject (subject_type, subject_id),
  KEY ix_appr_decision (decision),
  CONSTRAINT fk_appr_requester FOREIGN KEY (requested_by) REFERENCES users (id),
  CONSTRAINT fk_appr_approver FOREIGN KEY (approver_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE acquisitions (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  device_opportunity_id CHAR(36) NOT NULL,
  approval_id CHAR(36) NOT NULL,
  device_id CHAR(36) NOT NULL,
  seller_org_id CHAR(36) NOT NULL,
  purchase_price DECIMAL(14,2) NOT NULL,
  purchase_date DATE NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'UNPAID',
  takes_stock TINYINT(1) NOT NULL DEFAULT 1,
  conditions_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  voided_at DATETIME NULL, voided_by CHAR(36) NULL, void_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_acq_ref (ref_no),
  UNIQUE KEY uq_acq_opp (device_opportunity_id),
  KEY ix_acq_device (device_id),
  CONSTRAINT fk_acq_opp FOREIGN KEY (device_opportunity_id) REFERENCES device_opportunities (id),
  CONSTRAINT fk_acq_appr FOREIGN KEY (approval_id) REFERENCES approvals (id),
  CONSTRAINT fk_acq_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_acq_seller FOREIGN KEY (seller_org_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  device_id CHAR(36) NOT NULL,
  acquisition_id CHAR(36) NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'ACQUISITION',
  received_date DATE NOT NULL,
  storage_location VARCHAR(150) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'IN_STOCK',
  book_cost DECIMAL(14,2) NULL,
  list_price DECIMAL(14,2) NULL,
  reserved_for_transaction_id CHAR(36) NULL,
  notes TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_inv_ref (ref_no),
  KEY ix_inv_device (device_id),
  KEY ix_inv_status (status),
  CONSTRAINT fk_inv_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_inv_acq FOREIGN KEY (acquisition_id) REFERENCES acquisitions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- การขาย

CREATE TABLE sales_opportunities (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  lead_id CHAR(36) NOT NULL,
  buyer_org_id CHAR(36) NOT NULL,
  contact_id CHAR(36) NULL,
  owner_id CHAR(36) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'NEW_BUYER_LEAD',
  interested_device VARCHAR(255) NULL,
  req_brand VARCHAR(100) NULL,
  req_model VARCHAR(150) NULL,
  req_technology VARCHAR(150) NULL,
  budget_min DECIMAL(14,2) NULL,
  budget_max DECIMAL(14,2) NULL,
  preferred_condition VARCHAR(20) NULL,
  accessories_required TEXT NULL,
  warranty_required VARCHAR(150) NULL,
  installation_required TINYINT(1) NOT NULL DEFAULT 1,
  expected_purchase_date DATE NULL,
  timeline VARCHAR(150) NULL,
  requirement_note TEXT NULL,
  requirement_defined_at DATETIME NULL,
  requirement_by CHAR(36) NULL,
  next_action VARCHAR(255) NULL,
  next_follow_up_date DATE NULL,
  lost_reason VARCHAR(255) NULL,
  closed_at DATETIME NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_so_ref (ref_no),
  KEY ix_so_status (status),
  KEY ix_so_followup (owner_id, next_follow_up_date),
  CONSTRAINT fk_so_lead FOREIGN KEY (lead_id) REFERENCES leads (id),
  CONSTRAINT fk_so_buyer FOREIGN KEY (buyer_org_id) REFERENCES organizations (id),
  CONSTRAINT fk_so_contact FOREIGN KEY (contact_id) REFERENCES contacts (id),
  CONSTRAINT fk_so_owner FOREIGN KEY (owner_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BR-09: จับคู่กับของในสต็อก (inventory_id) หรือเครื่องที่ผู้ขายกำลังเสนอ (device_opportunity_id)
CREATE TABLE device_matches (
  id CHAR(36) NOT NULL PRIMARY KEY,
  sales_opportunity_id CHAR(36) NOT NULL,
  device_id CHAR(36) NOT NULL,
  source VARCHAR(20) NOT NULL,
  inventory_id CHAR(36) NULL,
  device_opportunity_id CHAR(36) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_match (sales_opportunity_id, device_id),
  CONSTRAINT fk_match_so FOREIGN KEY (sales_opportunity_id) REFERENCES sales_opportunities (id),
  CONSTRAINT fk_match_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_match_inv FOREIGN KEY (inventory_id) REFERENCES inventory (id),
  CONSTRAINT fk_match_do FOREIGN KEY (device_opportunity_id) REFERENCES device_opportunities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  sales_opportunity_id CHAR(36) NOT NULL,
  current_version_no INT NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_qtn_ref (ref_no),
  KEY ix_qtn_so (sales_opportunity_id),
  CONSTRAINT fk_qtn_so FOREIGN KEY (sales_opportunity_id) REFERENCES sales_opportunities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BR-10: แก้ราคา = สร้าง version ใหม่ version เก่าเป็นอ่านอย่างเดียว
CREATE TABLE quotation_versions (
  id CHAR(36) NOT NULL PRIMARY KEY,
  quotation_id CHAR(36) NOT NULL,
  version_no INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 7.00,
  vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  warranty_terms TEXT NULL,
  payment_terms TEXT NULL,
  delivery_terms TEXT NULL,
  installation_terms TEXT NULL,
  valid_until DATE NULL,
  notes TEXT NULL,
  approval_requested_at DATETIME NULL,
  approval_requested_by CHAR(36) NULL,
  approved_by CHAR(36) NULL,
  approved_at DATETIME NULL,
  sent_at DATETIME NULL,
  responded_at DATETIME NULL,
  response_note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_qv (quotation_id, version_no),
  KEY ix_qv_status (status),
  CONSTRAINT fk_qv_qtn FOREIGN KEY (quotation_id) REFERENCES quotations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotation_version_lines (
  id CHAR(36) NOT NULL PRIMARY KEY,
  quotation_version_id CHAR(36) NOT NULL,
  device_id CHAR(36) NULL,
  description VARCHAR(255) NOT NULL,
  device_condition VARCHAR(20) NULL,
  accessories TEXT NULL,
  qty INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_qvl_version (quotation_version_id, sort),
  CONSTRAINT fk_qvl_version FOREIGN KEY (quotation_version_id) REFERENCES quotation_versions (id),
  CONSTRAINT fk_qvl_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deposits (
  id CHAR(36) NOT NULL PRIMARY KEY,
  sales_opportunity_id CHAR(36) NOT NULL,
  quotation_version_id CHAR(36) NULL,
  required_amount DECIMAL(14,2) NOT NULL,
  due_date DATE NULL,
  received_amount DECIMAL(14,2) NULL,
  received_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'WAITING_DEPOSIT',
  verified_by CHAR(36) NULL,
  verified_at DATETIME NULL,
  note TEXT NULL,
  status_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_dep_so (sales_opportunity_id),
  CONSTRAINT fk_dep_so FOREIGN KEY (sales_opportunity_id) REFERENCES sales_opportunities (id),
  CONSTRAINT fk_dep_qv FOREIGN KEY (quotation_version_id) REFERENCES quotation_versions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contracts (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  sales_opportunity_id CHAR(36) NOT NULL,
  quotation_version_id CHAR(36) NULL,
  contract_no VARCHAR(100) NOT NULL,
  price DECIMAL(14,2) NOT NULL,
  payment_terms TEXT NULL,
  warranty_terms TEXT NULL,
  delivery_terms TEXT NULL,
  installation_terms TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  signed_date DATE NULL,
  voided_at DATETIME NULL, voided_by CHAR(36) NULL, void_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_ctr_ref (ref_no),
  KEY ix_ctr_so (sales_opportunity_id),
  CONSTRAINT fk_ctr_so FOREIGN KEY (sales_opportunity_id) REFERENCES sales_opportunities (id),
  CONSTRAINT fk_ctr_qv FOREIGN KEY (quotation_version_id) REFERENCES quotation_versions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_transactions (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  sales_opportunity_id CHAR(36) NOT NULL,
  quotation_version_id CHAR(36) NOT NULL,
  contract_id CHAR(36) NULL,
  buyer_org_id CHAR(36) NOT NULL,
  total_price DECIMAL(14,2) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'IN_PREPARATION',
  installation_required TINYINT(1) NOT NULL DEFAULT 1,
  won_at DATETIME(6) NOT NULL,
  ready_at DATETIME NULL,
  delivered_at DATETIME NULL,
  completed_at DATETIME(6) NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_trx_ref (ref_no),
  UNIQUE KEY uq_trx_so (sales_opportunity_id),
  KEY ix_trx_status (status),
  CONSTRAINT fk_trx_so FOREIGN KEY (sales_opportunity_id) REFERENCES sales_opportunities (id),
  CONSTRAINT fk_trx_qv FOREIGN KEY (quotation_version_id) REFERENCES quotation_versions (id),
  CONSTRAINT fk_trx_ctr FOREIGN KEY (contract_id) REFERENCES contracts (id),
  CONSTRAINT fk_trx_buyer FOREIGN KEY (buyer_org_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklists (
  id CHAR(36) NOT NULL PRIMARY KEY,
  sales_transaction_id CHAR(36) NOT NULL,
  section VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  completed_at DATETIME(6) NULL,
  completed_by CHAR(36) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_cl_section (sales_transaction_id, section),
  CONSTRAINT fk_cl_trx FOREIGN KEY (sales_transaction_id) REFERENCES sales_transactions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_items (
  id CHAR(36) NOT NULL PRIMARY KEY,
  checklist_id CHAR(36) NOT NULL,
  label VARCHAR(255) NOT NULL,
  is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  done TINYINT(1) NOT NULL DEFAULT 0,
  done_by CHAR(36) NULL,
  done_at DATETIME NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_cli_cl (checklist_id, sort),
  CONSTRAINT fk_cli_cl FOREIGN KEY (checklist_id) REFERENCES checklists (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qc_records (
  id CHAR(36) NOT NULL PRIMARY KEY,
  sales_transaction_id CHAR(36) NOT NULL,
  device_id CHAR(36) NOT NULL,
  engineer_id CHAR(36) NOT NULL,
  checked_at DATETIME(6) NOT NULL,
  checks_json LONGTEXT NOT NULL,
  overall_result VARCHAR(10) NOT NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_qc_trx (sales_transaction_id, device_id, checked_at),
  CONSTRAINT fk_qc_trx FOREIGN KEY (sales_transaction_id) REFERENCES sales_transactions (id),
  CONSTRAINT fk_qc_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_qc_engineer FOREIGN KEY (engineer_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deliveries (
  id CHAR(36) NOT NULL PRIMARY KEY,
  sales_transaction_id CHAR(36) NOT NULL,
  device_id CHAR(36) NOT NULL,
  delivered_date DATE NOT NULL,
  address TEXT NULL,
  transport_method VARCHAR(100) NULL,
  engineer_id CHAR(36) NULL,
  serial_confirmed VARCHAR(120) NOT NULL,
  accessories_delivered TEXT NULL,
  receiving_person VARCHAR(150) NOT NULL,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_del_trx (sales_transaction_id, device_id),
  CONSTRAINT fk_del_trx FOREIGN KEY (sales_transaction_id) REFERENCES sales_transactions (id),
  CONSTRAINT fk_del_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  sales_transaction_id CHAR(36) NOT NULL,
  device_id CHAR(36) NOT NULL,
  installed_date DATE NOT NULL,
  engineer_id CHAR(36) NULL,
  result VARCHAR(20) NOT NULL,
  system_test_result TEXT NULL,
  customer_accepted TINYINT(1) NOT NULL DEFAULT 0,
  accepted_by_name VARCHAR(150) NULL,
  training_done TINYINT(1) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_ins_trx (sales_transaction_id, device_id),
  CONSTRAINT fk_ins_trx FOREIGN KEY (sales_transaction_id) REFERENCES sales_transactions (id),
  CONSTRAINT fk_ins_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- งานปฏิบัติการ

CREATE TABLE technical_jobs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  type VARCHAR(20) NOT NULL,
  device_id CHAR(36) NULL,
  parent_type VARCHAR(40) NULL,
  parent_id CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  assigned_engineer_id CHAR(36) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  scheduled_date DATE NULL,
  completed_at DATETIME(6) NULL,
  hours DECIMAL(6,2) NULL,
  parts_used TEXT NULL,
  result_note TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_job_ref (ref_no),
  KEY ix_job_parent (parent_type, parent_id),
  KEY ix_job_status (status, assigned_engineer_id),
  CONSTRAINT fk_job_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_job_engineer FOREIGN KEY (assigned_engineer_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_cases (
  id CHAR(36) NOT NULL PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL,
  device_id CHAR(36) NOT NULL,
  organization_id CHAR(36) NULL,
  reported_at DATE NOT NULL,
  issue TEXT NOT NULL,
  under_warranty TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  resolution TEXT NULL,
  assigned_to CHAR(36) NULL,
  closed_at DATETIME NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_sc_ref (ref_no),
  KEY ix_sc_device (device_id),
  CONSTRAINT fk_sc_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_sc_org FOREIGN KEY (organization_id) REFERENCES organizations (id),
  CONSTRAINT fk_sc_user FOREIGN KEY (assigned_to) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
  id CHAR(36) NOT NULL PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'GENERAL',
  parent_type VARCHAR(40) NULL,
  parent_id CHAR(36) NULL,
  assignee_id CHAR(36) NULL,
  assignee_role VARCHAR(40) NULL,
  due_date DATE NULL,
  priority VARCHAR(10) NOT NULL DEFAULT 'MEDIUM',
  status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  completed_at DATETIME(6) NULL,
  completed_by CHAR(36) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_task_assignee (assignee_id, status),
  KEY ix_task_role (assignee_role, status),
  KEY ix_task_parent (parent_type, parent_id),
  CONSTRAINT fk_task_assignee FOREIGN KEY (assignee_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
  id CHAR(36) NOT NULL PRIMARY KEY,
  parent_type VARCHAR(40) NOT NULL,
  parent_id CHAR(36) NOT NULL,
  organization_id CHAR(36) NULL,
  device_id CHAR(36) NULL,
  type VARCHAR(20) NOT NULL,
  occurred_at DATETIME(6) NOT NULL,
  user_id CHAR(36) NULL,
  summary TEXT NOT NULL,
  outcome TEXT NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_act_parent (parent_type, parent_id, occurred_at),
  KEY ix_act_org (organization_id, occurred_at),
  KEY ix_act_device (device_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id CHAR(36) NOT NULL PRIMARY KEY,
  parent_type VARCHAR(40) NOT NULL,
  parent_id CHAR(36) NOT NULL,
  organization_id CHAR(36) NULL,
  device_id CHAR(36) NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'OTHER',
  title VARCHAR(255) NULL,
  file_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NULL,
  size_bytes BIGINT NULL,
  storage_key VARCHAR(255) NULL,
  url VARCHAR(1000) NULL,
  archived_at DATETIME NULL, archived_by CHAR(36) NULL, archive_reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  KEY ix_doc_parent (parent_type, parent_id),
  KEY ix_doc_org (organization_id),
  KEY ix_doc_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เขียนเพิ่มได้อย่างเดียว ไม่มีหน้าจอหรือ service ใดแก้/ลบ
CREATE TABLE audit_logs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  entity_type VARCHAR(40) NOT NULL,
  entity_id CHAR(36) NULL,
  action VARCHAR(30) NOT NULL,
  changes LONGTEXT NULL,
  note VARCHAR(500) NULL,
  user_id CHAR(36) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME(6) NOT NULL,
  KEY ix_audit_entity (entity_type, entity_id, created_at),
  KEY ix_audit_time (created_at),
  KEY ix_audit_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ตารางเสริม

CREATE TABLE ref_counters (
  counter_key VARCHAR(30) NOT NULL PRIMARY KEY,
  last_value INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
  setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NULL,
  updated_by CHAR(36) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE master_data (
  id CHAR(36) NOT NULL PRIMARY KEY,
  type VARCHAR(40) NOT NULL,
  code VARCHAR(80) NOT NULL,
  label VARCHAR(255) NOT NULL,
  meta TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL, created_by CHAR(36) NULL,
  updated_at DATETIME NOT NULL, updated_by CHAR(36) NULL,
  UNIQUE KEY uq_md (type, code),
  KEY ix_md_type (type, active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id CHAR(36) NOT NULL PRIMARY KEY,
  login VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL,
  KEY ix_la_login (login, created_at),
  KEY ix_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
