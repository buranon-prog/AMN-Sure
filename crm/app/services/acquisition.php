<?php
// Workflow ฝั่งซื้อ (สเปกข้อ 3): ลีดผู้ขาย → ตรวจเครื่อง → Cost Sheet → Valuation → เจรจา → GM อนุมัติ → ซื้อ → สต็อก
// ทุกขั้นตรวจสถานะต้นทาง + สิทธิ์ และทำใน transaction เดียว (ดู docs/crm-v1/02-state-transitions.md)

const DO_STATUSES = ['NEW_SELLER_LEAD', 'WAITING_INSPECTION', 'INSPECTION_IN_PROGRESS', 'INSPECTED', 'COSTED', 'VALUED',
    'NEGOTIATING', 'PENDING_APPROVAL', 'APPROVED', 'PURCHASED', 'REJECTED', 'LOST'];
const DO_CLOSED = ['PURCHASED', 'REJECTED', 'LOST'];
const DO_STEPS = ['NEW_SELLER_LEAD', 'WAITING_INSPECTION', 'INSPECTION_IN_PROGRESS', 'INSPECTED', 'COSTED', 'VALUED',
    'NEGOTIATING', 'PENDING_APPROVAL', 'APPROVED', 'PURCHASED'];
const INSP_ITEM_RESULTS = ['PASS', 'MINOR_ISSUE', 'MAJOR_ISSUE', 'MISSING', 'NA'];
const INSP_OVERALL = ['PASS', 'MINOR_ISSUE', 'MAJOR_ISSUE'];
const INSP_CATEGORIES = ['EXTERIOR', 'FUNCTIONAL', 'HANDPIECE', 'ACCESSORIES', 'ERRORS', 'USAGE', 'CONSUMABLES', 'SAFETY', 'SOFTWARE', 'PARTS'];
const CONDITIONS = ['EXCELLENT', 'GOOD', 'FAIR', 'POOR'];
const LEVELS = ['LOW', 'MEDIUM', 'HIGH'];
const COST_CATEGORIES = ['ACQUISITION_ASSUMPTION', 'REPAIR', 'PARTS', 'REFURBISHMENT', 'ACCESSORIES', 'TRANSPORT', 'INSTALLATION', 'WARRANTY_PROVISION', 'OTHER'];
const APPROVAL_DECISIONS = ['APPROVED', 'APPROVED_WITH_CONDITION', 'REVISION_REQUIRED', 'REJECTED'];
const PAYMENT_STATUSES = ['UNPAID', 'PARTIAL', 'PAID'];

function acq_get(string $id): array
{
    require_cap('lead.view');
    return db_get('device_opportunities', $id, 'ดีลซื้อ');
}

function acq_lock_status(string $id, array $allowed, string $what = 'ดีลซื้อ'): array
{
    $opp = db_lock('device_opportunities', $id, $what);
    if (!in_array($opp['status'], $allowed, true)) {
        throw new AppError('ทำรายการนี้ไม่ได้ เพราะดีลอยู่ในสถานะ "' . label('status', $opp['status']) . '"');
    }
    return $opp;
}

function acq_set_status(array $opp, string $to, array $extra = [], ?string $note = null): void
{
    update_audited('device_opportunities', 'DEVICE_OPPORTUNITY', $opp['id'], ['status' => $to] + $extra, 'STATUS_CHANGE', $note);
    log_activity('DEVICE_OPPORTUNITY', $opp['id'], 'SYSTEM', 'สถานะ: ' . label('status', $opp['status']) . ' → ' . label('status', $to) . ($note ? "\n" . $note : ''),
        ['organization_id' => $opp['seller_org_id'], 'device_id' => $opp['device_id']]);
}

function latest_completed_inspection(string $oppId): ?array
{
    return one("SELECT * FROM inspections WHERE device_opportunity_id = ? AND status = 'COMPLETED' ORDER BY completed_at DESC LIMIT 1", [$oppId]);
}

function latest_cost_sheet(string $oppId, bool $submittedOnly = true): ?array
{
    return one('SELECT * FROM cost_sheets WHERE device_opportunity_id = ?' . ($submittedOnly ? " AND status = 'SUBMITTED'" : '') . ' ORDER BY version_no DESC LIMIT 1', [$oppId]);
}

function latest_valuation(string $oppId): ?array
{
    return one('SELECT * FROM valuations WHERE device_opportunity_id = ? ORDER BY created_at DESC LIMIT 1', [$oppId]);
}

function pending_approval(string $oppId): ?array
{
    return one("SELECT * FROM approvals WHERE subject_type = 'DEVICE_OPPORTUNITY' AND subject_id = ? AND decision = 'PENDING' LIMIT 1", [$oppId]);
}

function latest_approval(string $oppId): ?array
{
    return one("SELECT * FROM approvals WHERE subject_type = 'DEVICE_OPPORTUNITY' AND subject_id = ? ORDER BY requested_at DESC, created_at DESC LIMIT 1", [$oppId]);
}

// ---------------------------------------------------------------- 1. ขอตรวจเครื่อง (BR-02)

/** ข้อมูลเครื่องที่ต้องครบก่อนขอตรวจ */
function acq_device_info_missing(array $device, array $opp): array
{
    $m = [];
    if (!$device['serial_number'] && !$device['serial_missing_reason']) $m[] = 'Serial number (หรือเหตุผลที่ไม่มี)';
    if (!$device['manufacture_year']) $m[] = 'ปีที่ผลิต';
    if (!$opp['location'] && !$device['current_location']) $m[] = 'สถานที่ตั้งเครื่อง';
    if ($opp['asking_price'] === null) $m[] = 'ราคาที่ผู้ขายตั้ง (asking price)';
    return $m;
}

function acq_request_inspection(string $oppId, array $d): string
{
    require_cap('inspection.request');
    return tx(function () use ($oppId, $d) {
        $opp = acq_lock_status($oppId, ['NEW_SELLER_LEAD']);
        $device = db_get('devices', $opp['device_id'], 'เครื่อง');

        // ขั้น DEVICE INFORMATION: อัปเดตข้อมูลเครื่องจากฟอร์ม (ช่องที่ไม่ได้ส่งมาใช้ค่าเดิม)
        $merged = [];
        foreach (['brand', 'model', 'category', 'serial_number', 'serial_missing_reason', 'manufacture_year', 'installation_year',
                     'current_location', 'usage_value', 'usage_unit', 'usage_recorded_at', 'accessories', 'notes'] as $f) {
            $merged[$f] = array_key_exists($f, $d) ? $d[$f] : $device[$f];
        }
        $devData = device_validate($merged);
        if ($devData['serial_normalized'] && $devData['serial_normalized'] !== $device['serial_normalized']) {
            $other = one('SELECT ref_no FROM devices WHERE serial_normalized = ? AND id <> ?', [$devData['serial_normalized'], $device['id']]);
            if ($other) throw new AppError('Serial นี้ถูกใช้กับเครื่อง ' . $other['ref_no'] . ' แล้ว');
        }
        update_audited('devices', 'DEVICE', $device['id'], $devData);
        $oppData = [
            'asking_price' => array_key_exists('asking_price', $d) ? v_money($d['asking_price'], 'ราคาที่ผู้ขายตั้ง') : $opp['asking_price'],
            'reason_for_sale' => array_key_exists('reason_for_sale', $d) ? s($d['reason_for_sale']) : $opp['reason_for_sale'],
            'location' => array_key_exists('current_location', $d) ? s($d['current_location']) : $opp['location'],
        ];
        update_audited('device_opportunities', 'DEVICE_OPPORTUNITY', $oppId, $oppData);

        $missing = acq_device_info_missing(db_get('devices', $device['id']), array_merge($opp, $oppData));
        if ($missing) throw new AppError('ข้อมูลเครื่องยังไม่ครบ: ' . implode(', ', $missing));

        $inspId = insert_audited('inspections', 'INSPECTION', [
            'device_id' => $device['id'],
            'device_opportunity_id' => $oppId,
            'status' => 'REQUESTED',
            'requested_by' => current_user_id(),
            'requested_at' => now6(),
            'preferred_date' => v_date($d['preferred_date'] ?? null, 'วันที่สะดวกให้เข้าตรวจ'),
            'request_note' => s($d['request_note'] ?? null),
        ]);
        $jobId = db_insert('technical_jobs', [
            'ref_no' => next_ref('JOB'),
            'type' => 'INSPECTION',
            'device_id' => $device['id'],
            'parent_type' => 'INSPECTION',
            'parent_id' => $inspId,
            'title' => 'ตรวจเครื่อง ' . $devData['brand'] . ' ' . $devData['model'] . ' (' . $opp['ref_no'] . ')',
            'status' => 'OPEN',
            'scheduled_date' => v_date($d['preferred_date'] ?? null, 'วันที่สะดวกให้เข้าตรวจ'),
        ]);
        audit_log('TECHNICAL_JOB', $jobId, 'CREATE', null, 'สร้างจากการขอตรวจเครื่อง ' . $opp['ref_no']);
        task_create([
            'title' => 'ตรวจเครื่อง ' . $devData['brand'] . ' ' . $devData['model'] . ' — ' . $opp['ref_no'],
            'type' => 'INSPECTION',
            'parent_type' => 'INSPECTION',
            'parent_id' => $inspId,
            'assignee_role' => 'SERVICE_ENGINEER',
            'due_date' => v_date($d['preferred_date'] ?? null, 'วันที่สะดวกให้เข้าตรวจ') ?? add_days(today(), 3),
            'priority' => 'HIGH',
        ]);
        acq_set_status($opp, 'WAITING_INSPECTION', [], 'ขอให้ Service Engineering ตรวจเครื่อง');
        return $inspId;
    });
}

// ---------------------------------------------------------------- 2. ตรวจเครื่อง (BR-03, BR-04)

function inspection_template_items(): array
{
    $out = [];
    foreach (all("SELECT label, meta FROM master_data WHERE type = 'INSPECTION_ITEM' AND active = 1 ORDER BY sort") as $r) {
        $m = json_decode((string) $r['meta'], true) ?: [];
        $out[] = ['category' => $m['category'] ?? 'OTHER', 'item_name' => $r['label'], 'is_mandatory' => !empty($m['mandatory']) ? 1 : 0];
    }
    return $out;
}

function inspection_start(string $inspId): void
{
    require_cap('inspection.edit');
    tx(function () use ($inspId) {
        $insp = db_lock('inspections', $inspId, 'การตรวจเครื่อง');
        if ($insp['status'] !== 'REQUESTED') throw new AppError('การตรวจนี้เริ่มไปแล้วหรือปิดแล้ว');
        $sort = 0;
        foreach (inspection_template_items() as $it) {
            db_insert('inspection_items', $it + ['inspection_id' => $inspId, 'sort' => $sort += 10]);
        }
        update_audited('inspections', 'INSPECTION', $inspId, ['status' => 'IN_PROGRESS', 'engineer_id' => current_user_id(), 'started_at' => now6()], 'STATUS_CHANGE');
        q("UPDATE technical_jobs SET status = 'IN_PROGRESS', assigned_engineer_id = ?, updated_at = ?, updated_by = ? WHERE parent_type = 'INSPECTION' AND parent_id = ? AND status IN ('OPEN','SCHEDULED')",
            [current_user_id(), now(), current_user_id(), $inspId]);
        q("UPDATE tasks SET status = 'IN_PROGRESS', assignee_id = ?, updated_at = ?, updated_by = ? WHERE parent_type = 'INSPECTION' AND parent_id = ? AND status = 'OPEN'",
            [current_user_id(), now(), current_user_id(), $inspId]);
        if ($insp['device_opportunity_id']) {
            $opp = acq_lock_status($insp['device_opportunity_id'], ['WAITING_INSPECTION']);
            acq_set_status($opp, 'INSPECTION_IN_PROGRESS', [], 'เริ่มตรวจโดย ' . current_user()['name']);
        }
    });
}

/**
 * บันทึกผลตรวจ (บันทึกร่างได้) ถ้า $complete = true จะปิดการตรวจ
 * $d['items'][item_id] = ['result' => ..., 'note' => ...]
 * $d['new_items'][] = ['category', 'item_name', 'result', 'note']
 */
function inspection_save(string $inspId, array $d, bool $complete): void
{
    require_cap('inspection.edit');
    tx(function () use ($inspId, $d, $complete) {
        $insp = db_lock('inspections', $inspId, 'การตรวจเครื่อง');
        if ($insp['status'] !== 'IN_PROGRESS') throw new AppError('บันทึกผลได้เฉพาะการตรวจที่กำลังดำเนินการ');

        foreach ((array) ($d['items'] ?? []) as $itemId => $v) {
            if (!is_uuid((string) $itemId) || !is_array($v)) continue;
            $item = one('SELECT * FROM inspection_items WHERE id = ? AND inspection_id = ?', [$itemId, $inspId]);
            if (!$item) continue;
            update_audited('inspection_items', 'INSPECTION', $itemId, [
                'result' => v_in($v['result'] ?? null, INSP_ITEM_RESULTS, 'ผลตรวจของ ' . $item['item_name'], false),
                'note' => s($v['note'] ?? null),
            ]);
        }
        $sort = (int) val('SELECT COALESCE(MAX(sort), 0) FROM inspection_items WHERE inspection_id = ?', [$inspId]);
        foreach ((array) ($d['new_items'] ?? []) as $v) {
            if (!is_array($v) || !s($v['item_name'] ?? null)) continue;
            db_insert('inspection_items', [
                'inspection_id' => $inspId,
                'category' => v_in($v['category'] ?? 'PARTS', INSP_CATEGORIES, 'หมวดรายการตรวจ'),
                'item_name' => v_maxlen(s($v['item_name']), 255, 'ชื่อรายการ'),
                'is_mandatory' => 0,
                'sort' => $sort += 10,
                'result' => v_in($v['result'] ?? null, INSP_ITEM_RESULTS, 'ผลตรวจ', false),
                'note' => s($v['note'] ?? null),
            ]);
        }
        $summary = [
            'overall_result' => v_in($d['overall_result'] ?? null, INSP_OVERALL, 'ผลตรวจรวม', false),
            'overall_condition' => v_in($d['overall_condition'] ?? null, CONDITIONS, 'สภาพโดยรวม', false),
            'usage_reading' => v_maxlen(s($d['usage_reading'] ?? null), 100, 'ค่าการใช้งานที่อ่านได้'),
            'issues' => s($d['issues'] ?? null),
            'required_repair' => s($d['required_repair'] ?? null),
            'recommended_repair' => s($d['recommended_repair'] ?? null),
            'missing_accessories' => s($d['missing_accessories'] ?? null),
            'technical_risk' => v_in($d['technical_risk'] ?? null, LEVELS, 'ความเสี่ยงทางเทคนิค', false),
            'est_repair_days' => v_int($d['est_repair_days'] ?? null, 'ระยะเวลาซ่อมโดยประมาณ (วัน)', false, 0, 3650),
        ];
        update_audited('inspections', 'INSPECTION', $inspId, $summary);
        if (!$complete) return;

        // BR-03: ปิดการตรวจได้เมื่อมีผลรวม และทุกรายการที่บังคับมีผลแล้ว
        $missing = [];
        if (!$summary['overall_result']) $missing[] = 'ผลตรวจรวม (overall result)';
        if (!$summary['overall_condition']) $missing[] = 'สภาพโดยรวม';
        if (!$summary['technical_risk']) $missing[] = 'ความเสี่ยงทางเทคนิค';
        $blank = all('SELECT item_name FROM inspection_items WHERE inspection_id = ? AND is_mandatory = 1 AND (result IS NULL OR result = \'\')', [$inspId]);
        if ($blank) $missing[] = 'ผลของรายการบังคับ: ' . implode(', ', array_column($blank, 'item_name'));
        if ($missing) throw new AppError('ยังปิดการตรวจไม่ได้ (BR-03) ขาด: ' . implode(' / ', $missing));

        update_audited('inspections', 'INSPECTION', $inspId, ['status' => 'COMPLETED', 'completed_at' => now6()], 'STATUS_CHANGE');
        $tech = ($summary['overall_result'] === 'MAJOR_ISSUE' || $summary['required_repair']) ? 'NEEDS_REPAIR' : 'INSPECTED_OK';
        update_audited('devices', 'DEVICE', $insp['device_id'], ['technical_status' => $tech], 'STATUS_CHANGE', 'ผลตรวจ: ' . label('result', $summary['overall_result']));
        q("UPDATE technical_jobs SET status = 'DONE', completed_at = ?, updated_at = ?, updated_by = ? WHERE parent_type = 'INSPECTION' AND parent_id = ? AND status NOT IN ('DONE','CANCELLED')",
            [now(), now(), current_user_id(), $inspId]);
        tasks_close_for('INSPECTION', $inspId);

        if ($insp['device_opportunity_id']) {
            $opp = acq_lock_status($insp['device_opportunity_id'], ['INSPECTION_IN_PROGRESS']);
            acq_set_status($opp, 'INSPECTED', [], 'ผลตรวจรวม: ' . label('result', $summary['overall_result']) . ' · ความเสี่ยง: ' . label('risk', $summary['technical_risk']));
            // BR-04: แจ้ง/คืนงานให้เจ้าของดีลฝั่งการค้า และขอ Cost Sheet จาก Service Director
            task_create([
                'title' => 'ผลตรวจเครื่องเสร็จแล้ว — ติดตามดีล ' . $opp['ref_no'],
                'type' => 'INSPECTION_DONE', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $opp['id'],
                'assignee_id' => $opp['owner_id'], 'due_date' => add_days(today(), 1),
            ]);
            task_create([
                'title' => 'จัดทำ Cost Sheet — ' . $opp['ref_no'],
                'type' => 'COST_SHEET', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $opp['id'],
                'assignee_role' => 'SERVICE_DIRECTOR', 'due_date' => add_days(today(), 2), 'priority' => 'HIGH',
            ]);
        }
    });
}

// ---------------------------------------------------------------- 3. Cost Sheet (BR-05)

/**
 * บันทึก Cost Sheet (ร่าง) หรือส่ง ($submit)
 * ถ้าฉบับล่าสุดส่งไปแล้ว การบันทึกครั้งใหม่ = สร้างฉบับใหม่ (version + 1) ฉบับเดิมไม่ถูกแก้
 * $d: inspection_id, notes, items[] = [category, description, amount]
 */
function cost_sheet_save(string $oppId, array $d, bool $submit): string
{
    require_cap('cost_sheet.edit');
    return tx(function () use ($oppId, $d, $submit) {
        $opp = acq_lock_status($oppId, ['INSPECTED', 'COSTED', 'VALUED', 'NEGOTIATING']);
        $inspId = v_uuid($d['inspection_id'] ?? null, 'การตรวจเครื่องที่อ้างอิง', false) ?? (latest_completed_inspection($oppId)['id'] ?? null);
        $insp = $inspId ? one("SELECT * FROM inspections WHERE id = ? AND device_opportunity_id = ? AND status = 'COMPLETED'", [$inspId, $oppId]) : null;
        if (!$insp) throw new AppError('Cost Sheet ต้องอ้างอิงผลตรวจเครื่องที่เสร็จแล้วของดีลนี้ (BR-05)');

        $items = [];
        foreach ((array) ($d['items'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $amount = v_money($row['amount'] ?? null, 'จำนวนเงิน');
            $desc = s($row['description'] ?? null);
            if ($amount === null && !$desc) continue;
            $items[] = [
                'category' => v_in($row['category'] ?? null, COST_CATEGORIES, 'หมวดต้นทุน'),
                'description' => v_maxlen($desc, 255, 'รายละเอียด'),
                'amount' => $amount ?? '0.00',
            ];
        }
        if (!$items) throw new AppError('กรุณาใส่รายการต้นทุนอย่างน้อย 1 รายการ');
        $total = 0.0;
        foreach ($items as $it) $total += (float) $it['amount'];
        $total = number_format($total, 2, '.', '');

        $draft = one("SELECT * FROM cost_sheets WHERE device_opportunity_id = ? AND status = 'DRAFT' ORDER BY version_no DESC LIMIT 1 FOR UPDATE", [$oppId]);
        if ($draft) {
            $csId = $draft['id'];
            $old = all('SELECT category, description, amount FROM cost_sheet_items WHERE cost_sheet_id = ? ORDER BY sort', [$csId]);
            q('DELETE FROM cost_sheet_items WHERE cost_sheet_id = ?', [$csId]); // แก้ได้เฉพาะฉบับร่าง
            update_audited('cost_sheets', 'COST_SHEET', $csId, ['inspection_id' => $insp['id'], 'total_estimated_cost' => $total, 'notes' => s($d['notes'] ?? null)]);
            audit_log('COST_SHEET', $csId, 'UPDATE', [['field' => 'items', 'old' => $old, 'new' => $items]]);
        } else {
            $version = (int) val('SELECT COALESCE(MAX(version_no), 0) FROM cost_sheets WHERE device_opportunity_id = ?', [$oppId]) + 1;
            $csId = insert_audited('cost_sheets', 'COST_SHEET', [
                'device_opportunity_id' => $oppId, 'inspection_id' => $insp['id'], 'version_no' => $version,
                'status' => 'DRAFT', 'total_estimated_cost' => $total, 'notes' => s($d['notes'] ?? null),
            ]);
            audit_log('COST_SHEET', $csId, 'UPDATE', [['field' => 'items', 'old' => null, 'new' => $items]]);
        }
        $sort = 0;
        foreach ($items as $it) db_insert('cost_sheet_items', $it + ['cost_sheet_id' => $csId, 'sort' => $sort += 10]);

        if ($submit) {
            update_audited('cost_sheets', 'COST_SHEET', $csId, ['status' => 'SUBMITTED', 'submitted_at' => now(), 'submitted_by' => current_user_id()], 'STATUS_CHANGE');
            $v = (int) val('SELECT version_no FROM cost_sheets WHERE id = ?', [$csId]);
            if ($opp['status'] === 'INSPECTED') {
                acq_set_status($opp, 'COSTED', [], 'ส่ง Cost Sheet ฉบับที่ ' . $v);
            } else {
                log_activity('DEVICE_OPPORTUNITY', $oppId, 'SYSTEM', 'ส่ง Cost Sheet ฉบับแก้ไขที่ ' . $v, ['organization_id' => $opp['seller_org_id'], 'device_id' => $opp['device_id']]);
            }
            tasks_close_for('DEVICE_OPPORTUNITY', $oppId, ['COST_SHEET']);
            task_create([
                'title' => 'ทำ Valuation — ' . $opp['ref_no'],
                'type' => 'VALUATION', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $oppId,
                'assignee_role' => 'SALES_DIRECTOR', 'due_date' => add_days(today(), 2), 'priority' => 'HIGH',
            ]);
        }
        return $csId;
    });
}

function cost_sheet_items(string $csId): array
{
    return redact('cost_sheet_items', all('SELECT * FROM cost_sheet_items WHERE cost_sheet_id = ? ORDER BY sort', [$csId]));
}

function cost_sheet_acquisition_assumption(string $csId): string
{
    return (string) val("SELECT COALESCE(SUM(amount), 0) FROM cost_sheet_items WHERE cost_sheet_id = ? AND category = 'ACQUISITION_ASSUMPTION'", [$csId]);
}

// ---------------------------------------------------------------- 4. Valuation (BR-06)

/** GP ที่คาดไว้ = ราคาขายเป้าหมาย − ต้นทุนรวมใน Cost Sheet (ซึ่งรวมราคาซื้อสมมติฐานแล้ว) */
function compute_gp(float $targetSelling, float $totalCost): array
{
    $gp = round($targetSelling - $totalCost, 2);
    $margin = $targetSelling > 0 ? round($gp / $targetSelling, 4) : 0.0;
    return [$gp, $margin];
}

function valuation_create(string $oppId, array $d): string
{
    require_cap('valuation.edit');
    return tx(function () use ($oppId, $d) {
        $opp = acq_lock_status($oppId, ['COSTED', 'VALUED', 'NEGOTIATING']);
        $cs = latest_cost_sheet($oppId);
        if (!$cs) throw new AppError('ต้องมี Cost Sheet ที่ส่งแล้วก่อนทำ Valuation');
        $device = db_get('devices', $opp['device_id']);
        $insp = latest_completed_inspection($oppId);

        $fmv = v_money($d['fair_market_value'] ?? null, 'Fair Market Value', true, false);
        $rec = v_money($d['recommended_acq_price'] ?? null, 'ราคาแนะนำให้ซื้อ', true, false);
        $max = v_money($d['max_acq_price'] ?? null, 'ราคาซื้อสูงสุด', true, false);
        $target = v_money($d['target_selling_price'] ?? null, 'ราคาขายเป้าหมาย', true, false);
        $min = v_money($d['min_selling_price'] ?? null, 'ราคาขายต่ำสุด', true, false);
        if ((float) $max < (float) $rec) throw new AppError('ราคาซื้อสูงสุดต้องไม่ต่ำกว่าราคาแนะนำให้ซื้อ');
        if ((float) $target < (float) $min) throw new AppError('ราคาขายเป้าหมายต้องไม่ต่ำกว่าราคาขายต่ำสุด');
        [$gp, $margin] = compute_gp((float) $target, (float) $cs['total_estimated_cost']);

        $age = $device['manufacture_year'] ? (int) date('Y') - (int) $device['manufacture_year'] : null;
        $data = [
            'device_opportunity_id' => $oppId,
            'cost_sheet_id' => $cs['id'],
            'asking_price' => v_money($d['asking_price'] ?? null, 'ราคาที่ผู้ขายตั้ง') ?? $opp['asking_price'],
            'total_cost_snapshot' => $cs['total_estimated_cost'],
            'acquisition_assumption_snapshot' => cost_sheet_acquisition_assumption($cs['id']),
            'market_selling_price' => v_money($d['market_selling_price'] ?? null, 'ราคาขายในตลาด'),
            'historical_selling_price' => v_money($d['historical_selling_price'] ?? null, 'ราคาที่เคยขายได้'),
            'device_condition' => v_in($d['device_condition'] ?? ($insp['overall_condition'] ?? null), CONDITIONS, 'สภาพเครื่อง', false),
            'age_years' => v_decimal($d['age_years'] ?? $age, 'อายุเครื่อง (ปี)'),
            'usage_note' => v_maxlen(s($d['usage_note'] ?? null) ?? ($device['usage_value'] !== null ? rtrim(rtrim((string) $device['usage_value'], '0'), '.') . ' ' . label('usage_unit', $device['usage_unit']) : null), 150, 'การใช้งาน'),
            'demand' => v_in($d['demand'] ?? null, LEVELS, 'ความต้องการในตลาด', false),
            'technical_risk' => v_in($d['technical_risk'] ?? ($insp['technical_risk'] ?? null), LEVELS, 'ความเสี่ยงทางเทคนิค', false),
            'expected_days_to_sell' => v_int($d['expected_days_to_sell'] ?? null, 'ระยะเวลาคาดว่าจะขายได้ (วัน)', false, 0, 3650),
            'fair_market_value' => $fmv,
            'recommended_acq_price' => $rec,
            'max_acq_price' => $max,
            'target_selling_price' => $target,
            'min_selling_price' => $min,
            'expected_gp' => number_format($gp, 2, '.', ''),
            'expected_gp_margin' => (string) $margin,
            'notes' => s($d['notes'] ?? null),
        ];
        $id = insert_audited('valuations', 'VALUATION', $data);
        if ($opp['status'] === 'COSTED') {
            acq_set_status($opp, 'VALUED', [], 'บันทึก Valuation (อ้างอิง Cost Sheet ฉบับที่ ' . $cs['version_no'] . ')');
            task_create([
                'title' => 'เริ่มเจรจาราคากับผู้ขาย — ' . $opp['ref_no'],
                'type' => 'NEGOTIATION', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $oppId,
                'assignee_id' => $opp['owner_id'], 'due_date' => add_days(today(), 2),
            ]);
        } else {
            log_activity('DEVICE_OPPORTUNITY', $oppId, 'SYSTEM', 'บันทึก Valuation ใหม่ (อ้างอิง Cost Sheet ฉบับที่ ' . $cs['version_no'] . ')', ['organization_id' => $opp['seller_org_id'], 'device_id' => $opp['device_id']]);
        }
        tasks_close_for('DEVICE_OPPORTUNITY', $oppId, ['VALUATION']);
        return $id;
    });
}

// ---------------------------------------------------------------- 5. เจรจา

/** บันทึกข้อเสนอ/ข้อเสนอโต้กลับ — ทุกแถวเก็บถาวร แก้ไม่ได้ */
function negotiation_add(string $oppId, array $d): string
{
    require_cap('negotiation.edit');
    return tx(function () use ($oppId, $d) {
        $opp = acq_lock_status($oppId, ['VALUED', 'NEGOTIATING']);
        $party = v_in($d['party'] ?? null, ['AMN_OFFER', 'SELLER_COUNTER', 'AGREED'], 'ผู้เสนอ');
        $amount = v_money($d['amount'] ?? null, 'จำนวนเงิน', true, false);
        $id = insert_audited('negotiations', 'NEGOTIATION', [
            'device_opportunity_id' => $oppId, 'party' => $party, 'amount' => $amount,
            'offered_at' => now6(), 'user_id' => current_user_id(), 'note' => s($d['note'] ?? null),
            'is_final' => $party === 'AGREED' ? 1 : 0,
        ]);
        $extra = $party === 'AGREED' ? ['final_negotiated_price' => $amount] : [];
        if ($opp['status'] !== 'NEGOTIATING') {
            acq_set_status($opp, 'NEGOTIATING', $extra, 'บันทึกการเจรจา: ' . label('negotiation_party', $party));
        } else {
            if ($extra) update_audited('device_opportunities', 'DEVICE_OPPORTUNITY', $oppId, $extra, 'UPDATE', 'ราคาที่ตกลงกับผู้ขาย');
            // ไม่ใส่จำนวนเงินใน timeline (ทุก role ที่ดูดีลได้เห็น timeline) — ดูตัวเลขได้ในส่วน "ประวัติการเจรจา" ตามสิทธิ์
            log_activity('DEVICE_OPPORTUNITY', $oppId, 'SYSTEM', 'บันทึกการเจรจา: ' . label('negotiation_party', $party),
                ['organization_id' => $opp['seller_org_id'], 'device_id' => $opp['device_id']]);
        }
        tasks_close_for('DEVICE_OPPORTUNITY', $oppId, ['NEGOTIATION']);
        return $id;
    });
}

// ---------------------------------------------------------------- 6. ขออนุมัติ / GM อนุมัติ (BR-07)

/** สิ่งที่ยังขาดก่อนส่งขออนุมัติได้ */
function approval_missing(array $opp): array
{
    $m = [];
    if ($opp['final_negotiated_price'] === null) $m[] = 'ราคาที่ตกลงกับผู้ขาย (บันทึกเป็น "ราคาที่ตกลง" ในการเจรจา)';
    if (!latest_completed_inspection($opp['id'])) $m[] = 'ผลตรวจเครื่องที่เสร็จแล้ว';
    $cs = latest_cost_sheet($opp['id']);
    if (!$cs) $m[] = 'Cost Sheet ที่ส่งแล้ว';
    $val = latest_valuation($opp['id']);
    if (!$val) $m[] = 'Valuation';
    elseif ($cs && $val['cost_sheet_id'] !== $cs['id']) $m[] = 'Valuation ใหม่ที่อ้างอิง Cost Sheet ฉบับล่าสุด';
    return $m;
}

/** ชุดข้อมูลที่ GM เห็นตอนอนุมัติ — เก็บเป็น snapshot ไว้กับคำขออนุมัติ */
function build_approval_package(array $opp): array
{
    $device = db_get('devices', $opp['device_id']);
    $seller = one('SELECT id, ref_no, name, type, phone, line_id FROM organizations WHERE id = ?', [$opp['seller_org_id']]);
    $contact = $opp['seller_contact_id'] ? one('SELECT name, position, phone, line_id FROM contacts WHERE id = ?', [$opp['seller_contact_id']]) : null;
    $insp = latest_completed_inspection($opp['id']);
    $counts = [];
    $problems = [];
    foreach (all('SELECT * FROM inspection_items WHERE inspection_id = ? ORDER BY sort', [$insp['id']]) as $it) {
        $r = $it['result'] ?: 'NOT_CHECKED';
        $counts[$r] = ($counts[$r] ?? 0) + 1;
        if (in_array($it['result'], ['MINOR_ISSUE', 'MAJOR_ISSUE', 'MISSING'], true)) {
            $problems[] = ['category' => $it['category'], 'item' => $it['item_name'], 'result' => $it['result'], 'note' => $it['note']];
        }
    }
    $cs = latest_cost_sheet($opp['id']);
    $val = latest_valuation($opp['id']);
    $final = (float) $opp['final_negotiated_price'];
    $assumption = (float) cost_sheet_acquisition_assumption($cs['id']);
    $costAtFinal = round((float) $cs['total_estimated_cost'] - $assumption + $final, 2);
    [$gpFinal, $marginFinal] = compute_gp((float) $val['target_selling_price'], $costAtFinal);

    $flags = [];
    if ($final > (float) $val['max_acq_price']) $flags[] = 'ราคาที่ตกลงสูงกว่าราคาซื้อสูงสุดที่ประเมินไว้ (' . money($val['max_acq_price']) . ')';
    if ($gpFinal < 0) $flags[] = 'GP ที่ราคาตกลงติดลบ';
    if ($insp['technical_risk'] === 'HIGH') $flags[] = 'ความเสี่ยงทางเทคนิคสูง';
    if ($insp['overall_result'] === 'MAJOR_ISSUE') $flags[] = 'ผลตรวจพบปัญหาใหญ่';
    $repeat = (int) val('SELECT COUNT(*) FROM device_service_history WHERE device_id = ? AND is_repeat_failure = 1 AND archived_at IS NULL', [$device['id']]);
    if ($repeat) $flags[] = 'มีประวัติเสียซ้ำ ' . $repeat . ' ครั้ง';

    return [
        'generated_at' => now(),
        'opportunity' => [
            'ref_no' => $opp['ref_no'], 'asking_price' => $opp['asking_price'], 'expected_price' => $opp['expected_price'],
            'final_negotiated_price' => $opp['final_negotiated_price'], 'reason_for_sale' => $opp['reason_for_sale'], 'location' => $opp['location'],
            'owner' => user_name($opp['owner_id']),
        ],
        'seller' => $seller,
        'contact' => $contact,
        'device' => [
            'ref_no' => $device['ref_no'], 'brand' => $device['brand'], 'model' => $device['model'], 'category' => $device['category'],
            'serial_number' => $device['serial_number'], 'manufacture_year' => $device['manufacture_year'], 'installation_year' => $device['installation_year'],
            'usage' => $device['usage_value'] !== null ? $device['usage_value'] . ' ' . label('usage_unit', $device['usage_unit']) : null,
            'accessories' => $device['accessories'],
        ],
        'inspection' => [
            'completed_at' => $insp['completed_at'], 'engineer' => user_name($insp['engineer_id']),
            'overall_result' => $insp['overall_result'], 'overall_condition' => $insp['overall_condition'], 'technical_risk' => $insp['technical_risk'],
            'issues' => $insp['issues'], 'required_repair' => $insp['required_repair'], 'recommended_repair' => $insp['recommended_repair'],
            'missing_accessories' => $insp['missing_accessories'], 'est_repair_days' => $insp['est_repair_days'], 'usage_reading' => $insp['usage_reading'],
            'counts' => $counts, 'problems' => $problems,
        ],
        'service_history' => all('SELECT service_date, type, description, parts_replaced, performed_by, cost, is_repeat_failure FROM device_service_history
            WHERE device_id = ? AND archived_at IS NULL ORDER BY service_date DESC LIMIT 30', [$device['id']]),
        'ma' => all('SELECT provider, contract_no, start_date, end_date, coverage FROM ma_records WHERE device_id = ? AND archived_at IS NULL ORDER BY end_date DESC', [$device['id']]),
        'cost_sheet' => [
            'version_no' => $cs['version_no'], 'total' => $cs['total_estimated_cost'], 'acquisition_assumption' => number_format($assumption, 2, '.', ''),
            'items' => all('SELECT category, description, amount FROM cost_sheet_items WHERE cost_sheet_id = ? ORDER BY sort', [$cs['id']]),
        ],
        'valuation' => array_intersect_key($val, array_flip(['fair_market_value', 'recommended_acq_price', 'max_acq_price', 'target_selling_price',
            'min_selling_price', 'expected_gp', 'expected_gp_margin', 'market_selling_price', 'historical_selling_price', 'demand',
            'technical_risk', 'expected_days_to_sell', 'device_condition', 'age_years', 'usage_note', 'notes'])),
        'negotiations' => array_map(function ($n) { return ['party' => $n['party'], 'amount' => $n['amount'], 'at' => $n['offered_at'], 'by' => user_name($n['user_id']), 'note' => $n['note']]; },
            all('SELECT * FROM negotiations WHERE device_opportunity_id = ? ORDER BY offered_at', [$opp['id']])),
        'at_final_price' => ['cost' => number_format($costAtFinal, 2, '.', ''), 'gp' => number_format($gpFinal, 2, '.', ''), 'margin' => (string) $marginFinal],
        'flags' => $flags,
    ];
}

function approval_submit(string $oppId, array $d): string
{
    require_cap('acquisition.submit');
    return tx(function () use ($oppId, $d) {
        $opp = acq_lock_status($oppId, ['NEGOTIATING']);
        if (pending_approval($oppId)) throw new AppError('มีคำขออนุมัติที่รอ GM อยู่แล้ว');
        $missing = approval_missing($opp);
        if ($missing) throw new AppError('ยังส่งขออนุมัติไม่ได้ ขาด: ' . implode(' / ', $missing));
        $package = build_approval_package($opp);
        $id = insert_audited('approvals', 'APPROVAL', [
            'subject_type' => 'DEVICE_OPPORTUNITY',
            'subject_id' => $oppId,
            'requested_by' => current_user_id(),
            'requested_at' => now6(),
            'request_note' => s($d['note'] ?? null),
            'decision' => 'PENDING',
            'package_snapshot' => json_encode($package, JSON_UNESCAPED_UNICODE),
        ]);
        acq_set_status($opp, 'PENDING_APPROVAL', [], 'ส่งขออนุมัติซื้อให้ GM');
        task_create([
            'title' => 'พิจารณาอนุมัติการซื้อ ' . $opp['ref_no'],
            'type' => 'APPROVAL', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $oppId,
            'assignee_role' => 'GM', 'due_date' => add_days(today(), 1), 'priority' => 'HIGH',
        ]);
        return $id;
    });
}

/**
 * GM ตัดสินคำขออนุมัติ
 * $d: decision, comment, condition_text, approved_amount, return_to (NEGOTIATION|COST_SHEET)
 */
function approval_decide(string $approvalId, array $d): void
{
    require_cap('acquisition.approve');
    tx(function () use ($approvalId, $d) {
        $appr = db_lock('approvals', $approvalId, 'คำขออนุมัติ');
        if ($appr['decision'] !== 'PENDING') throw new AppError('คำขอนี้ถูกตัดสินไปแล้ว');
        $decision = v_in($d['decision'] ?? null, APPROVAL_DECISIONS, 'ผลการพิจารณา');
        $comment = s($d['comment'] ?? null);
        $condition = s($d['condition_text'] ?? null);
        if (in_array($decision, ['REVISION_REQUIRED', 'REJECTED'], true) && !$comment) throw new AppError('กรุณาระบุเหตุผล/ความเห็น');
        if ($decision === 'APPROVED_WITH_CONDITION' && !$condition) throw new AppError('กรุณาระบุเงื่อนไขการอนุมัติ');
        $opp = acq_lock_status($appr['subject_id'], ['PENDING_APPROVAL']);
        $amount = null;
        $returnTo = null;
        if (in_array($decision, ['APPROVED', 'APPROVED_WITH_CONDITION'], true)) {
            $amount = v_money($d['approved_amount'] ?? null, 'วงเงินที่อนุมัติ', false, false) ?? $opp['final_negotiated_price'];
        }
        if ($decision === 'REVISION_REQUIRED') {
            $returnTo = v_in($d['return_to'] ?? 'NEGOTIATION', ['NEGOTIATION', 'COST_SHEET'], 'ส่งกลับไปขั้น');
        }
        update_audited('approvals', 'APPROVAL', $approvalId, [
            'decision' => $decision, 'approver_id' => current_user_id(), 'decided_at' => now6(),
            'comment' => $comment, 'condition_text' => $decision === 'APPROVED_WITH_CONDITION' ? $condition : null,
            'approved_amount' => $amount, 'return_to' => $returnTo,
        ], 'APPROVE', label('approval', $decision));
        tasks_close_for('DEVICE_OPPORTUNITY', $opp['id'], ['APPROVAL']);

        $note = label('approval', $decision) . ($comment ? ': ' . $comment : '') . ($condition ? "\nเงื่อนไข: " . $condition : '');
        if ($amount !== null) {
            acq_set_status($opp, 'APPROVED', [], $note);
            task_create([
                'title' => 'บันทึกการซื้อ (Acquisition) — ' . $opp['ref_no'],
                'type' => 'ACQUISITION', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $opp['id'],
                'assignee_role' => 'SALES_COORDINATOR', 'due_date' => add_days(today(), 2), 'priority' => 'HIGH',
            ]);
        } elseif ($decision === 'REVISION_REQUIRED') {
            acq_set_status($opp, $returnTo === 'COST_SHEET' ? 'INSPECTED' : 'NEGOTIATING', [], $note);
            task_create([
                'title' => 'GM ให้แก้ไข (' . ($returnTo === 'COST_SHEET' ? 'Cost Sheet' : 'การเจรจา') . ') — ' . $opp['ref_no'],
                'type' => $returnTo === 'COST_SHEET' ? 'COST_SHEET' : 'NEGOTIATION', 'parent_type' => 'DEVICE_OPPORTUNITY', 'parent_id' => $opp['id'],
                'assignee_id' => $returnTo === 'COST_SHEET' ? null : $opp['owner_id'],
                'assignee_role' => $returnTo === 'COST_SHEET' ? 'SERVICE_DIRECTOR' : null,
                'due_date' => add_days(today(), 2), 'priority' => 'HIGH',
            ]);
        } else {
            acq_set_status($opp, 'REJECTED', ['closed_at' => now()], $note);
            acq_release_device($opp);
            tasks_cancel_for('DEVICE_OPPORTUNITY', $opp['id']);
            lead_refresh_status($opp['lead_id']);
        }
    });
}

function acq_release_device(array $opp): void
{
    $dv = db_get('devices', $opp['device_id']);
    if ($dv['commercial_status'] === 'UNDER_OFFER') {
        update_audited('devices', 'DEVICE', $dv['id'], ['commercial_status' => (int) $dv['owned_by_amn'] === 1 ? 'IN_INVENTORY' : 'EXTERNAL'], 'STATUS_CHANGE', 'ปิดดีลซื้อ ' . $opp['ref_no']);
    }
}

// ---------------------------------------------------------------- 7. ซื้อ (Acquisition) + สต็อก (BR-07, BR-08)

function acquisition_create(string $oppId, array $d): string
{
    require_cap('acquisition.create');
    return tx(function () use ($oppId, $d) {
        $opp = acq_lock_status($oppId, ['APPROVED']);
        $appr = latest_approval($oppId);
        if (!$appr || !in_array($appr['decision'], ['APPROVED', 'APPROVED_WITH_CONDITION'], true)) {
            throw new AppError('ต้องได้รับอนุมัติจาก GM ก่อนบันทึกการซื้อ (BR-07)');
        }
        if ($appr['decision'] === 'APPROVED_WITH_CONDITION' && (empty($d['conditions_confirmed']) || $d['conditions_confirmed'] === '0')) {
            throw new AppError('กรุณายืนยันว่าได้ทำตามเงื่อนไขของ GM แล้ว: ' . $appr['condition_text']);
        }
        $price = v_money($d['purchase_price'] ?? null, 'ราคาซื้อจริง', true, false);
        if ((float) $price > (float) $appr['approved_amount'] + 0.001) {
            throw new AppError('ราคาซื้อจริง (' . money($price) . ') สูงกว่าวงเงินที่ GM อนุมัติ (' . money($appr['approved_amount']) . ') — ต้องขออนุมัติใหม่');
        }
        $takesStock = !isset($d['takes_stock']) || ($d['takes_stock'] !== '0' && $d['takes_stock'] !== '');
        $data = [
            'ref_no' => next_ref('ACQ'),
            'device_opportunity_id' => $oppId,
            'approval_id' => $appr['id'],
            'device_id' => $opp['device_id'],
            'seller_org_id' => $opp['seller_org_id'],
            'purchase_price' => $price,
            'purchase_date' => v_date($d['purchase_date'] ?? null, 'วันที่ซื้อ', true),
            'payment_status' => v_in($d['payment_status'] ?? 'UNPAID', PAYMENT_STATUSES, 'สถานะการจ่ายเงิน'),
            'takes_stock' => $takesStock ? 1 : 0,
            'conditions_confirmed' => $appr['decision'] === 'APPROVED_WITH_CONDITION' ? 1 : 0,
            'notes' => s($d['notes'] ?? null),
        ];
        $acqId = insert_audited('acquisitions', 'ACQUISITION', $data);
        device_transfer_ownership($opp['device_id'], null, true, 'ACQUISITION', ['acquisition_id' => $acqId]);
        $devStatus = 'PURCHASED';
        if ($takesStock) {
            $invId = insert_audited('inventory', 'INVENTORY', [
                'ref_no' => next_ref('INV', false, 6),
                'device_id' => $opp['device_id'],
                'acquisition_id' => $acqId,
                'source' => 'ACQUISITION',
                'received_date' => $data['purchase_date'],
                'storage_location' => v_maxlen(s($d['storage_location'] ?? null), 150, 'ที่เก็บ'),
                'status' => 'IN_STOCK',
                'book_cost' => $price,
                'list_price' => v_money($d['list_price'] ?? null, 'ราคาตั้งขาย'),
            ]);
            $devStatus = 'IN_INVENTORY';
            // ดีลขายที่จับคู่เครื่องนี้ไว้จากฝั่งผู้ขาย → ตอนนี้กลายเป็นของในสต็อก
            foreach (all("SELECT id FROM device_matches WHERE device_opportunity_id = ?", [$oppId]) as $m) {
                update_audited('device_matches', 'DEVICE_MATCH', $m['id'], ['inventory_id' => $invId, 'source' => 'INVENTORY'], 'UPDATE', 'เครื่องเข้าสต็อกจาก ' . $data['ref_no']);
            }
        }
        update_audited('devices', 'DEVICE', $opp['device_id'], ['commercial_status' => $devStatus], 'STATUS_CHANGE', 'ซื้อเข้า ' . $data['ref_no']);
        acq_set_status($opp, 'PURCHASED', ['closed_at' => now()], 'ซื้อแล้ว ' . $data['ref_no'] . ($takesStock ? ' · เข้าสต็อก' : ''));
        tasks_close_for('DEVICE_OPPORTUNITY', $oppId);
        lead_refresh_status($opp['lead_id']);
        return $acqId;
    });
}

function acq_mark_lost(string $oppId, string $reason): void
{
    require_cap('lead.edit');
    tx(function () use ($oppId, $reason) {
        $opp = acq_lock_status($oppId, ['NEW_SELLER_LEAD', 'WAITING_INSPECTION', 'INSPECTION_IN_PROGRESS', 'INSPECTED', 'COSTED', 'VALUED', 'NEGOTIATING', 'APPROVED']);
        $reason = s($reason);
        if (!$reason) throw new AppError('กรุณาระบุเหตุผลที่ดีลไม่สำเร็จ');
        foreach (all("SELECT id FROM inspections WHERE device_opportunity_id = ? AND status IN ('REQUESTED','IN_PROGRESS')", [$oppId]) as $i) {
            update_audited('inspections', 'INSPECTION', $i['id'], ['status' => 'CANCELLED'], 'STATUS_CHANGE', 'ดีลถูกยกเลิก');
            q("UPDATE technical_jobs SET status = 'CANCELLED', updated_at = ?, updated_by = ? WHERE parent_type = 'INSPECTION' AND parent_id = ? AND status NOT IN ('DONE','CANCELLED')", [now(), current_user_id(), $i['id']]);
            tasks_cancel_for('INSPECTION', $i['id']);
        }
        acq_set_status($opp, 'LOST', ['closed_at' => now(), 'lost_reason' => mb_substr($reason, 0, 255)], 'เหตุผล: ' . $reason);
        acq_release_device($opp);
        tasks_cancel_for('DEVICE_OPPORTUNITY', $oppId);
        lead_refresh_status($opp['lead_id']);
    });
}

// ---------------------------------------------------------------- inventory

function inventory_update(string $id, array $d): void
{
    require_cap('inventory.edit');
    $inv = db_get('inventory', $id, 'รายการสต็อก');
    if (!in_array($inv['status'], ['IN_STOCK', 'RESERVED'], true)) throw new AppError('แก้ไขได้เฉพาะรายการที่ยังอยู่ในสต็อก');
    update_audited('inventory', 'INVENTORY', $id, [
        'list_price' => v_money($d['list_price'] ?? null, 'ราคาตั้งขาย'),
        'storage_location' => v_maxlen(s($d['storage_location'] ?? null), 150, 'ที่เก็บ'),
        'notes' => s($d['notes'] ?? null),
    ]);
}

/** ข้อมูลทั้งหมดสำหรับหน้า Device Opportunity */
function acq_view_data(string $id): array
{
    $opp = acq_get($id);
    $d = ['opp' => $opp];
    $d['device'] = db_get('devices', $opp['device_id']);
    $d['seller'] = db_get('organizations', $opp['seller_org_id']);
    $d['contact'] = $opp['seller_contact_id'] ? db_find('contacts', $opp['seller_contact_id']) : null;
    $d['lead'] = db_get('leads', $opp['lead_id']);
    $d['inspections'] = can('inspection.view') ? all('SELECT i.*, u.name AS engineer_name FROM inspections i LEFT JOIN users u ON u.id = i.engineer_id
        WHERE i.device_opportunity_id = ? ORDER BY i.requested_at DESC', [$id]) : [];
    $d['cost_sheets'] = can('finance.view') ? all('SELECT * FROM cost_sheets WHERE device_opportunity_id = ? ORDER BY version_no DESC', [$id]) : [];
    $d['valuations'] = (can('finance.view') || can('valuation.recommended_view'))
        ? redact('valuations', all('SELECT v.*, cs.version_no AS cs_version FROM valuations v JOIN cost_sheets cs ON cs.id = v.cost_sheet_id WHERE v.device_opportunity_id = ? ORDER BY v.created_at DESC', [$id]))
        : [];
    $d['negotiations'] = can('negotiation.view') ? all('SELECT n.*, u.name AS user_name FROM negotiations n JOIN users u ON u.id = n.user_id
        WHERE n.device_opportunity_id = ? ORDER BY n.offered_at DESC', [$id]) : [];
    $d['approvals'] = all("SELECT a.*, r.name AS requester_name, ap.name AS approver_name FROM approvals a JOIN users r ON r.id = a.requested_by
        LEFT JOIN users ap ON ap.id = a.approver_id WHERE a.subject_type = 'DEVICE_OPPORTUNITY' AND a.subject_id = ? ORDER BY a.requested_at DESC", [$id]);
    $d['acquisition'] = one('SELECT * FROM acquisitions WHERE device_opportunity_id = ?', [$id]);
    $d['activities'] = activities_for('DEVICE_OPPORTUNITY', $id);
    $d['documents'] = documents_for('DEVICE_OPPORTUNITY', $id);
    $d['tasks'] = tasks_for_parent('DEVICE_OPPORTUNITY', $id);
    $d['matches'] = can('sales.view') ? all('SELECT m.*, s.ref_no AS so_ref, org.name AS buyer_name FROM device_matches m JOIN sales_opportunities s ON s.id = m.sales_opportunity_id
        JOIN organizations org ON org.id = s.buyer_org_id WHERE m.device_opportunity_id = ?', [$id]) : [];
    return $d;
}
