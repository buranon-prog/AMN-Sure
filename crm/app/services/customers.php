<?php
// ลูกค้า/องค์กร และผู้ติดต่อ

const ORG_TYPES = ['CLINIC', 'HOSPITAL', 'DEALER', 'INDIVIDUAL', 'OTHER'];

function org_validate(array $d): array
{
    $name = s($d['name'] ?? '');
    if (!$name) throw new AppError('กรุณากรอกชื่อลูกค้า/องค์กร');
    $owner = v_uuid($d['account_owner_id'] ?? null, 'ผู้ดูแลลูกค้า', false);
    if ($owner && !val('SELECT id FROM users WHERE id = ?', [$owner])) throw new AppError('ไม่พบผู้ดูแลลูกค้า');
    return [
        'name' => v_maxlen($name, 255, 'ชื่อ'),
        'type' => v_in($d['type'] ?? 'CLINIC', ORG_TYPES, 'ประเภทลูกค้า'),
        'tax_id' => v_maxlen(s($d['tax_id'] ?? null), 20, 'เลขประจำตัวผู้เสียภาษี'),
        'branch' => v_maxlen(s($d['branch'] ?? null), 100, 'สาขา'),
        'phone' => v_maxlen(s($d['phone'] ?? null), 50, 'โทรศัพท์'),
        'line_id' => v_maxlen(s($d['line_id'] ?? null), 100, 'LINE ID'),
        'email' => v_email($d['email'] ?? null, 'อีเมล'),
        'address' => s($d['address'] ?? null),
        'province' => v_maxlen(s($d['province'] ?? null), 100, 'จังหวัด'),
        'account_owner_id' => $owner,
        'notes' => s($d['notes'] ?? null),
    ];
}

/** ลูกค้าที่อาจซ้ำ: ชื่อเหมือนกัน หรือเบอร์โทรเดียวกัน */
function org_find_duplicates(?string $name, ?string $phone, ?string $excludeId = null): array
{
    $cond = [];
    $p = [];
    if ($name) { $cond[] = 'LOWER(TRIM(name)) = ?'; $p[] = mb_strtolower(trim($name)); }
    $digits = $phone ? preg_replace('/\D/', '', $phone) : '';
    if (strlen($digits) >= 6) { $cond[] = "REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+66', '0') = ?"; $p[] = preg_replace('/^66/', '0', $digits); }
    if (!$cond) return [];
    $sql = 'SELECT id, ref_no, name, phone FROM organizations WHERE archived_at IS NULL AND (' . implode(' OR ', $cond) . ')';
    if ($excludeId) { $sql .= ' AND id <> ?'; $p[] = $excludeId; }
    return all($sql . ' LIMIT 10', $p);
}

function org_create(array $d, bool $confirmDuplicate = false): string
{
    require_cap('customer.edit');
    $data = org_validate($d);
    if (!$data['account_owner_id']) $data['account_owner_id'] = current_user_id();
    if (!$confirmDuplicate) {
        $dups = org_find_duplicates($data['name'], $data['phone']);
        if ($dups) {
            throw new AppError('อาจเป็นลูกค้าซ้ำกับ: ' . implode(', ', array_map(function ($r) { return $r['name'] . ' (' . $r['ref_no'] . ')'; }, $dups))
                . ' — ถ้าเป็นคนละรายจริง ให้ติ๊ก "ยืนยันว่าไม่ซ้ำ" แล้วบันทึกอีกครั้ง');
        }
    }
    return tx(function () use ($data) {
        $data['ref_no'] = next_ref('ORG', false, 6);
        return insert_audited('organizations', 'ORGANIZATION', $data);
    });
}

function org_update(string $id, array $d): void
{
    require_cap('customer.edit');
    $org = db_get('organizations', $id, 'ลูกค้า');
    if ($org['archived_at']) throw new AppError('ลูกค้ารายนี้ถูกเก็บเข้าคลังแล้ว');
    update_audited('organizations', 'ORGANIZATION', $id, org_validate($d));
}

function org_archive(string $id, string $reason): void
{
    require_cap('customer.edit');
    $reason = s($reason);
    if (!$reason) throw new AppError('กรุณาระบุเหตุผล');
    $active = (int) val("SELECT COUNT(*) FROM device_opportunities WHERE seller_org_id = ? AND status NOT IN ('PURCHASED','REJECTED','LOST')", [$id])
        + (int) val("SELECT COUNT(*) FROM sales_opportunities WHERE buyer_org_id = ? AND status NOT IN ('WON','LOST')", [$id]);
    if ($active) throw new AppError('ลูกค้ารายนี้ยังมีดีลที่ยังไม่ปิด ปิดดีลก่อนจึงจะเก็บเข้าคลังได้');
    update_audited('organizations', 'ORGANIZATION', $id, ['archived_at' => now(), 'archived_by' => current_user_id(), 'archive_reason' => $reason], 'ARCHIVE');
}

// ---------------------------------------------------------------- contacts

function contact_validate(array $d): array
{
    $name = s($d['name'] ?? '');
    if (!$name) throw new AppError('กรุณากรอกชื่อผู้ติดต่อ');
    $org = v_uuid($d['organization_id'] ?? null, 'องค์กร', false);
    if ($org && !val('SELECT id FROM organizations WHERE id = ?', [$org])) throw new AppError('ไม่พบองค์กร');
    return [
        'organization_id' => $org,
        'name' => v_maxlen($name, 150, 'ชื่อผู้ติดต่อ'),
        'position' => v_maxlen(s($d['position'] ?? null), 100, 'ตำแหน่ง'),
        'phone' => v_maxlen(s($d['phone'] ?? null), 50, 'โทรศัพท์'),
        'line_id' => v_maxlen(s($d['line_id'] ?? null), 100, 'LINE ID'),
        'email' => v_email($d['email'] ?? null, 'อีเมล'),
        'is_primary' => !empty($d['is_primary']) && $d['is_primary'] !== '0' ? 1 : 0,
        'notes' => s($d['notes'] ?? null),
    ];
}

function contact_create(array $d): string
{
    require_cap('customer.edit');
    $data = contact_validate($d);
    return tx(function () use ($data) {
        if ($data['is_primary'] && $data['organization_id']) {
            q('UPDATE contacts SET is_primary = 0 WHERE organization_id = ?', [$data['organization_id']]);
        }
        return insert_audited('contacts', 'CONTACT', $data);
    });
}

function contact_update(string $id, array $d): void
{
    require_cap('customer.edit');
    db_get('contacts', $id, 'ผู้ติดต่อ');
    $data = contact_validate($d);
    tx(function () use ($id, $data) {
        if ($data['is_primary'] && $data['organization_id']) {
            q('UPDATE contacts SET is_primary = 0 WHERE organization_id = ? AND id <> ?', [$data['organization_id'], $id]);
        }
        update_audited('contacts', 'CONTACT', $id, $data);
    });
}

function contact_archive(string $id, string $reason): void
{
    require_cap('customer.edit');
    $reason = s($reason) ?? 'ไม่ได้ติดต่อแล้ว';
    update_audited('contacts', 'CONTACT', $id, ['archived_at' => now(), 'archived_by' => current_user_id(), 'archive_reason' => $reason], 'ARCHIVE');
}

/**
 * ใช้ในฟอร์มลีด: เลือกลูกค้าเดิม (organization_id) หรือสร้างใหม่ (new_org_*)
 * และเลือกผู้ติดต่อเดิม (contact_id) หรือสร้างใหม่ (new_contact_*)
 * คืน [org_id, contact_id]
 */
function resolve_org_and_contact(array $d): array
{
    $orgId = v_uuid($d['organization_id'] ?? null, 'ลูกค้า', false);
    if ($orgId) {
        $org = db_get('organizations', $orgId, 'ลูกค้า');
        if ($org['archived_at']) throw new AppError('ลูกค้ารายนี้ถูกเก็บเข้าคลังแล้ว');
    } else {
        if (!s($d['new_org_name'] ?? '')) throw new AppError('กรุณาเลือกลูกค้าเดิม หรือกรอกชื่อลูกค้าใหม่');
        $orgId = org_create([
            'name' => $d['new_org_name'],
            'type' => $d['new_org_type'] ?? 'CLINIC',
            'phone' => $d['new_org_phone'] ?? null,
            'line_id' => $d['new_org_line'] ?? null,
            'email' => $d['new_org_email'] ?? null,
            'province' => $d['new_org_province'] ?? null,
            'account_owner_id' => $d['owner_id'] ?? null,
        ], !empty($d['confirm_org_duplicate']) && $d['confirm_org_duplicate'] !== '0');
    }

    $contactId = v_uuid($d['contact_id'] ?? null, 'ผู้ติดต่อ', false);
    if ($contactId) {
        $c = db_get('contacts', $contactId, 'ผู้ติดต่อ');
        if ($c['organization_id'] && $c['organization_id'] !== $orgId) throw new AppError('ผู้ติดต่อที่เลือกไม่ได้อยู่ในองค์กรนี้');
    } elseif (s($d['new_contact_name'] ?? '')) {
        $contactId = contact_create([
            'organization_id' => $orgId,
            'name' => $d['new_contact_name'],
            'position' => $d['new_contact_position'] ?? null,
            'phone' => $d['new_contact_phone'] ?? null,
            'line_id' => $d['new_contact_line'] ?? null,
            'email' => $d['new_contact_email'] ?? null,
            'is_primary' => !val('SELECT id FROM contacts WHERE organization_id = ? AND archived_at IS NULL', [$orgId]) ? 1 : 0,
        ]);
    }
    return [$orgId, $contactId];
}

/** มีช่องทางติดต่ออย่างน้อยหนึ่งช่องทาง (โทร / LINE / อีเมล) จากผู้ติดต่อหรือองค์กร */
function has_contact_channel(string $orgId, ?string $contactId): bool
{
    $o = one('SELECT phone, line_id, email FROM organizations WHERE id = ?', [$orgId]);
    if ($o && ($o['phone'] || $o['line_id'] || $o['email'])) return true;
    if ($contactId) {
        $c = one('SELECT phone, line_id, email FROM contacts WHERE id = ?', [$contactId]);
        if ($c && ($c['phone'] || $c['line_id'] || $c['email'])) return true;
    }
    return false;
}

// ---------------------------------------------------------------- Customer 360

function customer_360(string $id): array
{
    require_cap('customer.view');
    $org = db_get('organizations', $id, 'ลูกค้า');
    $d = ['org' => $org];
    $d['contacts'] = all('SELECT * FROM contacts WHERE organization_id = ? AND archived_at IS NULL ORDER BY is_primary DESC, name', [$id]);
    $d['owner_name'] = user_name($org['account_owner_id']);

    $d['leads'] = can('lead.view') ? all('SELECT l.*, u.name AS owner_name FROM leads l JOIN users u ON u.id = l.owner_id WHERE l.organization_id = ? ORDER BY l.created_at DESC', [$id]) : [];
    $d['seller_opps'] = can('lead.view') ? all('SELECT o.*, dv.brand, dv.model, dv.serial_number, dv.ref_no AS device_ref, u.name AS owner_name
        FROM device_opportunities o JOIN devices dv ON dv.id = o.device_id JOIN users u ON u.id = o.owner_id
        WHERE o.seller_org_id = ? ORDER BY o.created_at DESC', [$id]) : [];
    $d['buyer_opps'] = can('sales.view') ? all('SELECT s.*, u.name AS owner_name FROM sales_opportunities s JOIN users u ON u.id = s.owner_id
        WHERE s.buyer_org_id = ? ORDER BY s.created_at DESC', [$id]) : [];
    $d['quotations'] = can('quotation.view') ? all('SELECT qt.*, qv.status AS version_status, qv.total, qv.valid_until, s.ref_no AS so_ref
        FROM quotations qt JOIN sales_opportunities s ON s.id = qt.sales_opportunity_id
        JOIN quotation_versions qv ON qv.quotation_id = qt.id AND qv.version_no = qt.current_version_no
        WHERE s.buyer_org_id = ? ORDER BY qt.created_at DESC', [$id]) : [];
    $d['transactions'] = can('transaction.view') ? all('SELECT * FROM sales_transactions WHERE buyer_org_id = ? ORDER BY won_at DESC', [$id]) : [];
    $d['acquisitions'] = can('lead.view') ? all('SELECT a.*, dv.brand, dv.model, dv.ref_no AS device_ref FROM acquisitions a JOIN devices dv ON dv.id = a.device_id
        WHERE a.seller_org_id = ? ORDER BY a.purchase_date DESC', [$id]) : [];
    $d['devices_owned'] = can('device.view') ? all('SELECT * FROM devices WHERE current_owner_org_id = ? AND archived_at IS NULL ORDER BY brand, model', [$id]) : [];
    $d['devices_previous'] = can('device.view') ? all('SELECT dv.*, ow.from_date, ow.to_date FROM device_ownerships ow JOIN devices dv ON dv.id = ow.device_id
        WHERE ow.organization_id = ? AND ow.to_date IS NOT NULL ORDER BY ow.to_date DESC', [$id]) : [];
    $d['service_cases'] = can('service_case.view') ? all('SELECT sc.*, dv.brand, dv.model FROM service_cases sc JOIN devices dv ON dv.id = sc.device_id
        WHERE sc.organization_id = ? ORDER BY sc.reported_at DESC', [$id]) : [];
    $d['activities'] = all('SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id = a.user_id
        WHERE a.organization_id = ? ORDER BY a.occurred_at DESC LIMIT 200', [$id]);
    $d['documents'] = documents_for_org($id);
    $d['last_activity'] = val("SELECT MAX(occurred_at) FROM activities WHERE organization_id = ? AND type <> 'SYSTEM'", [$id]);

    // Next action = ดีลที่ยัง active และมีวันติดตามใกล้ที่สุด
    $next = [];
    foreach ($d['seller_opps'] as $o) {
        if (!in_array($o['status'], DO_CLOSED, true)) $next[] = ['date' => $o['next_follow_up_date'], 'action' => $o['next_action'], 'ref' => $o['ref_no'], 'url' => url('acq.view', ['id' => $o['id']])];
    }
    foreach ($d['buyer_opps'] as $o) {
        if (!in_array($o['status'], SO_CLOSED, true)) $next[] = ['date' => $o['next_follow_up_date'], 'action' => $o['next_action'], 'ref' => $o['ref_no'], 'url' => url('sales.view', ['id' => $o['id']])];
    }
    usort($next, function ($a, $b) { return strcmp($a['date'] ?? '9999', $b['date'] ?? '9999'); });
    $d['next'] = $next[0] ?? null;
    return $d;
}
