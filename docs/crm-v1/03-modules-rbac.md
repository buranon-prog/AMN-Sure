# 03 — โมดูล, API และสิทธิ์ (RBAC)

## โครงสร้างโค้ด (PHP — `crm/`)

หน้าเว็บ server-rendered ทุกคำขอผ่าน `index.php?r=module.action` ไม่มี REST API แยก

```
crm/
  index.php                   ← front controller: GET → c_{module}_{action}(), POST → c_{module}_post_{action}() + ตรวจ CSRF
  app/lib/
    perm.php                  ← role → capability, can()/require_cap(), redact() ตัดฟิลด์ 🔒
    audit.php                 ← audit_log(), insert_audited(), update_audited() (เก็บค่าเก่า/ใหม่), log_activity()
    db.php                    ← PDO, uuid(), next_ref('DO') → DO-2026-0001, tx() (ซ้อนกันได้), db_lock()
    auth.php                  ← login (ชื่อผู้ใช้/อีเมล), rate limit, session_version, CSRF
    files.php                 ← เก็บไฟล์ใน storage/uploads (ปิดด้วย .htaccess) ดาวน์โหลดผ่านการตรวจสิทธิ์
  app/services/               ← business logic ทั้งหมด: customers, leads, devices, acquisition, sales, fulfillment,
                                 tasks, documents, search, service, admin, users, dashboard, parents
  app/controllers/ app/views/ ← หน้าจอ (ดู 04)
  tests/run.php               ← RBAC + state transitions + Scenario A–E (เรียก service ตรง)
  tests/http_test.php         ← end-to-end ผ่าน HTTP ทุก role
```

ทุก service function มีโครงเหมือนกัน: `require_cap()` → `tx()` → `db_lock()` + ตรวจสถานะต้นทาง →
เขียนข้อมูล + audit + activity + task → ส่งกลับ (หน้าเว็บตัดฟิลด์ 🔒 ด้วย `redact()`/`can('finance.view')`)

## Module map

| โมดูล | Service function หลัก | Capability ที่ต้องมี |
|---|---|---|
| **customers** | createOrganization, updateOrganization, archiveOrganization, createContact, updateContact, getCustomer360 | `customer.view`, `customer.edit` |
| **leads** | createSellerLead (สร้าง lead + device opp + ผูก/สร้าง device), createBuyerLead, updateFollowUp, logActivity, disqualifyLead, listMyFollowUps | `lead.view`, `lead.edit` |
| **devices** | findDuplicates, createDevice, updateDevice, getDevice360, addServiceHistory, addMaRecord | `device.view`, `device.edit`, `service_history.edit` |
| **acquisition** | requestInspection, startInspection, saveInspectionItems, completeInspection, submitCostSheet, createValuation, addNegotiation, submitForApproval, decideApproval, createAcquisition, markLost | `inspection.edit`, `cost_sheet.edit`, `valuation.edit`, `negotiation.edit`, `acquisition.submit`, `acquisition.approve`, `acquisition.create` |
| **sales** | saveRequirement, searchMatches, selectMatch, createQuotation, reviseQuotation, approveQuotation, sendQuotation, recordQuotationResponse, recordDeposit, signContract, evaluateWonRule (ภายใน) | `sales.edit`, `quotation.edit`, `quotation.approve`, `deposit.edit`, `contract.edit` |
| **fulfillment** | completeChecklistItem, recordQc, markReadyForDelivery, recordDelivery, recordInstallation, cancelTransaction | `checklist.<section>`, `qc.edit`, `delivery.edit`, `installation.edit`, `transaction.cancel` |
| **tasks** | listMyTasks, createTask, updateTaskStatus | ทุกคน (เห็นเฉพาะงานของตัวเอง/role ตัวเอง) — `task.view_all` สำหรับผู้บริหาร |
| **documents** | upload (Route Handler), list, download (ตรวจสิทธิ์ของ parent ก่อนเสมอ), archive | ตามสิทธิ์ของ parent |
| **search** | globalSearch(q) — clinic, contact, phone, model, serial, Device ID, เลขใบเสนอราคา, เลขธุรกรรม | ผลลัพธ์กรองตามสิทธิ์ |
| **admin** | users, roles, master data, app settings, audit log viewer | `admin.users`, `admin.master_data`, `audit.view` |

## สิทธิ์ตาม role (ข้อเสนอเบื้องต้น 🟡 — ต้องยืนยันใน Q4–Q8)

V = ดู, E = สร้าง/แก้, A = อนุมัติ, — = ไม่มีสิทธิ์ · ผู้ใช้ที่มีหลาย role ได้สิทธิ์รวมกัน

| Capability | GM | Sales Coord | Sales Exec | Sales Dir | Service Eng | Service Dir | Marketing | Mgmt | Admin |
|---|---|---|---|---|---|---|---|---|---|
| ลูกค้า / contact | E | E | E | E | V | V | V | V | V |
| Lead / follow-up | E | E | E | E | V | V | V | V | — |
| Device (ข้อมูลทั่วไป) | E | E | V | V | E | E | V | V | V |
| Inspection / technical summary | V | V | V | V | E | E | — | V | — |
| Service & MA history | V | V | V | V | E | E | — | V | — |
| ต้นทุนในประวัติซ่อม/MA 🔒 | V | — | — | V | — | E | — | V | — |
| Cost Sheet 🔒 | E | — | — | V | — | E | — | V | — |
| Valuation 🔒 ❓Q7 | E | — | — | E | — | V | — | V | — |
| Negotiation | E | V | E | E | — | — | — | V | — |
| ส่งขออนุมัติซื้อ | E | — | E | E | — | — | — | — | — |
| **อนุมัติซื้อ** | **A** | — | — | — | — | — | — | ❓Q8 | — |
| สร้าง Acquisition ❓Q13 | E | E | — | — | — | — | — | — | — |
| Inventory (ต้นทุนบัญชี 🔒) | E | V | V | V | V | V | — | V | — |
| Requirement / Device Match | E | V | E | E | V | V | — | V | — |
| Quotation | E | E | E | E | — | — | V | V | — |
| อนุมัติ Quotation ❓Q14 | A | — | — | A | — | — | — | — | — |
| Deposit ❓Q16 | E | E | V | V | — | — | — | V | — |
| Contract | E | E | V | E | — | — | — | V | — |
| Checklist หมวด Commercial / Sales | E | E | E | E | V | V | V | V | — |
| Checklist หมวด Marketing | V | V | V | V | V | V | E | V | — |
| Checklist หมวด Technical / QC / Delivery / Installation | V | V | V | V | E | E | V | V | — |
| ยกเลิกธุรกรรม | E | — | — | — | — | — | — | — | — |
| Audit log | V | — | — | — | — | — | — | V | V |
| จัดการผู้ใช้ / master data / settings | E | — | — | — | — | — | — | — | E |

## การป้องกันข้อมูลการเงิน (ข้อ 12)

1. **อ่าน:** ทุก service ที่ return record ที่มีฟิลด์ 🔒 ต้องผ่าน `redact(record, session)` ซึ่งลบฟิลด์ออก (ไม่ใช่แค่ส่ง null)
   role ที่ไม่มีสิทธิ์จึงไม่ได้รับข้อมูลนั้นเลยแม้จะเปิด DevTools ดู
2. **เขียน:** ตรวจ capability ก่อนเขียน และ **whitelist ฟิลด์** ที่แต่ละ action แก้ได้ (ใช้ zod schema ต่อ action)
   ส่งฟิลด์ราคามาใน action ที่ไม่มีสิทธิ์ → ปฏิเสธทั้ง request และบันทึกลง audit
3. **Export / ค้นหา / Dashboard:** ใช้ redact ตัวเดียวกัน

## Audit infrastructure (ทำใน P0 ก่อนโมดูลอื่น — ข้อ 15.7)

- `writeAudit(tx, …)` รับ transaction client เข้ามา ถ้า audit เขียนไม่ได้ การเปลี่ยนแปลงทั้งหมดต้อง rollback
- เก็บ old/new ทุกฟิลด์ที่เปลี่ยนสำหรับ: ราคา, ต้นทุน, การอนุมัติ, เจ้าของเครื่อง, สถานะ (ข้อ 12)
- ตาราง audit_logs ไม่มีทางแก้หรือลบผ่าน UI หรือ service ใด ๆ
- บันทึก login สำเร็จ/ล้มเหลวด้วย

## ระบบ login (ทำแล้ว)

1. **Session เพิกถอนได้ทันที:** โหลดผู้ใช้ + role จาก DB ทุก request และเทียบ `session_version`
   ปิดบัญชี / เปลี่ยน role / รีเซ็ตรหัส → มีผลทันที
2. **จำกัดการเดารหัส:** ผิด 5 ครั้งใน 15 นาที (ต่อชื่อผู้ใช้ หรือ 20 ครั้งต่อ IP) → ล็อก 15 นาที (ตั้งค่าได้)
3. **บัญชีใหม่/รีเซ็ตรหัส** ใช้รหัสชั่วคราว และบังคับให้ผู้ใช้ตั้งรหัสใหม่เองตอนเข้าครั้งแรก

## Automated tests (ข้อ 15.9)

- **crm/tests/run.php** — Scenario A–E ที่ระดับ service: role ที่ไม่มีสิทธิ์ถูกปฏิเสธ, redact ลบฟิลด์ 🔒, ข้ามขั้นไม่ได้
  (ตรวจเครื่องไม่ครบ, ซื้อโดยไม่มีอนุมัติ, QC FAIL ห้ามพร้อมส่ง, serial ไม่ตรงห้ามส่งมอบ), กฎ WON, OVERDUE, rollback เมื่อขั้นใดล้ม
- **crm/tests/http_test.php** — ทำ Scenario A + B ผ่านฟอร์มจริงในนามแต่ละ role, เปิดทุกหน้าในทุก role, CSRF, XSS,
  การดาวน์โหลดไฟล์ตามสิทธิ์, ตัวเลขการเงินไม่หลุดไปถึง role ที่ไม่มีสิทธิ์, ปิดบัญชีแล้วถูกเตะออกทันที
