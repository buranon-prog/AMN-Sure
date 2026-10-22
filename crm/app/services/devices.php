<?php
// Device Master: 1 เครื่องจริง = 1 record ถาวร (Device_ID ไม่เปลี่ยนตลอดอายุเครื่อง)

const USAGE_UNITS = ['SHOT', 'PULSE', 'HOUR'];
const DEVICE_COMMERCIAL = ['EXTERNAL', 'UNDER_OFFER', 'PURCHASED', 'IN_INVENTORY', 'RESERVED', 'DELIVERED', 'INSTALLED_AT_CUSTOMER'];
const DEVICE_TECHNICAL = ['UNKNOWN', 'INSPECTED_OK', 'NEEDS_REPAIR', 'UNDER_REPAIR', 'READY', 'QC_PASSED'];
const SERVICE_TYPES = ['PM', 'CM', 'REPAIR', 'PART_REPLACEMENT', 'UPGRADE'];

function device_validate(array $d): array
{
    $brand = s($d['brand'] ?? null);
    $model = s($d['model'] ?? null);
    if (!$brand || !$model) throw new AppError('กรุณากรอกยี่ห้อและรุ่นของเครื่อง');
    $serial = v_maxlen(s($d['serial_number'] ?? null), 120, 'Serial number');
    $maxYear = (int) date('Y') + 1;
    $unit = s($d['usage_unit'] ?? null);
    if ($unit !== null) v_in($unit, USAGE_UNITS, 'หน่วยการใช้งาน');
    return [
        'brand' => v_maxlen($brand, 100, 'ยี่ห้อ'),
        'model' => v_maxlen($model, 150, 'รุ่น'),
        'category' => v_maxlen(s($d['category'] ?? null), 100, 'ประเภทเครื่อง'),
        'serial_number' => $serial,
        'serial_normalized' => normalize_serial($serial),
        'serial_missing_reason' => $serial ? null : v_maxlen(s($d['serial_missing_reason'] ?? null), 255, 'เหตุผลที่ไม่มี serial'),
        'manufacture_year' => v_int($d['manufacture_year'] ?? null, 'ปีที่ผลิต', false, 1970, $maxYear),
        'installation_year' => v_int($d['installation_year'] ?? null, 'ปีที่ติดตั้ง', false, 1970, $maxYear),
        'current_location' => v_maxlen(s($d['current_location'] ?? null), 255, 'สถานที่ตั้ง'),
        'usage_value' => v_decimal($d['usage_value'] ?? null, 'ค่าการใช้งาน'),
        'usage_unit' => $unit,
        'usage_recorded_at' => s($d['usage_value'] ?? null) !== null ? (v_date($d['usage_recorded_at'] ?? null, 'วันที่บันทึกการใช้งาน') ?? today()) : null,
        'accessories' => s($d['accessories'] ?? null),
        'notes' => s($d['notes'] ?? null),
    ];
}

/**
 * ค้นหาเครื่องที่อาจซ้ำก่อนสร้างใหม่
 * exact = serial ตรงกัน (บล็อก), probable = ยี่ห้อ+รุ่นเดียวกัน และเจ้าของหรือสถานที่เดียวกัน (เตือน)
 */
function device_find_duplicates(?string $serial, ?string $brand, ?string $model, ?string $ownerOrgId, ?string $location, ?string $excludeId = null): array
{
    $exact = [];
    $norm = normalize_serial($serial);
    if ($norm) {
        $exact = all('SELECT id, ref_no, brand, model, serial_number, current_location FROM devices WHERE serial_normalized = ? AND id <> ?', [$norm, $excludeId ?? '']);
    }
    $probable = [];
    if ($brand && $model) {
        $cond = [];
        $p = [mb_strtolower($brand), mb_strtolower($model), $excludeId ?? ''];
        if ($ownerOrgId) { $cond[] = 'current_owner_org_id = ?'; $p[] = $ownerOrgId; }
        if ($location) { $cond[] = 'current_location LIKE ?'; $p[] = like($location); }
        if ($cond) {
            $probable = all('SELECT id, ref_no, brand, model, serial_number, current_location FROM devices
                WHERE LOWER(brand) = ? AND LOWER(model) = ? AND id <> ? AND archived_at IS NULL AND (' . implode(' OR ', $cond) . ') LIMIT 10', $p);
        }
        $exactIds = array_column($exact, 'id');
        $probable = array_values(array_filter($probable, function ($r) use ($exactIds) { return !in_array($r['id'], $exactIds, true); }));
    }
    return ['exact' => $exact, 'probable' => $probable];
}

/** สร้างเครื่อง (ไม่ตรวจสิทธิ์ — ผู้เรียกต้องตรวจเอง) */
function device_create_internal(array $data, bool $confirmDuplicate, ?string $ownerOrgId, bool $ownedByAmn = false): string
{
    $dups = device_find_duplicates($data['serial_number'], $data['brand'], $data['model'], $ownerOrgId, $data['current_location']);
    if ($dups['exact']) {
        $e = $dups['exact'][0];
        throw new AppError('Serial นี้มีในระบบแล้ว: ' . $e['ref_no'] . ' ' . $e['brand'] . ' ' . $e['model'] . ' — ให้เลือก "ใช้เครื่องที่มีอยู่" แทนการสร้างใหม่');
    }
    if ($dups['probable'] && !$confirmDuplicate) {
        throw new AppError('อาจเป็นเครื่องซ้ำกับ: ' . implode(', ', array_map(function ($r) {
            return $r['ref_no'] . ' ' . $r['brand'] . ' ' . $r['model'] . ($r['serial_number'] ? ' #' . $r['serial_number'] : '');
        }, $dups['probable'])) . ' — ถ้าเป็นคนละเครื่องจริง ให้ติ๊ก "ยืนยันว่าเป็นเครื่องใหม่" แล้วบันทึกอีกครั้ง');
    }
    return tx(function () use ($data, $ownerOrgId, $ownedByAmn, $dups) {
        $data['ref_no'] = next_ref('DEV', false, 6);
        $data['current_owner_org_id'] = $ownedByAmn ? null : $ownerOrgId;
        $data['owned_by_amn'] = $ownedByAmn ? 1 : 0;
        $data['commercial_status'] = $ownedByAmn ? 'IN_INVENTORY' : 'EXTERNAL';
        $data['technical_status'] = 'UNKNOWN';
        $id = insert_audited('devices', 'DEVICE', $data, $dups['probable'] ? 'ผู้ใช้ยืนยันว่าไม่ซ้ำกับ ' . implode(', ', array_column($dups['probable'], 'ref_no')) : null);
        if ($ownerOrgId || $ownedByAmn) {
            db_insert('device_ownerships', [
                'device_id' => $id, 'organization_id' => $ownedByAmn ? null : $ownerOrgId, 'owned_by_amn' => $ownedByAmn ? 1 : 0,
                'from_date' => today(), 'source' => 'INITIAL',
            ]);
        }
        log_activity('DEVICE', $id, 'SYSTEM', 'สร้างข้อมูลเครื่อง ' . $data['ref_no'], ['device_id' => $id, 'organization_id' => $ownerOrgId]);
        return $id;
    });
}

/**
 * สร้างเครื่องจากหน้า Device Master
 * $d['opening_stock'] = 1 → เครื่องที่ AMN Sure มีอยู่แล้วตอนเริ่มใช้ระบบ (ยอดยกมา) — เฉพาะ GM
 */
function device_create(array $d, bool $confirmDuplicate = false): string
{
    require_cap('device.edit');
    $data = device_validate($d);
    $opening = !empty($d['opening_stock']) && $d['opening_stock'] !== '0';
    if ($opening) require_cap('device.opening_stock');
    $owner = $opening ? null : v_uuid($d['current_owner_org_id'] ?? null, 'เจ้าของเครื่อง', false);
    if ($owner) db_get('organizations', $owner, 'เจ้าของเครื่อง');
    return tx(function () use ($data, $confirmDuplicate, $owner, $opening, $d) {
        $id = device_create_internal($data, $confirmDuplicate, $owner, $opening);
        if ($opening) {
            $invId = db_insert('inventory', [
                'ref_no' => next_ref('INV', false, 6),
                'device_id' => $id,
                'source' => 'OPENING',
                'received_date' => v_date($d['received_date'] ?? null, 'วันที่รับเข้า') ?? today(),
                'storage_location' => s($d['storage_location'] ?? null),
                'status' => 'IN_STOCK',
                'book_cost' => v_money($d['book_cost'] ?? null, 'ต้นทุนบัญชี'),
                'list_price' => v_money($d['list_price'] ?? null, 'ราคาตั้งขาย'),
            ]);
            audit_log('INVENTORY', $invId, 'CREATE', null, 'ยอดยกมา (opening stock)');
        }
        return $id;
    });
}

function device_update(string $id, array $d): void
{
    require_cap('device.edit');
    $device = db_get('devices', $id, 'เครื่อง');
    $data = device_validate($d);
    if (values_equal($data['usage_value'], $device['usage_value']) && !s($d['usage_recorded_at'] ?? null)) {
        $data['usage_recorded_at'] = $device['usage_recorded_at'];
    }
    if ($data['serial_normalized'] && $data['serial_normalized'] !== $device['serial_normalized']) {
        $other = one('SELECT ref_no FROM devices WHERE serial_normalized = ? AND id <> ?', [$data['serial_normalized'], $id]);
        if ($other) throw new AppError('Serial นี้ถูกใช้กับเครื่อง ' . $other['ref_no'] . ' แล้ว');
    }
    if (!$data['serial_number'] && !$data['serial_missing_reason'] && $device['serial_number']) {
        throw new AppError('ถ้าลบ serial ออก ต้องระบุเหตุผลที่ไม่มี serial');
    }
    update_audited('devices', 'DEVICE', $id, $data);
}

/** แก้สถานะเครื่องโดยตรง (เฉพาะ GM/Admin ต้องมีเหตุผล) — ปกติสถานะเปลี่ยนตาม workflow เท่านั้น */
function device_override_status(string $id, string $commercial, string $technical, string $reason): void
{
    require_cap('device.override_status');
    v_in($commercial, DEVICE_COMMERCIAL, 'สถานะทางการค้า');
    v_in($technical, DEVICE_TECHNICAL, 'สถานะทางเทคนิค');
    $reason = s($reason);
    if (!$reason) throw new AppError('กรุณาระบุเหตุผลในการแก้สถานะ');
    db_get('devices', $id, 'เครื่อง');
    update_audited('devices', 'DEVICE', $id, ['commercial_status' => $commercial, 'technical_status' => $technical], 'OVERRIDE', $reason);
}

function service_history_add(string $deviceId, array $d): string
{
    require_cap('service_history.edit');
    db_get('devices', $deviceId, 'เครื่อง');
    $desc = s($d['description'] ?? null);
    if (!$desc) throw new AppError('กรุณากรอกรายละเอียดงานซ่อม/บริการ');
    $data = [
        'device_id' => $deviceId,
        'service_date' => v_date($d['service_date'] ?? null, 'วันที่', true),
        'type' => v_in($d['type'] ?? null, SERVICE_TYPES, 'ประเภทงาน'),
        'description' => $desc,
        'parts_replaced' => s($d['parts_replaced'] ?? null),
        'performed_by' => v_maxlen(s($d['performed_by'] ?? null), 150, 'ผู้ดำเนินการ'),
        // ต้นทุนบันทึกได้เฉพาะผู้มีสิทธิ์เห็นข้อมูลการเงิน
        'cost' => can('finance.view') ? v_money($d['cost'] ?? null, 'ค่าใช้จ่าย') : null,
        'is_repeat_failure' => !empty($d['is_repeat_failure']) && $d['is_repeat_failure'] !== '0' ? 1 : 0,
        'source' => v_in($d['source'] ?? 'INTERNAL', ['INTERNAL', 'EXTERNAL_RECORD'], 'แหล่งข้อมูล'),
    ];
    return tx(function () use ($data, $deviceId) {
        $id = insert_audited('device_service_history', 'SERVICE_HISTORY', $data);
        log_activity('DEVICE', $deviceId, 'SYSTEM', 'บันทึกประวัติ ' . label('service_type', $data['type']) . ': ' . mb_substr($data['description'], 0, 120), ['device_id' => $deviceId]);
        return $id;
    });
}

function ma_record_add(string $deviceId, array $d): string
{
    require_cap('service_history.edit');
    db_get('devices', $deviceId, 'เครื่อง');
    $provider = s($d['provider'] ?? null);
    if (!$provider) throw new AppError('กรุณากรอกผู้ให้บริการ MA');
    $start = v_date($d['start_date'] ?? null, 'วันเริ่มสัญญา');
    $end = v_date($d['end_date'] ?? null, 'วันสิ้นสุดสัญญา', true);
    if ($start && $end < $start) throw new AppError('วันสิ้นสุดต้องไม่ก่อนวันเริ่มสัญญา');
    $data = [
        'device_id' => $deviceId,
        'provider' => v_maxlen($provider, 150, 'ผู้ให้บริการ'),
        'contract_no' => v_maxlen(s($d['contract_no'] ?? null), 100, 'เลขที่สัญญา'),
        'start_date' => $start,
        'end_date' => $end,
        'coverage' => s($d['coverage'] ?? null),
        'cost' => can('finance.view') ? v_money($d['cost'] ?? null, 'ค่าสัญญา') : null,
        'notes' => s($d['notes'] ?? null),
    ];
    return tx(function () use ($data, $deviceId) {
        $id = insert_audited('ma_records', 'MA_RECORD', $data);
        log_activity('DEVICE', $deviceId, 'SYSTEM', 'บันทึกสัญญา MA กับ ' . $data['provider'] . ' ถึง ' . d($data['end_date']), ['device_id' => $deviceId]);
        return $id;
    });
}

/** เปลี่ยนเจ้าของเครื่อง (ปิดแถวเดิม เปิดแถวใหม่) — ใช้ตอนซื้อเข้า/ขายออก */
function device_transfer_ownership(string $deviceId, ?string $orgId, bool $toAmn, string $source, array $refs = []): void
{
    q('UPDATE device_ownerships SET to_date = ?, updated_at = ?, updated_by = ? WHERE device_id = ? AND to_date IS NULL', [today(), now(), current_user_id(), $deviceId]);
    db_insert('device_ownerships', [
        'device_id' => $deviceId, 'organization_id' => $toAmn ? null : $orgId, 'owned_by_amn' => $toAmn ? 1 : 0,
        'from_date' => today(), 'source' => $source,
        'acquisition_id' => $refs['acquisition_id'] ?? null, 'sales_transaction_id' => $refs['sales_transaction_id'] ?? null,
    ]);
    update_audited('devices', 'DEVICE', $deviceId, ['owned_by_amn' => $toAmn ? 1 : 0, 'current_owner_org_id' => $toAmn ? null : $orgId], 'UPDATE', 'เปลี่ยนเจ้าของ (' . $source . ')');
}

// ---------------------------------------------------------------- Device 360

function device_360(string $id): array
{
    require_cap('device.view');
    $dv = db_get('devices', $id, 'เครื่อง');
    $d = ['device' => $dv];
    $d['owner'] = $dv['current_owner_org_id'] ? one('SELECT id, ref_no, name FROM organizations WHERE id = ?', [$dv['current_owner_org_id']]) : null;
    $d['ownerships'] = all('SELECT ow.*, o.name AS org_name, o.ref_no AS org_ref FROM device_ownerships ow LEFT JOIN organizations o ON o.id = ow.organization_id
        WHERE ow.device_id = ? ORDER BY ow.from_date DESC, ow.created_at DESC', [$id]);
    $d['seller_opps'] = can('lead.view') ? all('SELECT o.*, org.name AS seller_name, u.name AS owner_name FROM device_opportunities o
        JOIN organizations org ON org.id = o.seller_org_id JOIN users u ON u.id = o.owner_id WHERE o.device_id = ? ORDER BY o.created_at DESC', [$id]) : [];
    $d['inspections'] = can('inspection.view') ? all('SELECT i.*, u.name AS engineer_name, o.ref_no AS opp_ref FROM inspections i
        LEFT JOIN users u ON u.id = i.engineer_id LEFT JOIN device_opportunities o ON o.id = i.device_opportunity_id
        WHERE i.device_id = ? ORDER BY i.requested_at DESC', [$id]) : [];
    $d['service'] = can('service_history.view') ? redact('device_service_history', all('SELECT * FROM device_service_history WHERE device_id = ? AND archived_at IS NULL ORDER BY service_date DESC', [$id])) : [];
    $d['ma'] = can('service_history.view') ? redact('ma_records', all('SELECT * FROM ma_records WHERE device_id = ? AND archived_at IS NULL ORDER BY end_date DESC', [$id])) : [];
    $d['cost_sheets'] = can('finance.view') ? all("SELECT cs.*, o.ref_no AS opp_ref FROM cost_sheets cs JOIN device_opportunities o ON o.id = cs.device_opportunity_id
        WHERE o.device_id = ? ORDER BY cs.created_at DESC", [$id]) : [];
    $d['valuations'] = (can('finance.view') || can('valuation.recommended_view')) ? redact('valuations', all('SELECT v.*, o.ref_no AS opp_ref FROM valuations v
        JOIN device_opportunities o ON o.id = v.device_opportunity_id WHERE o.device_id = ? ORDER BY v.created_at DESC', [$id])) : [];
    $d['acquisitions'] = can('lead.view') ? all('SELECT a.*, org.name AS seller_name FROM acquisitions a JOIN organizations org ON org.id = a.seller_org_id
        WHERE a.device_id = ? ORDER BY a.purchase_date DESC', [$id]) : [];
    $d['inventory'] = can('inventory.view') ? redact('inventory', all('SELECT * FROM inventory WHERE device_id = ? ORDER BY received_date DESC', [$id])) : [];
    $d['matches'] = can('sales.view') ? all('SELECT m.*, s.ref_no AS so_ref, s.status AS so_status, org.name AS buyer_name FROM device_matches m
        JOIN sales_opportunities s ON s.id = m.sales_opportunity_id JOIN organizations org ON org.id = s.buyer_org_id
        WHERE m.device_id = ? ORDER BY m.created_at DESC', [$id]) : [];
    $d['transactions'] = can('transaction.view') ? all('SELECT DISTINCT t.*, org.name AS buyer_name FROM sales_transactions t
        JOIN quotation_version_lines l ON l.quotation_version_id = t.quotation_version_id JOIN organizations org ON org.id = t.buyer_org_id
        WHERE l.device_id = ? ORDER BY t.won_at DESC', [$id]) : [];
    $d['qc'] = can('transaction.view') ? all('SELECT q.*, u.name AS engineer_name, t.ref_no AS trx_ref FROM qc_records q JOIN users u ON u.id = q.engineer_id
        JOIN sales_transactions t ON t.id = q.sales_transaction_id WHERE q.device_id = ? ORDER BY q.checked_at DESC', [$id]) : [];
    $d['deliveries'] = can('transaction.view') ? all('SELECT dl.*, t.ref_no AS trx_ref FROM deliveries dl JOIN sales_transactions t ON t.id = dl.sales_transaction_id
        WHERE dl.device_id = ? ORDER BY dl.delivered_date DESC', [$id]) : [];
    $d['installations'] = can('transaction.view') ? all('SELECT i.*, t.ref_no AS trx_ref FROM installations i JOIN sales_transactions t ON t.id = i.sales_transaction_id
        WHERE i.device_id = ? ORDER BY i.installed_date DESC', [$id]) : [];
    $d['jobs'] = can('job.view') ? all('SELECT j.*, u.name AS engineer_name FROM technical_jobs j LEFT JOIN users u ON u.id = j.assigned_engineer_id
        WHERE j.device_id = ? ORDER BY j.created_at DESC', [$id]) : [];
    $d['cases'] = can('service_case.view') ? all('SELECT * FROM service_cases WHERE device_id = ? ORDER BY reported_at DESC', [$id]) : [];
    $d['activities'] = all('SELECT a.*, u.name AS user_name FROM activities a LEFT JOIN users u ON u.id = a.user_id
        WHERE a.device_id = ? ORDER BY a.occurred_at DESC LIMIT 200', [$id]);
    $d['documents'] = documents_for_device($id);
    return $d;
}
