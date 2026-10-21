# AMN SURE — ลูกค้า · สาขา · เครื่อง · การขาย · บริการ

ระบบตามสเปก `SURE-ClaudeCode-Prompt.md` สร้างเป็น **PHP + MySQL** เพื่อวางบน cPanel subdomain `crmsure.amnsure.co.th`
วิธีติดตั้งบนโฮสต์: [`INSTALL-TH.md`](./INSTALL-TH.md)

## โครงสร้าง

```
sure/
  index.php                 front controller: ?r=module.action (GET → c_x_y / POST → c_x_post_y + ตรวจ CSRF)
  install.php               ตัวติดตั้งผ่านเว็บ (สร้างตาราง + ตัวเลือกหัวข้อ 10 + VAT 7% + Manager คนแรก)
  app/sql/schema.sql        ตารางธุรกิจ 8 ตาราง + sure_options/sure_settings + users/user_permissions/login_attempts
  app/lib/fields.php        ทะเบียนฟิลด์ทุกตาราง (ลำดับ + label ตรงหัวข้อ 9) · ตัวเลือกหัวข้อ 10 · 77 จังหวัด → 7 ภาค
  app/lib/compute.php       ฟิลด์อัตโนมัติทั้งหมดในหัวข้อ 6 (คำนวณตอนอ่าน ไม่เก็บในฐานข้อมูล)
  app/lib/records.php       เพิ่ม/แก้/ลบ + validation หัวข้อ 4–5 + รหัสถัดไป
  app/lib/perm.php          สิทธิ์ MANAGER / EMPLOYEE (canView, canEdit ของโมดูล SURE)
  app/controllers/table.php รายการ + ฟอร์มของ C B A D S P V T (ค้นหา/กรอง/เรียง/แบ่งหน้า)
  app/controllers/          search, profit, dashboard, settings, export, users, auth
  tools/seed-demo.php       ใส่ข้อมูลตัวอย่างหัวข้อ 11 (CLI เท่านั้น ไม่รวมในไฟล์ zip)
  tests/run.php             เกณฑ์ตรวจรับหัวข้อ 12 ข้อ 1–8 + สิทธิ์ระดับ service
  tests/http_test.php       ข้อ 9: ตรวจ payload จริงของทุกหน้า/action/export ในนามพนักงานแต่ละแบบ
  build.sh                  สร้าง ../dist/amnsure-sure-v<version>.zip สำหรับอัปโหลดขึ้น cPanel
```

หน้าจอ (route) ↔ สเปกหัวข้อ 7: `?r=customers` `branches` `followups` `devices` `sales` `payments` `services` (+ `.new` / `.edit&id=`),
`search` (/sure/search), `costs` (/sure/costs), `profit` (/sure/profit), `dashboard` (/sure/dashboard), `settings` (/sure/settings),
`export`, `users` (/admin/users)

## สิทธิ์ (สเปกหัวข้อ 3)

| | Manager | พนักงาน canView | พนักงาน canEdit | พนักงานไม่มีสิทธิ์ SURE |
|---|---|---|---|---|
| ตาราง C B A D S P V + ค้นหา + export | ✔ | ดู | ดู + เพิ่ม/แก้ไข | ✘ |
| ต้นทุนเครื่อง, กำไรรายการขาย, สรุป, ฟิลด์ "Manager เท่านั้น" | ✔ | ✘ | ✘ | ✘ |
| รายการตัวเลือก + VAT | แก้ได้ | ดูอย่างเดียว | ดูอย่างเดียว | ✘ |
| ลบแถว, จัดการบัญชีผู้ใช้ | ✔ | ✘ | ✘ | ✘ |

การกรองทำที่ server: เมื่อผู้ใช้ไม่ใช่ Manager `ds()` จะ **ไม่ query ตาราง `sure_costs` เลย** ไม่คำนวณฟิลด์ Manager
และตัดฟิลด์ `mgr` ทิ้งอีกชั้นก่อนส่งให้หน้าเว็บ/ค้นหา/เรียง/export (`tests/run.php` พิสูจน์โดยซ่อนตารางต้นทุนแล้วคำนวณข้อมูลพนักงานได้ครบ)
หน้าค้นหาลูกค้าแสดง "สรุปกับ SURE" ให้พนักงานได้ตามสเปก 7.2

## Seed ข้อมูลตัวอย่าง (dev/ทดสอบเท่านั้น)

```bash
php tools/seed-demo.php <ชื่อผู้ใช้ Manager>
```

ใส่ข้อมูลหัวข้อ 11 ผ่าน validation เดียวกับหน้าเว็บ · ปฏิเสธถ้าเรียกผ่านเว็บ หรือมีข้อมูลลูกค้า/เครื่อง/การขายอยู่แล้ว
`tools/` ถูกปิดด้วย `.htaccess` และไม่รวมอยู่ในไฟล์ zip สำหรับ production

## รันบนเครื่องตัวเอง + tests

```bash
# ต้องมี PHP 7.4+ (pdo_mysql, mbstring) และ MySQL/MariaDB
php -S 127.0.0.1:8090 -t sure                      # เปิด http://127.0.0.1:8090/install.php แล้วติดตั้ง
php sure/tools/seed-demo.php manager               # ข้อมูลตัวอย่าง

cp sure/tests/config.test.sample.php sure/tests/config.test.php   # ใส่ฐานข้อมูลทดสอบว่าง ๆ
php sure/tests/run.php                             # ข้อ 1–8 + สิทธิ์ (ล้างฐานข้อมูลทดสอบทุกครั้ง)
php sure/tests/http_test.php http://127.0.0.1:8090 manager <รหัส>   # ข้อ 9 ผ่าน HTTP (ต้อง seed ก่อน, รันซ้ำได้)
```

## จุดที่ต่างจากสเปกตามตัวอักษร (ตัดสินใจแล้ว — ระบุไว้ใน PR)

1. **Stack**: สเปกหัวข้อ 0 ให้ใช้ Next.js + Prisma ของ repo แต่ผู้ใช้ต้องการวางบน cPanel subdomain + MySQL ของ cPanel
   ซึ่งยังไม่ยืนยันว่ารองรับ Node.js จึงสร้างเป็น PHP + MySQL (แบบเดียวกับ `crm/`) ไม่แตะโค้ด Next.js เดิม
2. **ตารางระบบสิทธิ์**: `users`, `user_permissions` (module `SURE`, can_view, can_edit), `login_attempts` — แทน RBAC ของ Next.js
   ที่สเปกอ้างถึง (ไม่ใช่ข้อมูลธุรกิจ) · ไม่มีตาราง audit log แยก (สเปกกำหนดเฉพาะ audit fields 4 ตัวในทุกตาราง)
3. **ชื่อคอลัมน์**: `by` → `by_name`, `usage` → `usage_info`, `close` → `close_date` (คำสงวนของ MySQL) — label และ field_key ในโค้ดยังเป็นตามสเปก
4. **GP % รวม** (หน้าสรุป/หน้ากำไร) = กำไรขั้นต้นรวม ÷ ยอดขายรวม (ไม่รวมยกเลิก) · ยอดขาย/กำไร/GP % บนหน้าสรุปนับเฉพาะ "ปีที่สรุป"
   ส่วน ยอดค้างชำระรวม / มูลค่าสต็อก / ประกันใกล้หมด / งานค้าง / นัดติดตาม เป็นค่าปัจจุบันทั้งระบบ
5. **ระยะประกัน 0 เดือน** ถือเป็น "ไม่ระบุประกัน" เหมือนช่องว่าง
