# AMN Sure CRM v1 — เอกสารออกแบบก่อนเริ่มเขียนโค้ด

เอกสารชุดนี้ทำตามข้อ 15 ของสเปก ([`00-spec-v1.md`](./00-spec-v1.md)) ที่ให้ทำ ERD, state transition,
module map, route map และรายการคำถามที่ยังไม่ชัด **ก่อน** เริ่มเขียนโค้ด

| ไฟล์ | เนื้อหา | ข้อสเปก |
|---|---|---|
| [`00-spec-v1.md`](./00-spec-v1.md) | สเปกต้นฉบับ (source of truth) | — |
| [`01-erd-schema.md`](./01-erd-schema.md) | ERD + ตารางทั้งหมดและคอลัมน์หลัก | 15.1 |
| [`02-state-transitions.md`](./02-state-transitions.md) | ตารางสถานะของ workflow ซื้อ/ขาย และเงื่อนไข | 15.2 |
| [`03-modules-rbac.md`](./03-modules-rbac.md) | โครงสร้างโมดูล/service, สิทธิ์ตาม role, audit | 15.3 |
| [`04-routes-components.md`](./04-routes-components.md) | หน้าจอ/เส้นทาง และโครง component | 15.4 |
| [`05-open-questions.md`](./05-open-questions.md) | **คำถามที่ต้องตอบก่อนเริ่มสร้าง** | 15.5 |

ส่วนที่ในสเปกไม่ได้กำหนดไว้ ผมเสนอเป็น **ข้อเสนอ (proposal)** และติดป้าย 🟡 ไว้ทุกจุด ส่วนที่เกี่ยวกับการเงิน
และการอนุมัติ ผมไม่ได้กำหนดเองเงียบ ๆ แต่ย้ายไปเป็นคำถามในไฟล์ 05 ทั้งหมด

## สถานะการพัฒนา (v1.0.0)

✅ **สร้างเสร็จแล้วในโฟลเดอร์ [`crm/`](../../crm/)** — ดูวิธีติดตั้งบน cPanel ที่ [`crm/INSTALL-TH.md`](../../crm/INSTALL-TH.md)

- **เปลี่ยนจาก Next.js เป็น PHP + MySQL** เพราะต้องนำไปวางบน cPanel ที่เป็น subdomain (`crmsure.amnsure.co.th`)
  และยังไม่ยืนยันว่าโฮสต์รองรับ Node.js — PHP/MySQL ใช้ได้กับ cPanel ทุกเจ้า อัปโหลด zip แล้วติดตั้งผ่านหน้าเว็บได้เลย (Q22)
- **ใช้ข้อเสนอในไฟล์ 05 ทุกข้อเป็นค่าเริ่มต้น** ข้อที่ปรับได้โดยไม่ต้องแก้โค้ดอยู่ในเมนู "ตั้งค่า" (VAT, ยืนราคา, มัดจำเริ่มต้น %,
  เงื่อนไข WON, ขนาดไฟล์แนบ, การล็อกเมื่อใส่รหัสผิด) และ "ข้อมูลหลัก" (แหล่งลีด, ประเภทเครื่อง, template ตรวจเครื่อง/เช็กลิสต์/QC)
- เข้าสู่ระบบด้วย **ชื่อผู้ใช้หรืออีเมล + รหัสผ่าน** แต่ละคนมีบัญชีของตัวเอง สร้างโดย GM/Admin
- Automated tests: `crm/tests/run.php` (148 checks — RBAC, state transitions, Scenario A–E) และ
  `crm/tests/http_test.php` (603 checks — ทำ Scenario A+B ผ่านหน้าเว็บจริงทุก role + เปิดทุกหน้า + ทดสอบความปลอดภัย)
- โครง P0–P6 ในตารางด้านล่างทำครบในรอบนี้ ยกเว้นการนำเข้าข้อมูลจาก Excel (Q27) ที่ยังไม่ได้ทำ

## ความสัมพันธ์กับระบบที่มีอยู่แล้ว

ใน repo ตอนนี้มี ERP ตัวแรกอยู่แล้ว (Inventory, Consignment, Finance, HR, Supply Chain, CRM แบบง่าย, Strategy)
สเปก CRM v1 ใหญ่กว่าและลงรายละเอียดกว่ามาก และบอกให้ **เลื่อน** ERP/HR ออกไปก่อน

CRM v1 เป็นแอปแยก (`crm/`) ไม่แตะโค้ด ERP เดิมที่ root ของ repo (Next.js) — ERP เดิมยังไม่เคย deploy และไม่มีข้อมูลจริง
ถ้าต้องการความสามารถของ ERP เดิม (HR, Finance, ฝากขาย) บน cPanel ด้วย ต้องย้ายมาเป็นโมดูลใน `crm/` (Q1)

## ลำดับการสร้าง (ตามข้อ 15.6–15.9)

| เฟส | งาน | ผ่านเมื่อ |
|---|---|---|
| **P0 Foundation** | schema + migration, seed role/master data, RBAC แบบ capability, audit log, เลขอ้างอิง, soft delete, ระบบ Task/Activity/Document กลาง, แก้ session ให้เพิกถอนได้ทันที + จำกัดการล็อกอินผิด | test สิทธิ์ผ่าน |
| **P1 Customer** | Organization, Contact, Lead, follow-up, OVERDUE, My Dashboard | Scenario C |
| **P2 Device** | Device Master, ตรวจเครื่องซ้ำ, ประวัติเจ้าของ, Service/MA history, Device 360 (บางส่วน) | — |
| **P3 Acquisition** | Seller lead → Inspection → Cost Sheet → Valuation → Negotiation → GM Approval → Acquisition → Inventory | Scenario A, E |
| **P4 Sales** | Buyer requirement → Device Match → Quotation (versions) → Deposit → Contract → WON → Checklist → QC → Delivery → Installation | Scenario B |
| **P5 ปิดงาน** | Global search, Device 360 / Customer 360 ครบ, Documents, Audit Log viewer | Scenario D |
| **P6 Deploy** | คู่มือ deploy (cPanel Node.js หรือ VPS), `.env` template, แผน backup/migration | — |

Automated test สำหรับ **สิทธิ์** และ **การเปลี่ยนสถานะ** อยู่ที่ `crm/tests/` (PHP ล้วน ไม่ต้องติดตั้งอะไรเพิ่ม)

## ข้อจำกัดด้าน infrastructure ที่รู้ตอนนี้

- โฮสต์เป็น cPanel พื้นที่ 2 GB — จึงสร้างเป็น PHP + MySQL (ดู Q22)
- สเปกต้องการเก็บรูปและวิดีโอจากการตรวจเครื่อง ซึ่ง 2 GB จะเต็มเร็ว (ดู Q21)
