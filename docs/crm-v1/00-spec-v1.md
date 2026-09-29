# AMN Sure CRM --- Developer Specification v1.0

**Status:** Ready for development planning

**Company:** AMN Sure

## 1. Purpose & Product Definition

Build an internal CRM + Device Lifecycle + Buy/Sell Workflow system for
AMN Sure.

Primary goals: • Customer CRM: store organizations, contacts,
buyer/seller leads, follow-ups, activities and transaction history. •
Device Master: maintain one permanent record per physical device/serial
number, including ownership, inspection, repair, MA, cost, valuation and
transaction history. • Workflow: coordinate cross-department work from
seller lead to purchase, and buyer lead to installation. • Management
Control: approvals, task ownership, audit trail and operational
visibility.

Core rule: Customer = WHO, Device = WHAT, Transaction = WHICH DEAL. A
Device_ID must remain permanent throughout the device lifecycle.

## 2. User Roles

Initial roles: • General Manager (GM): full operational visibility;
acquisition/pricing approvals. • Sales Coordinator: seller/buyer lead
creation, customer data, follow-up, coordination. • Sales Executive:
seller/buyer communication and negotiation. • Sales Management /
Director: buyer requirement, commercial review, pricing input. • Service
Engineering: inspection, technical summary, repair/MA history, QC,
delivery, installation. • Service Management / Director: cost sheet
review and technical cost approval/input. • Marketing: pre-delivery
marketing/customer confirmation checklist. • Management: reporting and
assigned approvals.

Implement RBAC (role-based access control). Financial and approval
fields must not be editable by unauthorized roles.

## 3. Buy-Side Workflow (Acquisition)

SELLER LEAD Owner: Sales Coordinator Required: organization/contact,
phone/LINE/email, brand/model, expected price, location, lead source.
Action: search Device Master by serial/model/owner. Link existing device
or create new Device. Status: NEW_SELLER_LEAD

→ DEVICE INFORMATION Collect brand, model, serial, manufacture year,
installation year, owner, location, usage, accessories, asking price,
reason for sale. Action: Request Inspection. System creates task for
Service Engineering. Status: WAITING_INSPECTION

→ INSPECTION Owner: Service Engineering. Capture exterior,
functional/system test, handpiece, accessories, errors, usage,
consumables, safety, software, parts condition, photos/videos/documents.
Item result: PASS / MINOR_ISSUE / MAJOR_ISSUE / MISSING / NA.

→ TECHNICAL SUMMARY + REPAIR/MA HISTORY Owner: Service Engineering.
Capture overall condition, issues, required/recommended repair, missing
accessories, technical risk, estimated repair time. Retrieve/record
repair history, parts replacement, PM/CM, MA contract/expiry, repeat
failures.

→ COST SHEET Owner: Service Management / Director. Inputs: acquisition
assumption, repair, parts, refurbishment, accessories, transport,
installation, warranty provision, other costs. Calculated: Estimated
Total Cost.

→ VALUATION Inputs: asking price, total cost, market selling price,
historical selling price, condition, age, usage, demand, technical risk,
expected days-to-sell. Outputs: Fair Market Value, Recommended
Acquisition Price, Maximum Acquisition Price, Target Selling Price,
Minimum Selling Price, Expected GP, Expected GP Margin.

→ NEGOTIATION Owner: Sales / authorized management. Store every
offer/counteroffer with date, user, amount and note. Output: Final
Negotiated Price.

→ GM APPROVAL GM sees one approval package: seller, device, inspection
summary, risk, repair/MA history, cost sheet, negotiated price,
valuation, expected GP/margin. Decision: APPROVED /
APPROVED_WITH_CONDITION / REVISION_REQUIRED / REJECTED.

→ PURCHASE Create Acquisition record only after approval. Device status
becomes PURCHASED; if AMN Sure takes stock, create Inventory record and
status IN_INVENTORY.

## 4. Sell-Side Workflow

BUYER LEAD Capture organization/contact, interested device, budget,
timeline, lead source, owner, next follow-up.

→ REQUIREMENT Owner: Sales Management / Director. Capture
brand/model/technology, budget, preferred condition, accessories,
warranty, installation and expected purchase date.

→ DEVICE MATCH System must match buyer requirements against: A) AMN Sure
inventory, and B) active Seller Device Opportunities not yet owned by
AMN Sure. Create Device Match linking Buyer Opportunity ↔ Device ↔
Seller Opportunity (if applicable).

→ QUOTATION Create version-controlled quotation: buyer, device/serial,
condition, accessories, price, VAT, warranty, payment terms, delivery,
installation, validity. Status: DRAFT / APPROVED / SENT / ACCEPTED /
REJECTED / EXPIRED.

→ DEPOSIT Capture required amount, due date, received date, payment
evidence. Status: WAITING_DEPOSIT / DEPOSIT_RECEIVED.

→ CONTRACT Capture contract number, buyer, device/serial, price,
payment/warranty/delivery/installation terms; upload signed contract.
Status: CONTRACT_SIGNED.

→ WON Business rule for WON should be configurable; initial default:
accepted quotation + deposit received + signed contract. Create Sales
Transaction.

→ INTERNAL CHECKLIST Commercial: quotation, deposit, contract,
outstanding payment, invoice/tax docs. Sales: customer confirmation,
address, delivery date, receiving contact. Marketing: required
material/customer communication. Technical: preparation, accessories,
repair completion, cleaning, software, documentation.

→ PRE-DELIVERY EXTERNAL CHECK / QC Owner: Service Engineering. Check
serial, exterior, functions, output, handpiece, accessories, software,
errors, safety, cleaning, packaging. QC = PASS is mandatory before
READY_FOR_DELIVERY.

→ DELIVERY Owner: Service Engineering. Capture date, address, transport,
engineer, serial, accessories, receiving person, evidence.

→ INSTALLATION Owner: Service Engineering. Capture installation date,
engineer, result, system test, customer acceptance, training, documents.
After successful installation: TRANSACTION_COMPLETED.

## 5. Core Data Model

Required entities/tables: 1. users 2. roles 3. user_roles 4.
organizations 5. contacts 6. leads 7. devices 8. device_opportunities 9.
inspections 10. inspection_items 11. device_service_history 12.
ma_records 13. cost_sheets 14. cost_sheet_items 15. valuations 16.
negotiations 17. approvals 18. acquisitions 19. inventory 20.
sales_opportunities 21. device_matches 22. quotations 23.
quotation_versions 24. deposits 25. contracts 26. sales_transactions 27.
checklists 28. checklist_items 29. qc_records 30. deliveries 31.
installations 32. technical_jobs 33. service_cases 34. tasks 35.
activities 36. documents 37. audit_logs

Use UUID primary keys internally. Generate human-readable reference
numbers separately (e.g., DEV-000001, DO-2026-0001, QTN-2026-0001). Use
foreign keys and soft-delete/archive rules.
Financial/approval/transaction history must never be hard-deleted
through normal UI.

## 6. Critical Relationships

Organization 1---N Contacts Organization 1---N Leads Device 1---N Device
Opportunities Device 1---N Inspections Device 1---N Service History
Device 1---N MA Records Device Opportunity 1---N Cost
Sheets/Valuations/Negotiations Device Opportunity 1---N Approvals
Approved Device Opportunity 0..1---1 Acquisition Acquisition 0..1---1
Inventory Buyer Lead/Organization 1---N Sales Opportunities Sales
Opportunity N---N Devices through Device Match Sales Opportunity 1---N
Quotation Versions Sales Opportunity 0..N Deposits Sales Opportunity
0..N Contracts Won Sales Opportunity 1---1 Sales Transaction Sales
Transaction 1---N Checklist Items Sales Transaction 0..N QC Records
Sales Transaction 0..N Deliveries Sales Transaction 0..N Installations
All major objects 1---N Tasks / Activities / Documents.

## 7. Customer CRM Requirements

Customer 360 page must show: • Organization profile and account owner •
Contacts • Buyer/Seller lead history • Last Activity • Next Action •
Next Follow-up Date • Devices associated with customer • Seller history
• Buyer/sales opportunities • Quotations • Transactions • Service
history • Activity timeline • Documents

Every active lead must have Owner, Status, Next Action and Next
Follow-up Date. If follow-up date is past and lead is active, mark
OVERDUE.

## 8. Device Master Requirements

Device 360 page must show: • Device ID, brand, model, serial number •
Manufacture/install year • Current and previous ownership • Current
location • Usage (shot/pulse/hour as applicable) • Current
technical/commercial status • Inspection history • Repair/service
history • MA history • Cost sheets • Valuations • Seller opportunities •
Buyer matches/opportunities • Acquisition and sales transaction history
• Documents • Full activity timeline

Serial number should be unique where reliably available. The application
must warn users about probable duplicate devices before creating a new
record.

## 9. Business Rules & Automation

BR-01 Active lead requires owner + next action + next follow-up. BR-02
Request Inspection creates a Technical Task for Service Engineering.
BR-03 Inspection cannot be completed without overall result and required
mandatory inspection items. BR-04 Completed inspection notifies/returns
workflow to the commercial owner. BR-05 Cost Sheet must reference
inspection/technical information used. BR-06 Valuation must not
overwrite Cost Sheet; they are separate records. BR-07 Acquisition
cannot be created without required GM approval. BR-08 Approved purchase
can create Inventory automatically. BR-09 Device Match can reference
owned inventory OR an active seller device. BR-10 Quotation revisions
create a new version; old versions remain read-only. BR-11 WON rule is
configurable; initial rule = accepted quotation + deposit received +
signed contract. BR-12 QC PASS required before READY_FOR_DELIVERY. BR-13
Delivery and Installation must reference the exact Device_ID/serial.
BR-14 All changes to price, cost, approval and transaction status create
audit logs. BR-15 Important records use soft delete/archive/void; no
normal hard delete.

## 10. Required Screens / Routes

Minimum screens: • Login • My Dashboard • Organizations List / Customer
360 • Contacts • Leads List / Lead Detail • Devices List / Device 360 •
Device Opportunity Detail • Inspection Form • Technical Summary /
Service & MA History • Cost Sheet • Valuation • Negotiation Timeline •
Approval Inbox / Approval Detail • Acquisition Detail • Inventory •
Sales Opportunity / Buyer Requirement • Device Matching • Quotation
Builder + Version History • Deposit • Contract • Sales Transaction •
Internal Checklist • QC Form • Delivery • Installation • My Tasks •
Activities / Timeline • Documents • Admin: Users, Roles, Master Data •
Audit Log (authorized users)

## 11. UX Requirements

Desktop-first responsive web application. Optimize for operational
speed: • searchable dropdowns • autocomplete • date pickers • structured
fields instead of unnecessary free text • photo/document upload •
autosave draft where appropriate • status badges • global search by
clinic, contact, phone, model, serial, Device ID, quotation and
transaction reference • clear Next Action on active records • no form
should force users to enter information that is not required at the
current workflow stage

Dashboard priority: Today's Tasks, Overdue Follow-ups, Pending Approvals
and Active Deals before analytics charts.

## 12. Audit, Security & Data Integrity

• Authentication required. • RBAC enforced server-side, not only hidden
in UI. • Record created_by, created_at, updated_by, updated_at. • Audit
old/new values for financial, approval, ownership and status changes. •
Store document metadata and link each file to its parent object. •
Prevent unauthorized price/cost edits. • Use transactional database
operations for workflow transitions that create multiple records. •
Validate foreign keys and status transitions on backend. • Do not expose
sensitive management financial data to unauthorized roles.

## 13. Suggested MVP Boundary

MVP MUST include: Customer/Organization, Contact, Seller/Buyer Lead,
Follow-up, Device Master, Device Opportunity, Inspection, Repair/MA
History, Cost Sheet, Valuation, Negotiation, Approval, Acquisition,
Sales Opportunity, Device Match, Quotation, Deposit, Contract,
Transaction, Checklist, QC, Delivery, Installation, Tasks, Activities,
Documents, RBAC and Audit Log.

Defer unless explicitly approved: advanced accounting/ERP, payroll/HR,
LINE integration, email integration, AI valuation, predictive
days-to-sell, customer portal, automated marketing and advanced KPI
analytics.

## 14. Acceptance Criteria

The MVP is acceptable when users can complete these end-to-end scenarios
without manual external tracking:

Scenario A --- Seller: Create seller/customer → create/link device →
request inspection → complete inspection → record service/MA history →
create cost sheet → valuation → negotiation → GM approval → purchase →
inventory.

Scenario B --- Buyer: Create buyer/customer → record requirement → match
device from inventory OR seller opportunity → quotation → deposit →
contract → mark Won → complete internal checklist → QC PASS → delivery →
installation → completed transaction.

Scenario C --- Follow-up: Create active lead → assign owner/next
action/date → dashboard shows due item → overdue state works → activity
history records follow-up.

Scenario D --- Device History: Open one Device 360 page and retrieve
ownership, inspections, service/MA, costs, valuation, acquisition, buyer
matches, transactions, delivery/installation and documents.

Scenario E --- Control: Unauthorized user cannot approve acquisition or
modify protected financial fields; authorized change is captured in
audit log.

## 15. Implementation Instruction for Coding Agent

Treat this specification as the functional source of truth for AMN Sure
CRM v1.

Before coding: 1. Produce ERD and normalized relational schema. 2.
Produce status/state-transition tables for acquisition and sales
workflows. 3. Produce API/module map. 4. Produce route/page map and
component hierarchy. 5. Identify ambiguities or missing business rules
as explicit questions; do not silently invent financial approval logic.
6. Implement migrations and seed master data. 7. Implement
authentication/RBAC and audit infrastructure before workflow modules. 8.
Build MVP vertically: Customer → Device → Acquisition workflow → Sales
workflow → Tasks/Activity/Documents. 9. Add automated tests for
permission rules and critical status transitions. 10. Provide deployment
instructions, environment-variable template and backup/migration
strategy.

Do not add unrelated ERP modules or redesign the approved business
workflow without documenting the proposed change.
