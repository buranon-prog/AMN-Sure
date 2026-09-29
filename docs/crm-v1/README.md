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

## ความสัมพันธ์กับระบบที่มีอยู่แล้ว

ใน repo ตอนนี้มี ERP ตัวแรกอยู่แล้ว (Inventory, Consignment, Finance, HR, Supply Chain, CRM แบบง่าย, Strategy)
สเปก CRM v1 ใหญ่กว่าและลงรายละเอียดกว่ามาก และบอกให้ **เลื่อน** ERP/HR ออกไปก่อน

🟡 **ข้อเสนอ:** สร้าง CRM v1 ในโค้ดเบสเดิม (Next.js 14 + Prisma + Auth.js) เพื่อใช้ระบบ login และโครงหน้าร่วมกัน
แล้วให้ CRM v1 **แทนที่** โมดูล CRM และ Inventory เดิม ส่วนโมดูลอื่นเก็บไว้แต่ซ่อนจากเมนูของ role CRM
รายละเอียดดูคำถาม Q1–Q2

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

เขียน automated test (Vitest + ฐานข้อมูล SQLite สำหรับ test) สำหรับ **สิทธิ์** และ **การเปลี่ยนสถานะ** ทุกตัว
ในไฟล์ 02 ตั้งแต่ P0 เป็นต้นไป

## ข้อจำกัดด้าน infrastructure ที่รู้ตอนนี้

- โฮสต์เป็น cPanel พื้นที่ 2 GB และ **ยังไม่ยืนยันว่ารองรับ Node.js** (ดู Q22)
- สเปกต้องการเก็บรูปและวิดีโอจากการตรวจเครื่อง ซึ่ง 2 GB จะเต็มเร็ว (ดู Q21)
