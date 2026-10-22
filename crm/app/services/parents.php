<?php
// ข้อมูลกลางของ record ที่ Task / Activity / Document ผูกไปได้ (polymorphic parent)
// FK แบบ polymorphic บังคับใน DB ไม่ได้ จึงตรวจการมีอยู่และสิทธิ์ที่นี่ทุกครั้ง

// type => [table, สิทธิ์ดู (อย่างใดอย่างหนึ่ง), สิทธิ์เพิ่มไฟล์/กิจกรรม (อย่างใดอย่างหนึ่ง)]
const PARENT_TYPES = [
    'ORGANIZATION' => ['organizations', ['customer.view'], ['customer.edit']],
    'CONTACT' => ['contacts', ['customer.view'], ['customer.edit']],
    'LEAD' => ['leads', ['lead.view'], ['lead.edit']],
    'DEVICE' => ['devices', ['device.view'], ['device.edit', 'service_history.edit']],
    'DEVICE_OPPORTUNITY' => ['device_opportunities', ['lead.view'], ['lead.edit', 'inspection.edit', 'negotiation.edit', 'cost_sheet.edit', 'acquisition.create']],
    'INSPECTION' => ['inspections', ['inspection.view'], ['inspection.edit']],
    'COST_SHEET' => ['cost_sheets', ['finance.view'], ['cost_sheet.edit']],
    'ACQUISITION' => ['acquisitions', ['lead.view'], ['acquisition.create']],
    'SALES_OPPORTUNITY' => ['sales_opportunities', ['sales.view'], ['sales.edit', 'quotation.edit', 'contract.edit', 'deposit.edit']],
    'QUOTATION' => ['quotations', ['quotation.view'], ['quotation.edit']],
    'DEPOSIT' => ['deposits', ['deposit.view'], ['deposit.edit']],
    'CONTRACT' => ['contracts', ['contract.view'], ['contract.edit']],
    'SALES_TRANSACTION' => ['sales_transactions', ['transaction.view'], ['checklist.COMMERCIAL', 'checklist.SALES', 'checklist.MARKETING', 'checklist.TECHNICAL', 'qc.edit', 'delivery.edit', 'installation.edit']],
    'TECHNICAL_JOB' => ['technical_jobs', ['job.view'], ['job.edit']],
    'SERVICE_CASE' => ['service_cases', ['service_case.view'], ['service_case.edit']],
];

function parent_row(string $type, string $id): array
{
    if (!isset(PARENT_TYPES[$type])) throw new NotFoundError('ประเภทข้อมูลไม่ถูกต้อง');
    return db_get(PARENT_TYPES[$type][0], $id);
}

function parent_require_view(string $type, string $id): array
{
    if (!isset(PARENT_TYPES[$type])) throw new NotFoundError('ประเภทข้อมูลไม่ถูกต้อง');
    require_cap(...PARENT_TYPES[$type][1]);
    return parent_row($type, $id);
}

function parent_require_edit(string $type, string $id): array
{
    if (!isset(PARENT_TYPES[$type])) throw new NotFoundError('ประเภทข้อมูลไม่ถูกต้อง');
    require_cap(...PARENT_TYPES[$type][2]);
    return parent_row($type, $id);
}

function parent_can_edit(string $type): bool
{
    return isset(PARENT_TYPES[$type]) && can_any(...PARENT_TYPES[$type][2]);
}

/** organization_id / device_id ที่เกี่ยวข้อง (ใช้ให้ timeline หน้า Customer 360 / Device 360 ครบ) */
function parent_context(string $type, array $row): array
{
    switch ($type) {
        case 'ORGANIZATION': return ['organization_id' => $row['id'], 'device_id' => null];
        case 'CONTACT':
        case 'LEAD': return ['organization_id' => $row['organization_id'], 'device_id' => null];
        case 'DEVICE': return ['organization_id' => null, 'device_id' => $row['id']];
        case 'DEVICE_OPPORTUNITY': return ['organization_id' => $row['seller_org_id'], 'device_id' => $row['device_id']];
        case 'ACQUISITION': return ['organization_id' => $row['seller_org_id'], 'device_id' => $row['device_id']];
        case 'INSPECTION':
            $org = $row['device_opportunity_id'] ? val('SELECT seller_org_id FROM device_opportunities WHERE id = ?', [$row['device_opportunity_id']]) : null;
            return ['organization_id' => $org, 'device_id' => $row['device_id']];
        case 'COST_SHEET':
            $o = one('SELECT seller_org_id, device_id FROM device_opportunities WHERE id = ?', [$row['device_opportunity_id']]);
            return ['organization_id' => $o['seller_org_id'] ?? null, 'device_id' => $o['device_id'] ?? null];
        case 'SALES_OPPORTUNITY': return ['organization_id' => $row['buyer_org_id'], 'device_id' => null];
        case 'QUOTATION':
        case 'DEPOSIT':
        case 'CONTRACT':
            return ['organization_id' => val('SELECT buyer_org_id FROM sales_opportunities WHERE id = ?', [$row['sales_opportunity_id']]), 'device_id' => null];
        case 'SALES_TRANSACTION': return ['organization_id' => $row['buyer_org_id'], 'device_id' => null];
        case 'TECHNICAL_JOB': return ['organization_id' => null, 'device_id' => $row['device_id']];
        case 'SERVICE_CASE': return ['organization_id' => $row['organization_id'], 'device_id' => $row['device_id']];
    }
    return ['organization_id' => null, 'device_id' => null];
}

/** ลิงก์กลับไปหน้าของ record */
function parent_url(string $type, string $id): string
{
    switch ($type) {
        case 'ORGANIZATION': return url('customers.view', ['id' => $id]);
        case 'CONTACT': return url('contacts.view', ['id' => $id]);
        case 'LEAD': return url('leads.view', ['id' => $id]);
        case 'DEVICE': return url('devices.view', ['id' => $id]);
        case 'DEVICE_OPPORTUNITY': return url('acq.view', ['id' => $id]);
        case 'INSPECTION': return url('acq.inspection', ['id' => $id]);
        case 'COST_SHEET': return url('acq.view', ['id' => val('SELECT device_opportunity_id FROM cost_sheets WHERE id = ?', [$id])]);
        case 'ACQUISITION': return url('acq.view', ['id' => val('SELECT device_opportunity_id FROM acquisitions WHERE id = ?', [$id])]);
        case 'SALES_OPPORTUNITY': return url('sales.view', ['id' => $id]);
        case 'QUOTATION': return url('quotations.view', ['id' => $id]);
        case 'DEPOSIT': return url('sales.view', ['id' => val('SELECT sales_opportunity_id FROM deposits WHERE id = ?', [$id])]) . '#deposits';
        case 'CONTRACT': return url('sales.view', ['id' => val('SELECT sales_opportunity_id FROM contracts WHERE id = ?', [$id])]) . '#contracts';
        case 'SALES_TRANSACTION': return url('trx.view', ['id' => $id]);
        case 'TECHNICAL_JOB': return url('jobs.view', ['id' => $id]);
        case 'SERVICE_CASE': return url('cases.view', ['id' => $id]);
    }
    return url('dashboard');
}

/** ชื่อสั้นของ record สำหรับแสดงในรายการงาน/เอกสาร */
function parent_title(string $type, ?string $id): string
{
    if (!$id || !isset(PARENT_TYPES[$type])) return '';
    $row = db_find(PARENT_TYPES[$type][0], $id);
    if (!$row) return '';
    $name = label('entity', $type);
    if (!empty($row['ref_no'])) return $name . ' ' . $row['ref_no'];
    if (!empty($row['name'])) return $name . ': ' . $row['name'];
    return $name;
}
