# 04 — หน้าจอ, เส้นทาง และโครง Component

ออกแบบสำหรับ desktop ก่อน แต่ยังใช้บนมือถือได้ (ข้อ 11) · UI ภาษาไทย ส่วนค่า status/code ในระบบเป็นภาษาอังกฤษ 🟡

## Route map

| เส้นทาง | หน้าจอ (สเปกข้อ 10) | หมายเหตุ |
|---|---|---|
| `/login` | Login | มีอยู่แล้ว |
| `/dashboard` | My Dashboard | เรียงตามข้อ 11: Today's Tasks → Overdue Follow-ups → Pending Approvals → Active Deals → กราฟ |
| `/search?q=` | Global search | ช่องค้นหาอยู่ที่ header ทุกหน้า |
| `/customers` · `/customers/new` · `/customers/[id]` | Organizations List / **Customer 360** | แท็บ: ภาพรวม, Contacts, Leads, เครื่อง, ประวัติการขายให้เรา, ดีลซื้อ, Quotations, ธุรกรรม, Service, Timeline, เอกสาร |
| `/contacts` · `/contacts/[id]` | Contacts | |
| `/leads` · `/leads/new?type=SELLER\|BUYER` · `/leads/[id]` | Leads List / Detail | ตัวกรอง: ของฉัน, OVERDUE, ประเภท, สถานะ |
| `/devices` · `/devices/new` · `/devices/[id]` | Devices List / **Device 360** | `/new` มีขั้นตอนเตือนเครื่องซ้ำก่อนบันทึก |
| `/acquisitions/opportunities` | รายการดีลซื้อ (seller) | |
| `/acquisitions/opportunities/[id]` | Device Opportunity Detail | ใช้ **stepper** แสดงขั้นตอนและ Next Action ด้านบน แล้วมีแท็บย่อยตามข้างล่าง |
| `…/[id]/inspection` | Inspection Form + Technical Summary | Service Eng; autosave draft |
| `…/[id]/cost-sheet` | Cost Sheet | 🔒 ประวัติทุก version |
| `…/[id]/valuation` | Valuation | 🔒 |
| `…/[id]/negotiation` | Negotiation Timeline | |
| `/approvals` · `/approvals/[id]` | Approval Inbox / Detail | หน้า detail = approval package เดียวครบทุกอย่าง (ข้อ 3) |
| `/acquisitions/[id]` | Acquisition Detail | |
| `/inventory` | Inventory | |
| `/sales/opportunities` · `/sales/opportunities/[id]` | Sales Opportunity / Buyer Requirement | stepper แบบเดียวกับฝั่งซื้อ |
| `…/[id]/match` | Device Matching | แสดง 2 กลุ่ม: A) ของในสต็อก B) เครื่องที่กำลังเจรจาซื้อ |
| `/quotations/[id]` | Quotation Builder + Version History | version เก่าอ่านอย่างเดียว; ดาวน์โหลด PDF 🟡 |
| `…/[id]/deposit` · `/contracts/[id]` | Deposit · Contract | แนบหลักฐาน |
| `/transactions` · `/transactions/[id]` | Sales Transaction | แท็บ: Checklist, QC, Delivery, Installation |
| `/tasks` | My Tasks | ของฉัน / ของ role ฉัน / ทั้งหมด (ผู้บริหาร) |
| `/activities` | Activities / Timeline | |
| `/documents` | Documents | |
| `/service/jobs` · `/service/cases` | Technical Jobs · Service Cases | |
| `/admin/users` · `/admin/roles` · `/admin/master-data` · `/admin/settings` | Admin | settings = กฎ WON, VAT, template checklist/inspection |
| `/admin/audit-log` | Audit Log | GM, Management, Admin |

## โครง Component

```
app/(crm)/layout.tsx          AppShell: Sidebar (เมนูตามสิทธิ์) + Header (GlobalSearch, MyTasksBadge, UserMenu)
components/
  layout/     AppShell, Sidebar, Header, PageHeader(title, refNo, StatusBadge, actions)
  workflow/   WorkflowStepper, NextActionCard, TransitionButton (เปิด dialog ยืนยัน + ช่องเหตุผล)
  data/       DataTable (ค้นหา/เรียง/แบ่งหน้าฝั่ง server), FilterBar, EmptyState
  form/       Field, SearchableSelect, Autocomplete (org/contact/device), DatePicker, MoneyInput,
              FileUpload, AutosaveForm (เก็บ draft)
  timeline/   ActivityTimeline, ActivityComposer (บันทึกโทร/LINE/นัดพบ + ตั้ง follow-up ถัดไป)
  entity/     OrganizationCard, ContactCard, DeviceCard, MoneyField (แสดง 🔒 เป็น "—" ถ้าไม่มีสิทธิ์)
  badges/     StatusBadge, OverdueBadge, RiskBadge, ResultBadge (PASS/MINOR/MAJOR/MISSING/NA)
features/
  customer360/   Customer360Tabs + ส่วนย่อยต่อแท็บ
  device360/     Device360Tabs, OwnershipHistory, UsageCard
  inspection/    InspectionChecklist (จัดกลุ่มตาม category, ข้อ mandatory มีเครื่องหมาย), TechnicalSummaryForm
  costing/       CostSheetEditor (รวมยอดอัตโนมัติ), ValuationForm, NegotiationTimeline
  approval/      ApprovalPackage (อ่านอย่างเดียวจาก snapshot), DecisionForm
  sales/         RequirementForm, MatchResults, QuotationBuilder, VersionHistory, DepositForm, ContractForm
  fulfillment/   ChecklistBoard (4 หมวด), QcForm, DeliveryForm (บังคับยืนยัน serial), InstallationForm
```

## หลักการ UX ที่จะใช้ทุกหน้า (ข้อ 11)

- ทุก record ที่ยัง active แสดง **Next Action** และวัน follow-up ที่หัวหน้าเสมอ
- ฟอร์มถามเฉพาะสิ่งที่ต้องใช้ในขั้นนั้น ส่วนช่องของขั้นถัดไปจะซ่อนไว้จนกว่าจะถึงขั้นนั้น
- ปุ่มเปลี่ยนสถานะแสดงเฉพาะเมื่อผู้ใช้มีสิทธิ์และเงื่อนไขครบ ถ้าไม่ครบจะบอกว่าขาดอะไร
  (ฝั่ง server ตรวจซ้ำอีกรอบเสมอ)
- dropdown ทุกตัวค้นหาได้, วันที่ใช้ date picker, เงินใช้ช่องที่จัดรูปแบบหลักพันให้อัตโนมัติ
