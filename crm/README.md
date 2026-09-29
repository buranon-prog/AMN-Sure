# AMN Sure CRM v1 (PHP + MySQL สำหรับ cPanel)

ระบบ CRM + Device Lifecycle + Buy/Sell Workflow ตามสเปก [`docs/crm-v1/00-spec-v1.md`](../docs/crm-v1/00-spec-v1.md)
ออกแบบให้ **อัปโหลดขึ้น cPanel แล้วใช้งานได้ทันที** (ไม่ต้องใช้ Node.js, Composer หรือ SSH)

- ติดตั้งบนโฮสต์: ดู [`INSTALL-TH.md`](./INSTALL-TH.md)
- เอกสารออกแบบ (ERD, state transition, RBAC, routes, คำถามที่ยังเปิด): [`docs/crm-v1/`](../docs/crm-v1/)

## เทคโนโลยี

- PHP 7.4+ (ทดสอบบน 8.4) ไม่มี framework / dependency ภายนอก, MySQL 5.7+ / MariaDB 10.3+ (ผ่าน PDO)
- หน้าเว็บ server-rendered ภาษาไทย, CSS/JS ของตัวเอง (ไม่โหลดอะไรจาก CDN), CSP ไม่อนุญาต inline script

## โครงสร้าง

```
index.php            front controller: ?r=module.action → app/controllers/{module}.php
                     GET → c_{module}_{action}()   POST → c_{module}_post_{action}() (ตรวจ CSRF เสมอ)
install.php          ตัวติดตั้งผ่านเว็บ (ใช้ได้ครั้งเดียว สร้าง app/config.php)
app/lib/             db (PDO, UUID, เลขอ้างอิง, transaction), auth, perm (RBAC + redact), audit, view, files
app/services/        business logic + การตรวจสิทธิ์/สถานะทั้งหมด (เรียกได้จาก test โดยไม่ผ่าน HTTP)
app/controllers/     แปลง request → service → redirect/render
app/views/           template (ทุกค่าที่แสดงผ่าน e())
app/sql/schema.sql   43 ตาราง (37 ตามสเปก + 6 ตารางเสริม)
storage/             ไฟล์แนบ + log (ปิดการเข้าถึงด้วย .htaccess)
tests/               run.php (service/RBAC/state) และ http_test.php (end-to-end ผ่าน HTTP)
```

## รันในเครื่อง

```bash
# 1) สร้างฐานข้อมูลว่างใน MySQL/MariaDB แล้วเปิดตัวติดตั้ง
php -S 127.0.0.1:8080 -t crm
# เปิด http://127.0.0.1:8080/install.php

# 2) ทดสอบ business logic (ใช้ฐานข้อมูลแยก — ตารางจะถูกลบทุกครั้ง)
cp crm/tests/config.test.sample.php crm/tests/config.test.php   # แก้ข้อมูลฐานข้อมูล
php crm/tests/run.php

# 3) ทดสอบ end-to-end ผ่าน HTTP (หลังติดตั้งแล้ว)
php crm/tests/http_test.php http://127.0.0.1:8080 <gm-username> <gm-password>

# 4) สร้างไฟล์ zip สำหรับ cPanel → dist/amnsure-crm-v<version>.zip
bash crm/build.sh
```

## กฎที่ต้องรักษาเวลาแก้โค้ด

- ตรวจสิทธิ์ด้วย `require_cap()` ใน service ทุกฟังก์ชันที่แก้ข้อมูล (ไม่ใช่แค่ซ่อนปุ่ม)
- เปลี่ยนสถานะต้องล็อกแถว (`db_lock`) ตรวจสถานะต้นทาง แล้วทำทุกอย่างใน `tx()` เดียว
- แก้ข้อมูลธุรกิจด้วย `update_audited()` / `insert_audited()` เพื่อให้มี audit log
- ข้อมูลการเงินที่อ่อนไหวต้องผ่าน `redact()` หรือเช็ก `can('finance.view')` ก่อนแสดง และห้ามใส่ตัวเลขเหล่านี้ใน activity timeline
- ห้ามลบข้อมูลการเงิน/อนุมัติ/ธุรกรรมจริง ใช้ archive/void
