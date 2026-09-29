<?php
// ค้นหารวม: คลินิก, ผู้ติดต่อ, เบอร์โทร, รุ่น, serial, Device ID, เลขใบเสนอราคา, เลขธุรกรรม ฯลฯ (ผลลัพธ์กรองตามสิทธิ์)

function global_search(string $q): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) return [];
    $l = like($q);
    $digits = preg_replace('/\D/', '', $q);
    $phoneLike = strlen($digits) >= 4 ? like($digits) : null;
    $out = [];

    if (can('customer.view')) {
        $p = [$l, $l, $l, $l];
        $phoneSql = '';
        if ($phoneLike) { $phoneSql = " OR REPLACE(REPLACE(phone, '-', ''), ' ', '') LIKE ?"; $p[] = $phoneLike; }
        foreach (all("SELECT id, ref_no, name, phone, province FROM organizations WHERE archived_at IS NULL AND (name LIKE ? OR ref_no LIKE ? OR tax_id LIKE ? OR line_id LIKE ?$phoneSql) ORDER BY name LIMIT 20", $p) as $r) {
            $out[] = ['type' => 'ลูกค้า', 'title' => $r['name'], 'sub' => trim($r['ref_no'] . ' · ' . ($r['phone'] ?? '') . ' ' . ($r['province'] ?? '')), 'url' => url('customers.view', ['id' => $r['id']])];
        }
        $p = [$l, $l, $l];
        $phoneSql = '';
        if ($phoneLike) { $phoneSql = " OR REPLACE(REPLACE(c.phone, '-', ''), ' ', '') LIKE ?"; $p[] = $phoneLike; }
        foreach (all("SELECT c.id, c.name, c.phone, o.name AS org FROM contacts c LEFT JOIN organizations o ON o.id = c.organization_id
                      WHERE c.archived_at IS NULL AND (c.name LIKE ? OR c.email LIKE ? OR c.line_id LIKE ?$phoneSql) ORDER BY c.name LIMIT 20", $p) as $r) {
            $out[] = ['type' => 'ผู้ติดต่อ', 'title' => $r['name'], 'sub' => trim(($r['org'] ?? '') . ' · ' . ($r['phone'] ?? '')), 'url' => url('contacts.view', ['id' => $r['id']])];
        }
    }
    if (can('device.view')) {
        $norm = normalize_serial($q);
        foreach (all('SELECT id, ref_no, brand, model, serial_number, commercial_status FROM devices
                      WHERE ref_no LIKE ? OR serial_number LIKE ? OR serial_normalized LIKE ? OR brand LIKE ? OR model LIKE ? OR CONCAT(brand, \' \', model) LIKE ?
                      ORDER BY brand, model LIMIT 30', [$l, $l, like((string) $norm), $l, $l, $l]) as $r) {
            $out[] = ['type' => 'เครื่อง', 'title' => $r['brand'] . ' ' . $r['model'], 'sub' => $r['ref_no'] . ($r['serial_number'] ? ' · S/N ' . $r['serial_number'] : '') . ' · ' . label('commercial', $r['commercial_status']), 'url' => url('devices.view', ['id' => $r['id']])];
        }
    }
    if (can('lead.view')) {
        foreach (all('SELECT o.id, o.ref_no, o.status, org.name FROM device_opportunities o JOIN organizations org ON org.id = o.seller_org_id WHERE o.ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'ดีลซื้อ', 'title' => $r['ref_no'], 'sub' => $r['name'] . ' · ' . label('status', $r['status']), 'url' => url('acq.view', ['id' => $r['id']])];
        }
        foreach (all('SELECT l.id, l.ref_no, l.type, org.name FROM leads l JOIN organizations org ON org.id = l.organization_id WHERE l.ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'ลีด', 'title' => $r['ref_no'], 'sub' => $r['name'] . ' · ' . label('lead_type', $r['type']), 'url' => url('leads.view', ['id' => $r['id']])];
        }
    }
    if (can('sales.view')) {
        foreach (all('SELECT s.id, s.ref_no, s.status, org.name FROM sales_opportunities s JOIN organizations org ON org.id = s.buyer_org_id WHERE s.ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'ดีลขาย', 'title' => $r['ref_no'], 'sub' => $r['name'] . ' · ' . label('status', $r['status']), 'url' => url('sales.view', ['id' => $r['id']])];
        }
    }
    if (can('quotation.view')) {
        foreach (all('SELECT q.id, q.ref_no, org.name FROM quotations q JOIN sales_opportunities s ON s.id = q.sales_opportunity_id JOIN organizations org ON org.id = s.buyer_org_id WHERE q.ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'ใบเสนอราคา', 'title' => $r['ref_no'], 'sub' => $r['name'], 'url' => url('quotations.view', ['id' => $r['id']])];
        }
    }
    if (can('contract.view')) {
        foreach (all('SELECT c.id, c.contract_no, c.sales_opportunity_id, org.name FROM contracts c JOIN sales_opportunities s ON s.id = c.sales_opportunity_id JOIN organizations org ON org.id = s.buyer_org_id WHERE c.contract_no LIKE ? OR c.ref_no LIKE ? LIMIT 10', [$l, $l]) as $r) {
            $out[] = ['type' => 'สัญญา', 'title' => $r['contract_no'], 'sub' => $r['name'], 'url' => url('sales.view', ['id' => $r['sales_opportunity_id']])];
        }
    }
    if (can('transaction.view')) {
        foreach (all('SELECT t.id, t.ref_no, t.status, org.name FROM sales_transactions t JOIN organizations org ON org.id = t.buyer_org_id WHERE t.ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'ธุรกรรมขาย', 'title' => $r['ref_no'], 'sub' => $r['name'] . ' · ' . label('status', $r['status']), 'url' => url('trx.view', ['id' => $r['id']])];
        }
    }
    if (can('inventory.view')) {
        foreach (all('SELECT i.ref_no, i.device_id, dv.brand, dv.model FROM inventory i JOIN devices dv ON dv.id = i.device_id WHERE i.ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'สต็อก', 'title' => $r['ref_no'], 'sub' => $r['brand'] . ' ' . $r['model'], 'url' => url('devices.view', ['id' => $r['device_id']])];
        }
    }
    if (can('job.view')) {
        foreach (all('SELECT id, ref_no, title FROM technical_jobs WHERE ref_no LIKE ? LIMIT 10', [$l]) as $r) {
            $out[] = ['type' => 'ใบงานช่าง', 'title' => $r['ref_no'], 'sub' => $r['title'], 'url' => url('jobs.view', ['id' => $r['id']])];
        }
    }
    return $out;
}
