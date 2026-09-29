<?php
// ลีด (หัวเรื่องการติดต่อ) + การติดตาม (follow-up) ของดีลซื้อ/ดีลขาย
// BR-01: ดีลที่ยัง active ต้องมีผู้รับผิดชอบ + next action + วันติดตามครั้งถัดไป
// OVERDUE ไม่เก็บใน DB: คำนวณจาก active && next_follow_up_date < วันนี้

function validate_followup(array $d, bool $required = true): array
{
    $owner = v_uuid($d['owner_id'] ?? null, 'ผู้รับผิดชอบ', false) ?? current_user_id();
    if (!val('SELECT id FROM users WHERE id = ? AND active = 1', [$owner])) throw new AppError('ผู้รับผิดชอบต้องเป็นผู้ใช้ที่ยังใช้งานอยู่');
    $action = v_maxlen(s($d['next_action'] ?? null), 255, 'Next action');
    $date = v_date($d['next_follow_up_date'] ?? null, 'วันติดตามครั้งถัดไป');
    if ($required) {
        $missing = [];
        if (!$action) $missing[] = 'Next action (สิ่งที่ต้องทำต่อ)';
        if (!$date) $missing[] = 'วันติดตามครั้งถัดไป';
        if ($missing) throw new AppError('ดีลที่ยังดำเนินการอยู่ต้องมี ' . implode(' และ ', $missing) . ' (BR-01)');
    }
    return ['owner_id' => $owner, 'next_action' => $action, 'next_follow_up_date' => $date];
}

function lead_source_validate($v): string
{
    $v = s($v);
    if (!$v) throw new AppError('กรุณาเลือกแหล่งที่มาของลีด');
    if (!val("SELECT id FROM master_data WHERE type = 'LEAD_SOURCE' AND code = ?", [$v])) throw new AppError('แหล่งที่มาของลีดไม่ถูกต้อง');
    return $v;
}

/**
 * ลีดผู้ขาย: ลูกค้าอยากขายเครื่องให้ AMN Sure
 * สร้าง lead + (ผูกเครื่องเดิม หรือสร้างเครื่องใหม่) + ดีลซื้อ สถานะ NEW_SELLER_LEAD
 * คืน id ของดีลซื้อ
 */
function lead_create_seller(array $d): string
{
    require_cap('lead.edit');
    $fu = validate_followup($d);
    $source = lead_source_validate($d['source'] ?? null);
    $expected = v_money($d['expected_price'] ?? null, 'ราคาที่ผู้ขายต้องการ', true);
    $location = v_maxlen(s($d['location'] ?? null), 255, 'สถานที่ตั้งเครื่อง');
    if (!$location) throw new AppError('กรุณากรอกสถานที่ตั้งเครื่อง');
    $deviceId = v_uuid($d['device_id'] ?? null, 'เครื่อง', false);
    if (!$deviceId && (!s($d['brand'] ?? null) || !s($d['model'] ?? null))) throw new AppError('กรุณากรอกยี่ห้อและรุ่นของเครื่อง (หรือเลือกเครื่องที่มีอยู่แล้ว)');

    return tx(function () use ($d, $fu, $source, $expected, $location, $deviceId) {
        [$orgId, $contactId] = resolve_org_and_contact($d + ['owner_id' => $fu['owner_id']]);
        if (!has_contact_channel($orgId, $contactId)) {
            throw new AppError('ต้องมีช่องทางติดต่อผู้ขายอย่างน้อย 1 ช่องทาง (โทรศัพท์ / LINE / อีเมล)');
        }
        if ($deviceId) {
            $device = db_get('devices', $deviceId, 'เครื่อง');
            $open = one("SELECT ref_no FROM device_opportunities WHERE device_id = ? AND status NOT IN ('PURCHASED','REJECTED','LOST')", [$deviceId]);
            if ($open) throw new AppError('เครื่องนี้มีดีลซื้อที่ยังไม่ปิดอยู่แล้ว (' . $open['ref_no'] . ')');
            if ((int) $device['owned_by_amn'] === 1) throw new AppError('เครื่องนี้เป็นของ AMN Sure อยู่แล้ว');
        } else {
            $deviceId = device_create_internal(device_validate($d + ['current_location' => $location]), !empty($d['confirm_duplicate']) && $d['confirm_duplicate'] !== '0', $orgId);
        }

        $leadId = db_insert('leads', [
            'ref_no' => next_ref('LD'),
            'type' => 'SELLER',
            'organization_id' => $orgId,
            'contact_id' => $contactId,
            'source' => $source,
            'owner_id' => $fu['owner_id'],
            'status' => 'ACTIVE',
            'summary' => s($d['summary'] ?? null),
        ]);
        audit_create('LEAD', $leadId, ['type' => 'SELLER', 'organization_id' => $orgId, 'source' => $source, 'owner_id' => $fu['owner_id']]);

        $oppId = device_opp_create_internal($leadId, $deviceId, $orgId, $contactId, $fu, [
            'expected_price' => $expected,
            'asking_price' => v_money($d['asking_price'] ?? null, 'ราคาที่ผู้ขายตั้ง') ?? $expected,
            'location' => $location,
            'reason_for_sale' => v_maxlen(s($d['reason_for_sale'] ?? null), 255, 'เหตุผลที่ขาย'),
        ]);
        log_activity('LEAD', $leadId, 'SYSTEM', 'สร้างลีดผู้ขาย', ['organization_id' => $orgId, 'device_id' => $deviceId]);
        return $oppId;
    });
}

/** สร้างดีลซื้อ (ใช้จากลีดใหม่ หรือเพิ่มเครื่องในลีดเดิม) */
function device_opp_create_internal(string $leadId, string $deviceId, string $orgId, ?string $contactId, array $fu, array $extra): string
{
    $data = [
        'ref_no' => next_ref('DO'),
        'device_id' => $deviceId,
        'lead_id' => $leadId,
        'seller_org_id' => $orgId,
        'seller_contact_id' => $contactId,
        'owner_id' => $fu['owner_id'],
        'status' => 'NEW_SELLER_LEAD',
        'next_action' => $fu['next_action'],
        'next_follow_up_date' => $fu['next_follow_up_date'],
    ] + $extra;
    $id = insert_audited('device_opportunities', 'DEVICE_OPPORTUNITY', $data);
    update_audited('devices', 'DEVICE', $deviceId, ['commercial_status' => 'UNDER_OFFER', 'current_location' => $extra['location'] ?? null], 'STATUS_CHANGE', 'เปิดดีลซื้อ ' . $data['ref_no']);
    log_activity('DEVICE_OPPORTUNITY', $id, 'SYSTEM', 'สร้างดีลซื้อ ' . $data['ref_no'] . ' (ลีดผู้ขายใหม่)', ['organization_id' => $orgId, 'device_id' => $deviceId]);
    return $id;
}

/** เพิ่มเครื่องอีกเครื่องในลีดผู้ขายเดิม */
function lead_add_device(string $leadId, array $d): string
{
    require_cap('lead.edit');
    $lead = db_get('leads', $leadId, 'ลีด');
    if ($lead['type'] !== 'SELLER') throw new AppError('เพิ่มเครื่องได้เฉพาะลีดผู้ขาย');
    $fu = validate_followup($d);
    $expected = v_money($d['expected_price'] ?? null, 'ราคาที่ผู้ขายต้องการ', true);
    $location = s($d['location'] ?? null);
    if (!$location) throw new AppError('กรุณากรอกสถานที่ตั้งเครื่อง');
    return tx(function () use ($lead, $d, $fu, $expected, $location) {
        $deviceId = v_uuid($d['device_id'] ?? null, 'เครื่อง', false);
        if ($deviceId) {
            db_get('devices', $deviceId, 'เครื่อง');
            $open = one("SELECT ref_no FROM device_opportunities WHERE device_id = ? AND status NOT IN ('PURCHASED','REJECTED','LOST')", [$deviceId]);
            if ($open) throw new AppError('เครื่องนี้มีดีลซื้อที่ยังไม่ปิดอยู่แล้ว (' . $open['ref_no'] . ')');
        } else {
            $deviceId = device_create_internal(device_validate($d + ['current_location' => $location]), !empty($d['confirm_duplicate']) && $d['confirm_duplicate'] !== '0', $lead['organization_id']);
        }
        if ($lead['status'] !== 'ACTIVE') update_audited('leads', 'LEAD', $lead['id'], ['status' => 'ACTIVE'], 'STATUS_CHANGE');
        return device_opp_create_internal($lead['id'], $deviceId, $lead['organization_id'], $lead['contact_id'], $fu, [
            'expected_price' => $expected,
            'asking_price' => v_money($d['asking_price'] ?? null, 'ราคาที่ผู้ขายตั้ง') ?? $expected,
            'location' => $location,
            'reason_for_sale' => s($d['reason_for_sale'] ?? null),
        ]);
    });
}

/**
 * ลีดผู้ซื้อ: ลูกค้าสนใจซื้อเครื่อง
 * สร้าง lead + ดีลขาย สถานะ NEW_BUYER_LEAD คืน id ของดีลขาย
 */
function lead_create_buyer(array $d): string
{
    require_cap('lead.edit');
    $fu = validate_followup($d);
    $source = lead_source_validate($d['source'] ?? null);
    $interested = v_maxlen(s($d['interested_device'] ?? null), 255, 'เครื่องที่สนใจ');
    if (!$interested) throw new AppError('กรุณากรอกเครื่องที่ลูกค้าสนใจ');
    $budget = v_money($d['budget_max'] ?? null, 'งบประมาณ');
    $expectedDate = v_date($d['expected_purchase_date'] ?? null, 'วันที่คาดว่าจะซื้อ');

    return tx(function () use ($d, $fu, $source, $interested, $budget, $expectedDate) {
        [$orgId, $contactId] = resolve_org_and_contact($d + ['owner_id' => $fu['owner_id']]);
        if (!has_contact_channel($orgId, $contactId)) {
            throw new AppError('ต้องมีช่องทางติดต่อผู้ซื้ออย่างน้อย 1 ช่องทาง (โทรศัพท์ / LINE / อีเมล)');
        }
        $leadId = db_insert('leads', [
            'ref_no' => next_ref('LD'),
            'type' => 'BUYER',
            'organization_id' => $orgId,
            'contact_id' => $contactId,
            'source' => $source,
            'owner_id' => $fu['owner_id'],
            'status' => 'ACTIVE',
            'summary' => s($d['summary'] ?? null),
        ]);
        audit_create('LEAD', $leadId, ['type' => 'BUYER', 'organization_id' => $orgId, 'source' => $source, 'owner_id' => $fu['owner_id']]);
        $soData = [
            'ref_no' => next_ref('SO'),
            'lead_id' => $leadId,
            'buyer_org_id' => $orgId,
            'contact_id' => $contactId,
            'owner_id' => $fu['owner_id'],
            'status' => 'NEW_BUYER_LEAD',
            'interested_device' => $interested,
            'budget_max' => $budget,
            'expected_purchase_date' => $expectedDate,
            'timeline' => v_maxlen(s($d['timeline'] ?? null), 150, 'ระยะเวลา'),
            'next_action' => $fu['next_action'],
            'next_follow_up_date' => $fu['next_follow_up_date'],
        ];
        $soId = insert_audited('sales_opportunities', 'SALES_OPPORTUNITY', $soData);
        log_activity('LEAD', $leadId, 'SYSTEM', 'สร้างลีดผู้ซื้อ', ['organization_id' => $orgId]);
        log_activity('SALES_OPPORTUNITY', $soId, 'SYSTEM', 'สร้างดีลขาย ' . $soData['ref_no'] . ' (ลีดผู้ซื้อใหม่)', ['organization_id' => $orgId]);
        // ขั้น REQUIREMENT เป็นของ Sales Management / Director
        task_create([
            'title' => 'บันทึกความต้องการของผู้ซื้อ — ' . $soData['ref_no'],
            'type' => 'REQUIREMENT', 'parent_type' => 'SALES_OPPORTUNITY', 'parent_id' => $soId,
            'assignee_role' => 'SALES_DIRECTOR', 'due_date' => add_days(today(), 2),
        ]);
        return $soId;
    });
}

/** ลีดปิดเองเมื่อดีลทุกตัวภายใต้ลีดปิดแล้ว */
function lead_refresh_status(string $leadId): void
{
    $active = (int) val("SELECT COUNT(*) FROM device_opportunities WHERE lead_id = ? AND status NOT IN ('PURCHASED','REJECTED','LOST')", [$leadId])
        + (int) val("SELECT COUNT(*) FROM sales_opportunities WHERE lead_id = ? AND status NOT IN ('WON','LOST')", [$leadId]);
    $status = $active ? 'ACTIVE' : 'CLOSED';
    if (val('SELECT status FROM leads WHERE id = ?', [$leadId]) !== $status) {
        update_audited('leads', 'LEAD', $leadId, ['status' => $status], 'STATUS_CHANGE');
    }
}

function lead_update(string $id, array $d): void
{
    require_cap('lead.edit');
    db_get('leads', $id, 'ลีด');
    $owner = v_uuid($d['owner_id'] ?? null, 'ผู้รับผิดชอบ');
    if (!val('SELECT id FROM users WHERE id = ? AND active = 1', [$owner])) throw new AppError('ไม่พบผู้รับผิดชอบ');
    update_audited('leads', 'LEAD', $id, [
        'source' => lead_source_validate($d['source'] ?? null),
        'owner_id' => $owner,
        'summary' => s($d['summary'] ?? null),
    ]);
}

/**
 * อัปเดตการติดตามของดีล (ผู้รับผิดชอบ / next action / วันติดตาม)
 * $kind = 'DO' (ดีลซื้อ) หรือ 'SO' (ดีลขาย)
 */
function followup_update(string $kind, string $id, array $d): void
{
    require_cap('lead.edit');
    [$table, $entity, $closed] = $kind === 'DO'
        ? ['device_opportunities', 'DEVICE_OPPORTUNITY', DO_CLOSED]
        : ['sales_opportunities', 'SALES_OPPORTUNITY', SO_CLOSED];
    $row = db_get($table, $id, 'ดีล');
    $active = !in_array($row['status'], $closed, true);
    $fu = validate_followup($d, $active);
    update_audited($table, $entity, $id, $fu);
}

/**
 * บันทึกกิจกรรม (โทร / LINE / เข้าพบ ...) และตั้งการติดตามครั้งถัดไปในฟอร์มเดียว
 * $d: type, summary, outcome, occurred_at(date), next_action, next_follow_up_date
 */
function activity_add(string $parentType, string $parentId, array $d): string
{
    $row = parent_require_edit($parentType, $parentId);
    $type = v_in($d['type'] ?? 'NOTE', ['CALL', 'LINE', 'EMAIL', 'MEETING', 'VISIT', 'NOTE'], 'ประเภทกิจกรรม');
    $summary = s($d['summary'] ?? null);
    if (!$summary) throw new AppError('กรุณากรอกรายละเอียดกิจกรรม');
    $when = v_date($d['occurred_on'] ?? null, 'วันที่') ?? today();
    $occurred = $when === today() ? now6() : $when . ' 12:00:00';
    if ($when > today()) throw new AppError('วันที่ของกิจกรรมต้องไม่เป็นวันในอนาคต');

    return tx(function () use ($parentType, $parentId, $row, $type, $summary, $d, $occurred) {
        $ctx = parent_context($parentType, $row);
        $id = log_activity($parentType, $parentId, $type, $summary, $ctx + ['outcome' => s($d['outcome'] ?? null), 'occurred_at' => $occurred]);
        // ถ้าเป็นดีล ให้อัปเดต next action / วันติดตามด้วย
        if (in_array($parentType, ['DEVICE_OPPORTUNITY', 'SALES_OPPORTUNITY'], true) && (s($d['next_action'] ?? null) || s($d['next_follow_up_date'] ?? null))) {
            followup_update($parentType === 'DEVICE_OPPORTUNITY' ? 'DO' : 'SO', $parentId, [
                'owner_id' => $row['owner_id'],
                'next_action' => $d['next_action'] ?? $row['next_action'],
                'next_follow_up_date' => $d['next_follow_up_date'] ?? $row['next_follow_up_date'],
            ]);
        }
        return $id;
    });
}

function activities_for(string $parentType, string $parentId): array
{
    return all('SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id = a.user_id
                WHERE a.parent_type = ? AND a.parent_id = ? ORDER BY a.occurred_at DESC LIMIT 300', [$parentType, $parentId]);
}

/**
 * รายการดีลทั้งสองฝั่งสำหรับหน้า "ลีด / ติดตาม" และ Dashboard
 * $f: kind (DO|SO|''), owner ('me'|user id|''), state ('active'|'closed'|'all'), overdue (bool), due_today (bool), q
 */
function followup_query(array $f): array
{
    $parts = [];
    $p = [];
    if (can('lead.view') && ($f['kind'] ?? '') !== 'SO') {
        $parts[] = "SELECT 'DO' AS kind, d.id, d.ref_no, d.status, d.owner_id, d.next_action, d.next_follow_up_date, d.created_at, d.updated_at,
                    o.id AS org_id, o.name AS org_name, CONCAT(dv.brand, ' ', dv.model, IFNULL(CONCAT(' #', dv.serial_number), '')) AS subject,
                    l.id AS lead_id, l.ref_no AS lead_ref, l.source,
                    CASE WHEN d.status IN ('PURCHASED','REJECTED','LOST') THEN 0 ELSE 1 END AS is_active
                    FROM device_opportunities d JOIN organizations o ON o.id = d.seller_org_id JOIN devices dv ON dv.id = d.device_id JOIN leads l ON l.id = d.lead_id";
    }
    if (can('sales.view') && ($f['kind'] ?? '') !== 'DO') {
        $parts[] = "SELECT 'SO' AS kind, s.id, s.ref_no, s.status, s.owner_id, s.next_action, s.next_follow_up_date, s.created_at, s.updated_at,
                    o.id AS org_id, o.name AS org_name, COALESCE(NULLIF(CONCAT_WS(' ', s.req_brand, s.req_model), ''), s.interested_device) AS subject,
                    l.id AS lead_id, l.ref_no AS lead_ref, l.source,
                    CASE WHEN s.status IN ('WON','LOST') THEN 0 ELSE 1 END AS is_active
                    FROM sales_opportunities s JOIN organizations o ON o.id = s.buyer_org_id JOIN leads l ON l.id = s.lead_id";
    }
    if (!$parts) return ['sql' => null, 'params' => []];
    $where = [];
    $state = $f['state'] ?? 'active';
    if ($state === 'active') $where[] = 'x.is_active = 1';
    if ($state === 'closed') $where[] = 'x.is_active = 0';
    $owner = $f['owner'] ?? '';
    if ($owner === 'me') { $where[] = 'x.owner_id = ?'; $p[] = current_user_id(); }
    elseif ($owner && is_uuid($owner)) { $where[] = 'x.owner_id = ?'; $p[] = $owner; }
    if (!empty($f['overdue'])) { $where[] = 'x.is_active = 1 AND x.next_follow_up_date < ?'; $p[] = today(); }
    if (!empty($f['due_today'])) { $where[] = 'x.is_active = 1 AND x.next_follow_up_date = ?'; $p[] = today(); }
    if (!empty($f['no_followup'])) { $where[] = 'x.is_active = 1 AND x.next_follow_up_date IS NULL'; }
    if (!empty($f['q'])) {
        $where[] = '(x.ref_no LIKE ? OR x.org_name LIKE ? OR x.subject LIKE ? OR x.lead_ref LIKE ?)';
        $like = like($f['q']);
        array_push($p, $like, $like, $like, $like);
    }
    // ใช้: 'SELECT x.*, u.name AS owner_name ' . $sql
    $sql = 'FROM (' . implode(' UNION ALL ', $parts) . ') x LEFT JOIN users u ON u.id = x.owner_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    return ['sql' => $sql, 'params' => $p];
}
