# AMN-Sure — ERP ภายในองค์กร (เครื่องมือแพทย์มือสอง)

Web app ERP ภายในสำหรับธุรกิจซื้อ-ขายเครื่องมือแพทย์มือสอง (รับเข้า/ขายออก และรับฝากขาย)
ใช้งานได้เฉพาะบุคคลในองค์กรที่มีบัญชีเท่านั้น ไม่มีหน้าสมัครสมาชิกสาธารณะ

## สแตกเทคโนโลยี

- Next.js 14 (App Router) + TypeScript + Tailwind CSS
- Prisma ORM — ใช้ SQLite ตั้งต้น (ไฟล์เดียว รันได้ทันทีไม่ต้องติดตั้งฐานข้อมูลแยก)
  เปลี่ยนไปใช้ Postgres ตอน deploy จริงได้แค่แก้ `DATABASE_URL` และ `provider` ใน `prisma/schema.prisma`
- Auth.js (NextAuth v5) แบบ Credentials + JWT session — ไม่มี OAuth ภายนอก บัญชีสร้างโดยผู้จัดการเท่านั้น

## สิทธิ์การใช้งาน (Role-based access control)

- **Manager**: เข้าถึงได้ทุกโมดูล ทุกข้อมูล โดยอัตโนมัติ และเป็นผู้เดียวที่จัดการบัญชีผู้ใช้งาน/สิทธิ์ได้ (`/admin/users`)
- **Employee**: เห็นเฉพาะเมนู/โมดูลที่ได้รับสิทธิ์ "ดูข้อมูลได้" เท่านั้น และกรอก/แก้ไขข้อมูลได้เฉพาะโมดูลที่ได้รับสิทธิ์
  "กรอก/แก้ไขข้อมูลได้" — กำหนดได้ละเอียดเป็นรายโมดูล (Inventory, Consignment, Finance, HR, Supply Chain, CRM, Strategy)
  ผ่านหน้า `/admin/users/[id]`

การตรวจสิทธิ์ทำที่ server-side ทุกหน้า/ทุก server action (ไม่ใช่แค่ซ่อนปุ่มฝั่ง client)

## โมดูลที่มีให้

| โมดูล | เส้นทาง | รายละเอียด |
|---|---|---|
| Inventory | `/inventory` | แคตตาล็อกสินค้า, รับสินค้าเข้าสต็อก (ซื้อ), ขายสินค้า |
| Consignment | `/consignment` | ผู้ฝากขาย, รับสินค้าฝากขายเข้าสต็อก, ขาย → คำนวณยอดจ่ายคืนอัตโนมัติ |
| Finance | `/finance` | บันทึกรายรับ-รายจ่าย + รายการที่เกิดขึ้นอัตโนมัติจากการขาย/ฝากขาย/ค่าใช้จ่าย HR |
| HR | `/hr`, `/hr/attendance`, `/hr/kpi`, `/hr/costs` | พนักงาน, การลงเวลา, ตัวชี้วัด KPI (ตั้งค่าเองได้), ค่าใช้จ่ายดำเนินงาน |
| Supply Chain | `/supply-chain` | สถานะการสั่งซื้อ/ขนส่งจากซัพพลายเออร์ |
| CRM | `/crm`, `/crm/[id]` | ข้อมูลลูกค้า, loyalty tier, ความสนใจ/โอกาสในการขาย, ประวัติการซื้อ |
| Strategy | `/strategy` | เครื่องมือแพทย์ที่ควรจัดหา (และซื้อจากไหน) + ตลาด/กลุ่มลูกค้าที่ควรขาย |

การขายสินค้า (จาก Inventory หรือ Consignment) จะสร้างรายการ `Sale` + `FinanceTransaction` (รายรับ)
โดยอัตโนมัติ และถ้าเป็นสินค้าฝากขายจะสร้างรายจ่าย "จ่ายคืนผู้ฝากขาย" เพิ่มให้ด้วย

## เริ่มต้นใช้งาน (Local development)

```bash
npm install
cp .env.example .env
# แก้ AUTH_SECRET ใน .env เป็นค่าที่สุ่มเอง เช่น: openssl rand -base64 32

npm run db:push     # สร้างตารางฐานข้อมูล (SQLite ไฟล์ prisma/dev.db)
npm run db:seed      # สร้างบัญชีผู้จัดการ + พนักงานตัวอย่าง + ข้อมูลตัวอย่าง
npm run dev
```

เปิด http://localhost:3000 แล้วเข้าสู่ระบบด้วยบัญชีที่ seed ให้:

- ผู้จัดการ: `manager@amnsure.local` / `ChangeMe123!`
- พนักงานตัวอย่าง: `employee@amnsure.local` / `ChangeMe123!`

**สำคัญ**: เปลี่ยนรหัสผ่านทั้งสองบัญชีทันทีหลังติดตั้งจริง (หน้า `/admin/users/[id]` มีปุ่มรีเซ็ตรหัสผ่าน)
หรือกำหนดรหัสผ่านเริ่มต้นเองผ่าน env `SEED_MANAGER_PASSWORD` / `SEED_EMPLOYEE_PASSWORD` ก่อนรัน `npm run db:seed`

## Deploy ใช้งานจริงภายในองค์กร

แอปนี้ออกแบบมาให้ "คนนอกเข้าไม่ได้" จึงควร deploy ไว้หลัง VPN/เครือข่ายภายในองค์กร หรือ intranet server
เท่านั้น ไม่แนะนำให้เปิด public โดยไม่มีการควบคุมเครือข่ายเพิ่มเติม แม้ระบบจะบังคับ login ทุกหน้าอยู่แล้ว

สำหรับข้อมูลจริงที่มีปริมาณมากขึ้น แนะนำเปลี่ยนจาก SQLite เป็น PostgreSQL:

1. แก้ `prisma/schema.prisma` → `datasource db { provider = "postgresql" ... }`
2. ตั้ง `DATABASE_URL` เป็น connection string ของ Postgres
3. รัน `npx prisma db push` (หรือใช้ `prisma migrate` ถ้าต้องการ migration history)
