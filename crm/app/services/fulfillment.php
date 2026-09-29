<?php
// หลังปิดการขาย: Internal checklist → QC (BR-12) → ส่งมอบ (BR-13) → ติดตั้ง → TRANSACTION_COMPLETED

const TRX_STATUSES = ['IN_PREPARATION', 'READY_FOR_DELIVERY', 'DELIVERED', 'TRANSACTION_COMPLETED', 'CANCELLED'];
const CHECKLIST_SECTIONS = ['COMMERCIAL', 'SALES', 'MARKETING', 'TECHNICAL'];
const CHECKLIST_ROLE = ['COMMERCIAL' => 'SALES_COORDINATOR', 'SALES' => 'SALES_COORDINATOR', 'MARKETING' => 'MARKETING', 'TECHNICAL' => 'SERVICE_ENGINEER'];

function trx_get(string $id): array
{
    require_cap('transaction.view');
    return db_get('sales_transactions', $id, 'ธุรกรรมขาย');
}

function trx_lock_status(string $id, array $allowed): array
{
    $t = db_lock('sales_transactions', $id, 'ธุรกรรมขาย');
    if (!in_array($t['status'], $allowed, true)) throw new AppError('ทำรายการนี้ไม่ได้ เพราะธุรกรรมอยู่ในสถานะ "' . label('status', $t['status']) . '"');
    return $t;
}

function trx_log(array $t, string $text, ?string $deviceId = null): void
{
    log_activity('SALES_TRANSACTION', $t['id'], 'SYSTEM', $text, ['organization_id' => $t['buyer_org_id'], 'device_id' => $deviceId]);
}

/** เครื่องในธุรกรรม (จากบรรทัดใบเสนอราคาที่ลูกค้าตอบรับ) */
function trx_devices(array $t): array
{
    return all('SELECT DISTINCT dv.* FROM quotation_version_lines l JOIN devices dv ON dv.id = l.device_id WHERE l.quotation_version_id = ? ORDER BY dv.ref_no', [$t['quotation_version_id']]);
}

function trx_device_ids(array $t): array
{
    return array_column(trx_devices($t), 'id');
}

function checklist_create_for(string $trxId, string $trxRef): void
{
    $templates = [];
    foreach (all("SELECT label, meta FROM master_data WHERE type = 'CHECKLIST_ITEM' AND active = 1 ORDER BY sort") as $r) {
        $m = json_decode((string) $r['meta'], true) ?: [];
        $sec = $m['section'] ?? 'COMMERCIAL';
        $templates[$sec][] = ['label' => $r['label'], 'is_mandatory' => !empty($m['mandatory']) ? 1 : 0];
    }
    foreach (CHECKLIST_SECTIONS as $sec) {
        $clId = db_insert('checklists', ['sales_transaction_id' => $trxId, 'section' => $sec, 'status' => 'OPEN']);
        $sort = 0;
        foreach ($templates[$sec] ?? [] as $it) db_insert('checklist_items', $it + ['checklist_id' => $clId, 'sort' => $sort += 10]);
        checklist_refresh($clId);
        if (val('SELECT status FROM checklists WHERE id = ?', [$clId]) === 'OPEN') {
            task_create([
                'title' => 'เช็กลิสต์ ' . label('checklist_section', $sec) . ' — ' . $trxRef,
                'type' => 'CHECKLIST_' . $sec, 'parent_type' => 'SALES_TRANSACTION', 'parent_id' => $trxId,
                'assignee_role' => CHECKLIST_ROLE[$sec], 'due_date' => add_days(today(), 3), 'priority' => 'HIGH',
            ]);
        }
    }
}

/** หมวดเสร็จเมื่อรายการบังคับทำครบ */
function checklist_refresh(string $checklistId): void
{
    $cl = db_get('checklists', $checklistId);
    $left = (int) val('SELECT COUNT(*) FROM checklist_items WHERE checklist_id = ? AND is_mandatory = 1 AND done = 0', [$checklistId]);
    if ($left === 0 && $cl['status'] !== 'DONE') {
        db_update('checklists', $checklistId, ['status' => 'DONE', 'completed_at' => now(), 'completed_by' => current_user_id()]);
        tasks_close_for('SALES_TRANSACTION', $cl['sales_transaction_id'], ['CHECKLIST_' . $cl['section']]);
    } elseif ($left > 0 && $cl['status'] === 'DONE') {
        db_update('checklists', $checklistId, ['status' => 'OPEN', 'completed_at' => null, 'completed_by' => null]);
    }
}

function checklist_item_set(string $itemId, bool $done, ?string $note): void
{
    tx(function () use ($itemId, $done, $note) {
        $item = db_lock('checklist_items', $itemId, 'รายการเช็กลิสต์');
        $cl = db_get('checklists', $item['checklist_id']);
        require_cap('checklist.' . $cl['section']);
        $t = trx_lock_status($cl['sales_transaction_id'], $done ? ['IN_PREPARATION', 'READY_FOR_DELIVERY'] : ['IN_PREPARATION']);
        update_audited('checklist_items', 'CHECKLIST_ITEM', $itemId, [
            'done' => $done ? 1 : 0, 'done_by' => $done ? current_user_id() : null, 'done_at' => $done ? now() : null,
            'note' => s($note) ?? $item['note'],
        ]);
        checklist_refresh($cl['id']);
        trx_log($t, ($done ? '✓ ' : '✗ ') . label('checklist_section', $cl['section']) . ': ' . $item['label']);
    });
}

// ---------------------------------------------------------------- QC (BR-12)

function qc_items(): array
{
    return all("SELECT code, label FROM master_data WHERE type = 'QC_ITEM' AND active = 1 ORDER BY sort");
}

/** $d['checks'][code] = ['result' => PASS|FAIL|NA, 'note' => ...] */
function qc_record(string $trxId, string $deviceId, array $d): string
{
    require_cap('qc.edit');
    return tx(function () use ($trxId, $deviceId, $d) {
        $t = trx_lock_status($trxId, ['IN_PREPARATION']);
        if (!in_array($deviceId, trx_device_ids($t), true)) throw new AppError('เครื่องนี้ไม่ได้อยู่ในธุรกรรมนี้');
        $checks = [];
        $fail = false;
        foreach (qc_items() as $it) {
            $v = $d['checks'][$it['code']] ?? [];
            $result = v_in($v['result'] ?? null, ['PASS', 'FAIL', 'NA'], 'ผล QC: ' . $it['label']);
            if ($result === 'FAIL') $fail = true;
            $checks[] = ['code' => $it['code'], 'item' => $it['label'], 'result' => $result, 'note' => s($v['note'] ?? null)];
        }
        $overall = $fail ? 'FAIL' : 'PASS';
        $id = insert_audited('qc_records', 'QC', [
            'sales_transaction_id' => $trxId, 'device_id' => $deviceId, 'engineer_id' => current_user_id(), 'checked_at' => now6(),
            'checks_json' => json_encode($checks, JSON_UNESCAPED_UNICODE), 'overall_result' => $overall, 'note' => s($d['note'] ?? null),
        ]);
        $dv = db_get('devices', $deviceId);
        if ($overall === 'PASS') {
            update_audited('devices', 'DEVICE', $deviceId, ['technical_status' => 'QC_PASSED'], 'STATUS_CHANGE', 'QC ผ่าน (' . $t['ref_no'] . ')');
        } else {
            update_audited('devices', 'DEVICE', $deviceId, ['technical_status' => 'NEEDS_REPAIR'], 'STATUS_CHANGE', 'QC ไม่ผ่าน (' . $t['ref_no'] . ')');
            $failed = array_filter($checks, function ($c) { return $c['result'] === 'FAIL'; });
            $jobId = db_insert('technical_jobs', [
                'ref_no' => next_ref('JOB'), 'type' => 'REPAIR', 'device_id' => $deviceId,
                'parent_type' => 'SALES_TRANSACTION', 'parent_id' => $trxId,
                'title' => 'แก้ไขหลัง QC ไม่ผ่าน: ' . $dv['brand'] . ' ' . $dv['model'] . ' (' . implode(', ', array_column($failed, 'item')) . ')',
                'status' => 'OPEN',
            ]);
            audit_log('TECHNICAL_JOB', $jobId, 'CREATE', null, 'QC ไม่ผ่าน ' . $t['ref_no']);
            task_create([
                'title' => 'ซ่อม/แก้ไขเครื่องหลัง QC ไม่ผ่าน — ' . $dv['ref_no'] . ' (' . $t['ref_no'] . ')',
                'type' => 'REPAIR', 'parent_type' => 'TECHNICAL_JOB', 'parent_id' => $jobId,
                'assignee_role' => 'SERVICE_ENGINEER', 'due_date' => add_days(today(), 3), 'priority' => 'HIGH',
            ]);
        }
        trx_log($t, 'QC ' . $dv['ref_no'] . ': ' . label('result', $overall), $deviceId);
        return $id;
    });
}

function latest_qc(string $trxId, string $deviceId): ?array
{
    return one('SELECT * FROM qc_records WHERE sales_transaction_id = ? AND device_id = ? ORDER BY checked_at DESC LIMIT 1', [$trxId, $deviceId]);
}

/** สิ่งที่ยังขาดก่อนเปลี่ยนเป็น READY_FOR_DELIVERY */
function trx_ready_missing(array $t): array
{
    $m = [];
    foreach (all('SELECT c.section, ci.label FROM checklist_items ci JOIN checklists c ON c.id = ci.checklist_id
                  WHERE c.sales_transaction_id = ? AND ci.is_mandatory = 1 AND ci.done = 0 ORDER BY c.section, ci.sort', [$t['id']]) as $r) {
        $m[] = 'เช็กลิสต์ ' . label('checklist_section', $r['section']) . ': ' . $r['label'];
    }
    foreach (trx_devices($t) as $dv) {
        $qc = latest_qc($t['id'], $dv['id']);
        if (!$qc || $qc['overall_result'] !== 'PASS') $m[] = 'QC ผ่านสำหรับ ' . $dv['ref_no'] . ' (BR-12)';
        if ((int) $dv['owned_by_amn'] !== 1) $m[] = 'AMN Sure ต้องซื้อเครื่อง ' . $dv['ref_no'] . ' เข้ามาก่อน (Acquisition)';
    }
    if (!trx_devices($t)) $m[] = 'ธุรกรรมต้องมีเครื่องอย่างน้อย 1 เครื่อง';
    return $m;
}

function trx_mark_ready(string $trxId): void
{
    require_cap('qc.edit');
    tx(function () use ($trxId) {
        $t = trx_lock_status($trxId, ['IN_PREPARATION']);
        $missing = trx_ready_missing($t);
        if ($missing) throw new AppError('ยังพร้อมส่งมอบไม่ได้: ' . implode(' / ', $missing));
        update_audited('sales_transactions', 'SALES_TRANSACTION', $trxId, ['status' => 'READY_FOR_DELIVERY', 'ready_at' => now()], 'STATUS_CHANGE');
        trx_log($t, 'สถานะ: เตรียมส่งมอบ → พร้อมส่งมอบ');
        task_create([
            'title' => 'ส่งมอบเครื่องให้ลูกค้า — ' . $t['ref_no'],
            'type' => 'DELIVERY', 'parent_type' => 'SALES_TRANSACTION', 'parent_id' => $trxId,
            'assignee_role' => 'SERVICE_ENGINEER', 'due_date' => add_days(today(), 3), 'priority' => 'HIGH',
        ]);
    });
}

// ---------------------------------------------------------------- ส่งมอบ / ติดตั้ง (BR-13)

function delivery_record(string $trxId, string $deviceId, array $d, ?array $file): string
{
    require_cap('delivery.edit');
    return tx(function () use ($trxId, $deviceId, $d, $file) {
        $t = trx_lock_status($trxId, ['READY_FOR_DELIVERY']);
        if (!in_array($deviceId, trx_device_ids($t), true)) throw new AppError('เครื่องนี้ไม่ได้อยู่ในธุรกรรมนี้');
        if (val('SELECT id FROM deliveries WHERE sales_transaction_id = ? AND device_id = ?', [$trxId, $deviceId])) throw new AppError('บันทึกการส่งมอบเครื่องนี้ไปแล้ว');
        $dv = db_get('devices', $deviceId);
        // BR-13: ต้องยืนยัน serial ของเครื่องที่ส่งจริง (ถ้าเครื่องไม่มี serial ให้ใช้ Device ID)
        $typed = normalize_serial($d['serial_confirmed'] ?? null);
        $expected = $dv['serial_normalized'] ?: normalize_serial($dv['ref_no']);
        if (!$typed || $typed !== $expected) {
            throw new AppError('Serial ที่ยืนยันไม่ตรงกับเครื่อง ' . $dv['ref_no'] . ' ในระบบ — ตรวจเครื่องที่ส่งจริงอีกครั้ง (BR-13)');
        }
        $receiver = v_maxlen(s($d['receiving_person'] ?? null), 150, 'ผู้รับสินค้า');
        if (!$receiver) throw new AppError('กรุณากรอกชื่อผู้รับสินค้า');
        $date = v_date($d['delivered_date'] ?? null, 'วันที่ส่งมอบ', true);
        $id = insert_audited('deliveries', 'DELIVERY', [
            'sales_transaction_id' => $trxId, 'device_id' => $deviceId, 'delivered_date' => $date,
            'address' => s($d['address'] ?? null), 'transport_method' => v_maxlen(s($d['transport_method'] ?? null), 100, 'วิธีขนส่ง'),
            'engineer_id' => current_user_id(), 'serial_confirmed' => (string) s($d['serial_confirmed']),
            'accessories_delivered' => s($d['accessories_delivered'] ?? null), 'receiving_person' => $receiver, 'note' => s($d['note'] ?? null),
        ]);
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            document_add('SALES_TRANSACTION', $trxId, $file, ['category' => 'PHOTO', 'title' => 'หลักฐานการส่งมอบ ' . $dv['ref_no']], true);
        }
        update_audited('devices', 'DEVICE', $deviceId, ['commercial_status' => 'DELIVERED', 'current_location' => s($d['address'] ?? null) ?? $dv['current_location']], 'STATUS_CHANGE', 'ส่งมอบ ' . $t['ref_no']);
        trx_log($t, 'ส่งมอบ ' . $dv['ref_no'] . ' ให้ ' . $receiver . ' เมื่อ ' . d($date), $deviceId);

        $left = array_filter(trx_device_ids($t), function ($id) use ($trxId) { return !val('SELECT id FROM deliveries WHERE sales_transaction_id = ? AND device_id = ?', [$trxId, $id]); });
        if (!$left) {
            update_audited('sales_transactions', 'SALES_TRANSACTION', $trxId, ['status' => 'DELIVERED', 'delivered_at' => now()], 'STATUS_CHANGE');
            tasks_close_for('SALES_TRANSACTION', $trxId, ['DELIVERY']);
            if ((int) $t['installation_required'] === 1) {
                trx_log($t, 'สถานะ: พร้อมส่งมอบ → ส่งมอบแล้ว');
                task_create([
                    'title' => 'ติดตั้งและทดสอบเครื่อง — ' . $t['ref_no'],
                    'type' => 'INSTALLATION', 'parent_type' => 'SALES_TRANSACTION', 'parent_id' => $trxId,
                    'assignee_role' => 'SERVICE_ENGINEER', 'due_date' => add_days(today(), 3), 'priority' => 'HIGH',
                ]);
            } else {
                trx_complete(db_get('sales_transactions', $trxId), false);
            }
        }
        return $id;
    });
}

function installation_record(string $trxId, string $deviceId, array $d, ?array $file): string
{
    require_cap('installation.edit');
    return tx(function () use ($trxId, $deviceId, $d, $file) {
        $t = trx_lock_status($trxId, ['DELIVERED']);
        if (!in_array($deviceId, trx_device_ids($t), true)) throw new AppError('เครื่องนี้ไม่ได้อยู่ในธุรกรรมนี้');
        $result = v_in($d['result'] ?? null, ['SUCCESS', 'PARTIAL', 'FAILED'], 'ผลการติดตั้ง');
        $accepted = !empty($d['customer_accepted']) && $d['customer_accepted'] !== '0';
        $acceptedBy = v_maxlen(s($d['accepted_by_name'] ?? null), 150, 'ผู้ตรวจรับฝั่งลูกค้า');
        if ($accepted && !$acceptedBy) throw new AppError('กรุณากรอกชื่อผู้ตรวจรับฝั่งลูกค้า');
        $dv = db_get('devices', $deviceId);
        $id = insert_audited('installations', 'INSTALLATION', [
            'sales_transaction_id' => $trxId, 'device_id' => $deviceId,
            'installed_date' => v_date($d['installed_date'] ?? null, 'วันที่ติดตั้ง', true),
            'engineer_id' => current_user_id(), 'result' => $result,
            'system_test_result' => s($d['system_test_result'] ?? null),
            'customer_accepted' => $accepted ? 1 : 0, 'accepted_by_name' => $acceptedBy,
            'training_done' => !empty($d['training_done']) && $d['training_done'] !== '0' ? 1 : 0,
            'note' => s($d['note'] ?? null),
        ]);
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            document_add('SALES_TRANSACTION', $trxId, $file, ['category' => 'OTHER', 'title' => 'เอกสารติดตั้ง/ตรวจรับ ' . $dv['ref_no']], true);
        }
        trx_log($t, 'ติดตั้ง ' . $dv['ref_no'] . ': ' . label('result', $result) . ($accepted ? ' · ลูกค้าตรวจรับแล้ว (' . $acceptedBy . ')' : ' · ลูกค้ายังไม่ตรวจรับ'), $deviceId);

        $allDone = true;
        foreach (trx_device_ids($t) as $devId) {
            $last = one('SELECT * FROM installations WHERE sales_transaction_id = ? AND device_id = ? ORDER BY created_at DESC LIMIT 1', [$trxId, $devId]);
            if (!$last || $last['result'] !== 'SUCCESS' || (int) $last['customer_accepted'] !== 1) { $allDone = false; break; }
        }
        if ($allDone) trx_complete(db_get('sales_transactions', $trxId), true);
        return $id;
    });
}

/** ปิดธุรกรรม: โอนความเป็นเจ้าของเครื่องให้ผู้ซื้อ, สต็อก → SOLD */
function trx_complete(array $t, bool $installed): void
{
    foreach (trx_devices($t) as $dv) {
        device_transfer_ownership($dv['id'], $t['buyer_org_id'], false, 'SALE', ['sales_transaction_id' => $t['id']]);
        update_audited('devices', 'DEVICE', $dv['id'], ['commercial_status' => $installed ? 'INSTALLED_AT_CUSTOMER' : 'DELIVERED'], 'STATUS_CHANGE', 'ขายให้ลูกค้า ' . $t['ref_no']);
        foreach (all("SELECT id FROM inventory WHERE device_id = ? AND status IN ('RESERVED','IN_STOCK')", [$dv['id']]) as $inv) {
            update_audited('inventory', 'INVENTORY', $inv['id'], ['status' => 'SOLD'], 'STATUS_CHANGE', $t['ref_no']);
        }
    }
    update_audited('sales_transactions', 'SALES_TRANSACTION', $t['id'], ['status' => 'TRANSACTION_COMPLETED', 'completed_at' => now()], 'STATUS_CHANGE');
    tasks_close_for('SALES_TRANSACTION', $t['id']);
    trx_log($t, 'ธุรกรรมเสร็จสมบูรณ์ — เครื่องเป็นของลูกค้าแล้ว');
}

function trx_cancel(string $trxId, string $reason): void
{
    require_cap('transaction.cancel');
    $reason = s($reason);
    if (!$reason) throw new AppError('กรุณาระบุเหตุผลในการยกเลิก');
    tx(function () use ($trxId, $reason) {
        $t = trx_lock_status($trxId, ['IN_PREPARATION', 'READY_FOR_DELIVERY']);
        update_audited('sales_transactions', 'SALES_TRANSACTION', $trxId, ['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 255)], 'VOID', $reason);
        foreach (all("SELECT * FROM inventory WHERE reserved_for_transaction_id = ? AND status = 'RESERVED'", [$trxId]) as $inv) {
            update_audited('inventory', 'INVENTORY', $inv['id'], ['status' => 'IN_STOCK', 'reserved_for_transaction_id' => null], 'STATUS_CHANGE', 'ยกเลิก ' . $t['ref_no']);
        }
        foreach (trx_devices($t) as $dv) {
            if ($dv['commercial_status'] === 'RESERVED') {
                update_audited('devices', 'DEVICE', $dv['id'], ['commercial_status' => (int) $dv['owned_by_amn'] === 1 ? 'IN_INVENTORY' : 'UNDER_OFFER'], 'STATUS_CHANGE', 'ยกเลิก ' . $t['ref_no']);
            }
        }
        tasks_cancel_for('SALES_TRANSACTION', $trxId);
        $so = db_get('sales_opportunities', $t['sales_opportunity_id']);
        update_audited('sales_opportunities', 'SALES_OPPORTUNITY', $so['id'], ['status' => 'LOST', 'lost_reason' => mb_substr('ยกเลิกหลังปิดการขาย: ' . $reason, 0, 255)], 'STATUS_CHANGE');
        trx_log($t, 'ยกเลิกธุรกรรม: ' . $reason);
    });
}

function trx_view_data(string $id): array
{
    $t = trx_get($id);
    $d = ['trx' => $t];
    $d['so'] = db_get('sales_opportunities', $t['sales_opportunity_id']);
    $d['buyer'] = db_get('organizations', $t['buyer_org_id']);
    $d['qv'] = db_get('quotation_versions', $t['quotation_version_id']);
    $d['quotation'] = db_get('quotations', $d['qv']['quotation_id']);
    $d['lines'] = all('SELECT * FROM quotation_version_lines WHERE quotation_version_id = ? ORDER BY sort', [$t['quotation_version_id']]);
    $d['contract'] = $t['contract_id'] ? db_find('contracts', $t['contract_id']) : null;
    $d['devices'] = trx_devices($t);
    $d['checklists'] = [];
    foreach (all('SELECT * FROM checklists WHERE sales_transaction_id = ? ORDER BY FIELD(section, \'COMMERCIAL\', \'SALES\', \'MARKETING\', \'TECHNICAL\')', [$id]) as $cl) {
        $cl['items'] = all('SELECT ci.*, u.name AS done_by_name FROM checklist_items ci LEFT JOIN users u ON u.id = ci.done_by WHERE ci.checklist_id = ? ORDER BY ci.sort', [$cl['id']]);
        $d['checklists'][] = $cl;
    }
    $d['qc'] = all('SELECT q.*, u.name AS engineer_name FROM qc_records q JOIN users u ON u.id = q.engineer_id WHERE q.sales_transaction_id = ? ORDER BY q.checked_at DESC', [$id]);
    $d['deliveries'] = all('SELECT dl.*, u.name AS engineer_name FROM deliveries dl LEFT JOIN users u ON u.id = dl.engineer_id WHERE dl.sales_transaction_id = ? ORDER BY dl.created_at', [$id]);
    $d['installations'] = all('SELECT i.*, u.name AS engineer_name FROM installations i LEFT JOIN users u ON u.id = i.engineer_id WHERE i.sales_transaction_id = ? ORDER BY i.created_at DESC', [$id]);
    $d['ready_missing'] = $t['status'] === 'IN_PREPARATION' ? trx_ready_missing($t) : [];
    $d['activities'] = activities_for('SALES_TRANSACTION', $id);
    $d['documents'] = documents_for('SALES_TRANSACTION', $id);
    $d['tasks'] = tasks_for_parent('SALES_TRANSACTION', $id);
    $d['jobs'] = can('job.view') ? all("SELECT * FROM technical_jobs WHERE parent_type = 'SALES_TRANSACTION' AND parent_id = ? ORDER BY created_at DESC", [$id]) : [];
    return $d;
}
