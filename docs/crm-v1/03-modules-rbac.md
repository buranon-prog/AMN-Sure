# 03 — โมดูล, API และสิทธิ์ (RBAC)

## โครงสร้างโค้ด

ใช้ **Server Actions** ของ Next.js ตามแบบเดิม ไม่ได้เปิด REST API แยก ยกเว้นอัปโหลดไฟล์และ export ที่เป็น Route Handler

```
src/
  server/                     ← business logic ทั้งหมด (ห้าม import จาก client component)
    core/
      permissions.ts          ← capability, role → capability, can()/assert()
      audit.ts                ← writeAudit(tx, entity, id, action, before, after)
      refno.ts                ← nextRef(tx, "DO", year)
      transitions.ts          ← ตัวช่วยตรวจสถานะต้นทาง/ปลายทาง
      redact.ts               ← ตัดฟิลด์ 🔒 ออกตามสิทธิ์ก่อนส่งให้หน้าเว็บ
      files.ts                ← เก็บ/อ่านไฟล์ (local disk หรือ S3 — ดู Q21)
    customers/   leads/   devices/   acquisition/   sales/
    fulfillment/ tasks/   activities/ documents/  search/  admin/
  app/(crm)/…                 ← หน้าเว็บ (ดู 04) เรียก server/* ผ่าน actions.ts ของแต่ละหน้า
tests/
  permissions.test.ts   acquisition-flow.test.ts   sales-flow.test.ts   followup.test.ts
```

ทุก service function มีโครงเหมือนกัน: `assert(capability)` → โหลด record → ตรวจสถานะ → `prisma.$transaction(...)`
(เขียนข้อมูล + audit + activity + task) → ตัดฟิลด์ 🔒 ออกก่อน return

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

## ปรับปรุงระบบ login เดิม (ทำใน P0)

1. **Session เพิกถอนได้ทันที:** เก็บ `session_version` ใน JWT แล้วเทียบกับ DB ทุก request ฝั่ง server
   ถ้าปิดบัญชีหรือเปลี่ยน role จะเพิ่มค่านี้ → ผู้ใช้ถูกออกจากระบบทันที (ตอนนี้ต้องรอถึง 30 วัน)
2. **จำกัดการเดารหัส:** ผิด 5 ครั้งใน 15 นาที (ต่อ email หรือ IP) → ล็อก 15 นาที 🟡
3. **สิทธิ์อ่านจาก role ใน DB** แทนการเก็บ permission ทั้งก้อนใน JWT

## Automated tests (ข้อ 15.9)

- **permissions.test.ts** — ทุกแถวของตาราง RBAC ข้างบน: role ที่ไม่มีสิทธิ์เรียก action แล้วต้องถูกปฏิเสธ และ redact ต้องลบฟิลด์ 🔒 (Scenario E)
- **acquisition-flow.test.ts** — Scenario A ครบวงจร + ข้ามขั้นไม่ได้ (เช่น complete inspection โดยที่ข้อ mandatory ว่าง → error, สร้าง acquisition โดยไม่มี approval → error)
- **sales-flow.test.ts** — Scenario B + กฎ WON + QC FAIL ห้ามไป READY_FOR_DELIVERY + serial ไม่ตรงห้ามส่งของ
- **followup.test.ts** — Scenario C: OVERDUE และสิ่งที่แสดงบน dashboard
- ทุก transition: ถ้าขั้นใดล้มกลางทาง ต้อง rollback ทั้งหมด
