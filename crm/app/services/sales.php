<?php
// Workflow ฝั่งขาย (สเปกข้อ 4): ลีดผู้ซื้อ → ความต้องการ → จับคู่เครื่อง → ใบเสนอราคา → มัดจำ → สัญญา → WON

const SO_STATUSES = ['NEW_BUYER_LEAD', 'REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING', 'WON', 'LOST'];
const SO_CLOSED = ['WON', 'LOST'];
const SO_STEPS = ['NEW_BUYER_LEAD', 'REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING', 'WON'];
const MATCH_STATUSES = ['PROPOSED', 'SHORTLISTED', 'SELECTED', 'REJECTED'];

function so_get(string $id): array
{
    require_cap('sales.view');
    return db_get('sales_opportunities', $id, 'ดีลขาย');
}

function so_lock_status(string $id, array $allowed): array
{
    $so = db_lock('sales_opportunities', $id, 'ดีลขาย');
    if (!in_array($so['status'], $allowed, true)) {
        throw new AppError('ทำรายการนี้ไม่ได้ เพราะดีลขายอยู่ในสถานะ "' . label('status', $so['status']) . '"');
    }
    return $so;
}

function so_set_status(array $so, string $to, array $extra = [], ?string $note = null): void
{
    update_audited('sales_opportunities', 'SALES_OPPORTUNITY', $so['id'], ['status' => $to] + $extra, 'STATUS_CHANGE', $note);
    log_activity('SALES_OPPORTUNITY', $so['id'], 'SYSTEM', 'สถานะ: ' . label('status', $so['status']) . ' → ' . label('status', $to) . ($note ? "\n" . $note : ''), ['organization_id' => $so['buyer_org_id']]);
}

function so_log(array $so, string $text): void
{
    log_activity('SALES_OPPORTUNITY', $so['id'], 'SYSTEM', $text, ['organization_id' => $so['buyer_org_id']]);
}

// ---------------------------------------------------------------- ความต้องการ (Requirement)

function requirement_save(string $soId, array $d): void
{
    require_cap('sales.edit');
    tx(function () use ($soId, $d) {
        $so = so_lock_status($soId, ['NEW_BUYER_LEAD', 'REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING']);
        $data = [
            'req_brand' => v_maxlen(s($d['req_brand'] ?? null), 100, 'ยี่ห้อ'),
            'req_model' => v_maxlen(s($d['req_model'] ?? null), 150, 'รุ่น'),
            'req_technology' => v_maxlen(s($d['req_technology'] ?? null), 150, 'เทคโนโลยี/ประเภท'),
            'budget_min' => v_money($d['budget_min'] ?? null, 'งบขั้นต่ำ'),
            'budget_max' => v_money($d['budget_max'] ?? null, 'งบสูงสุด', true),
            'preferred_condition' => v_in($d['preferred_condition'] ?? null, CONDITIONS, 'สภาพที่ต้องการ', false),
            'accessories_required' => s($d['accessories_required'] ?? null),
            'warranty_required' => v_maxlen(s($d['warranty_required'] ?? null), 150, 'การรับประกันที่ต้องการ'),
            'installation_required' => !empty($d['installation_required']) && $d['installation_required'] !== '0' ? 1 : 0,
            'expected_purchase_date' => v_date($d['expected_purchase_date'] ?? null, 'วันที่คาดว่าจะซื้อ', true),
            'requirement_note' => s($d['requirement_note'] ?? null),
        ];
        if (!$data['req_brand'] && !$data['req_technology'] && !$data['req_model']) throw new AppError('กรุณาระบุยี่ห้อ/รุ่น หรือเทคโนโลยีที่ลูกค้าต้องการ');
        if ($data['budget_min'] !== null && (float) $data['budget_min'] > (float) $data['budget_max']) throw new AppError('งบขั้นต่ำต้องไม่มากกว่างบสูงสุด');
        if ($so['status'] === 'NEW_BUYER_LEAD') {
            $data['requirement_defined_at'] = now();
            $data['requirement_by'] = current_user_id();
            so_set_status($so, 'REQUIREMENT_DEFINED', $data, 'บันทึกความต้องการของผู้ซื้อ');
            task_create([
                'title' => 'หาเครื่องให้ผู้ซื้อ (Device Match) — ' . $so['ref_no'],
                'type' => 'MATCH', 'parent_type' => 'SALES_OPPORTUNITY', 'parent_id' => $soId,
                'assignee_id' => $so['owner_id'], 'due_date' => add_days(today(), 2),
            ]);
        } else {
            update_audited('sales_opportunities', 'SALES_OPPORTUNITY', $soId, $data);
        }
        tasks_close_for('SALES_OPPORTUNITY', $soId, ['REQUIREMENT']);
    });
}

// ---------------------------------------------------------------- จับคู่เครื่อง (BR-09)

/** ราคาขายเป้าหมายจาก valuation ล่าสุดของเครื่อง (ข้อมูลอ่อนไหว) */
function device_latest_valuation(string $deviceId): ?array
{
    return one('SELECT v.* FROM valuations v JOIN device_opportunities o ON o.id = v.device_opportunity_id WHERE o.device_id = ? ORDER BY v.created_at DESC LIMIT 1', [$deviceId]);
}

/**
 * หาเครื่องที่ตรงความต้องการ: A) ของในสต็อก B) เครื่องที่ผู้ขายกำลังเสนอ (ยังไม่ใช่ของ AMN Sure)
 */
function match_candidates(string $soId, ?string $keyword = null): array
{
    $so = so_get($soId);
    $terms = [];
    $p = [];
    if ($keyword) {
        $terms[] = '(dv.brand LIKE ? OR dv.model LIKE ? OR dv.serial_number LIKE ? OR dv.ref_no LIKE ? OR dv.category LIKE ?)';
        $l = like($keyword);
        array_push($p, $l, $l, $l, $l, $l);
    } else {
        $or = [];
        foreach ([['dv.brand', $so['req_brand']], ['dv.model', $so['req_model']], ['dv.category', $so['req_technology']], ['dv.model', $so['req_technology']]] as $pair) {
            if ($pair[1]) { $or[] = $pair[0] . ' LIKE ?'; $p[] = like($pair[1]); }
        }
        if ($or) $terms[] = '(' . implode(' OR ', $or) . ')';
    }
    $where = $terms ? ' AND ' . implode(' AND ', $terms) : '';
    $exclude = ' AND dv.id NOT IN (SELECT device_id FROM device_matches WHERE sales_opportunity_id = ?)';

    $inv = all("SELECT i.id AS ref_id, i.ref_no AS inv_ref, i.list_price, i.storage_location, dv.*
                FROM inventory i JOIN devices dv ON dv.id = i.device_id
                WHERE i.status = 'IN_STOCK'$where$exclude ORDER BY dv.brand, dv.model LIMIT 100", array_merge($p, [$soId]));
    $seller = all("SELECT o.id AS ref_id, o.ref_no AS opp_ref, o.status AS opp_status, o.asking_price, org.name AS seller_name, dv.*
                   FROM device_opportunities o JOIN devices dv ON dv.id = o.device_id JOIN organizations org ON org.id = o.seller_org_id
                   WHERE o.status NOT IN ('PURCHASED','REJECTED','LOST')$where$exclude ORDER BY dv.brand, dv.model LIMIT 100", array_merge($p, [$soId]));
    $fin = can('finance.view');
    foreach ([&$inv, &$seller] as &$list) {
        foreach ($list as &$r) {
            $v = $fin ? device_latest_valuation($r['id']) : null;
            $r['target_selling_price'] = $v['target_selling_price'] ?? null;
            $r['min_selling_price'] = $v['min_selling_price'] ?? null;
            $r['in_budget'] = null;
            $price = $r['list_price'] ?? ($v['target_selling_price'] ?? null);
            if ($price !== null && $so['budget_max'] !== null) $r['in_budget'] = (float) $price <= (float) $so['budget_max'];
        }
        unset($r);
    }
    unset($list);
    return ['inventory' => $inv, 'seller' => $seller];
}

function match_add(string $soId, string $source, string $refId, ?string $note): string
{
    require_cap('sales.edit');
    return tx(function () use ($soId, $source, $refId, $note) {
        $so = so_lock_status($soId, ['REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING']);
        v_in($source, ['INVENTORY', 'SELLER_OPPORTUNITY'], 'แหล่งเครื่อง');
        if ($source === 'INVENTORY') {
            $inv = db_get('inventory', $refId, 'รายการสต็อก');
            if ($inv['status'] !== 'IN_STOCK') throw new AppError('เครื่องนี้ไม่ได้อยู่ในสต็อกแล้ว (' . label('status', $inv['status']) . ')');
            $deviceId = $inv['device_id'];
            $data = ['inventory_id' => $inv['id'], 'device_opportunity_id' => null];
        } else {
            $opp = db_get('device_opportunities', $refId, 'ดีลซื้อ');
            if (in_array($opp['status'], DO_CLOSED, true)) throw new AppError('ดีลซื้อของเครื่องนี้ปิดไปแล้ว');
            $deviceId = $opp['device_id'];
            $data = ['inventory_id' => null, 'device_opportunity_id' => $opp['id']];
        }
        if (val('SELECT id FROM device_matches WHERE sales_opportunity_id = ? AND device_id = ?', [$soId, $deviceId])) throw new AppError('จับคู่เครื่องนี้ไว้แล้ว');
        $id = insert_audited('device_matches', 'DEVICE_MATCH', $data + [
            'sales_opportunity_id' => $soId, 'device_id' => $deviceId, 'source' => $source, 'status' => 'PROPOSED', 'note' => s($note),
        ]);
        $dv = db_get('devices', $deviceId);
        if ($so['status'] === 'REQUIREMENT_DEFINED') {
            so_set_status($so, 'MATCHING', [], 'จับคู่เครื่อง ' . $dv['ref_no'] . ' ' . $dv['brand'] . ' ' . $dv['model']);
        } else {
            so_log($so, 'จับคู่เครื่อง ' . $dv['ref_no'] . ' ' . $dv['brand'] . ' ' . $dv['model'] . ' (' . label('match_source', $source) . ')');
        }
        tasks_close_for('SALES_OPPORTUNITY', $soId, ['MATCH']);
        return $id;
    });
}

function match_set_status(string $matchId, string $status): void
{
    require_cap('sales.edit');
    v_in($status, MATCH_STATUSES, 'สถานะการจับคู่');
    tx(function () use ($matchId, $status) {
        $m = db_lock('device_matches', $matchId, 'การจับคู่');
        so_lock_status($m['sales_opportunity_id'], ['MATCHING', 'QUOTING', 'REQUIREMENT_DEFINED']);
        update_audited('device_matches', 'DEVICE_MATCH', $matchId, ['status' => $status], 'STATUS_CHANGE');
    });
}

// ---------------------------------------------------------------- ใบเสนอราคา (BR-10)

function quotation_current_version(string $quotationId): array
{
    return one('SELECT qv.* FROM quotation_versions qv JOIN quotations q ON q.id = qv.quotation_id AND qv.version_no = q.current_version_no WHERE q.id = ?', [$quotationId]);
}

function quotation_create(string $soId): string
{
    require_cap('quotation.edit');
    return tx(function () use ($soId) {
        $so = so_lock_status($soId, ['MATCHING', 'QUOTING']);
        if (val('SELECT id FROM quotations WHERE sales_opportunity_id = ?', [$soId])) throw new AppError('ดีลนี้มีใบเสนอราคาแล้ว — ถ้าต้องการเปลี่ยนราคาให้ออกฉบับแก้ไข (revision)');
        $selected = all("SELECT m.*, dv.brand, dv.model, dv.serial_number, dv.ref_no AS device_ref, dv.accessories, i.list_price
            FROM device_matches m JOIN devices dv ON dv.id = m.device_id LEFT JOIN inventory i ON i.id = m.inventory_id
            WHERE m.sales_opportunity_id = ? AND m.status = 'SELECTED'", [$soId]);
        if (!$selected) throw new AppError('ต้องเลือกเครื่องอย่างน้อย 1 เครื่อง (สถานะ "เลือกแล้ว") ก่อนออกใบเสนอราคา');

        $ref = next_ref('QTN');
        $qId = insert_audited('quotations', 'QUOTATION', ['ref_no' => $ref, 'sales_opportunity_id' => $soId, 'current_version_no' => 1]);
        $vId = db_insert('quotation_versions', [
            'quotation_id' => $qId, 'version_no' => 1, 'status' => 'DRAFT',
            'vat_rate' => (string) (float) setting('vat_rate', 7),
            'valid_until' => add_days(today(), (int) setting('quotation_valid_days', 30)),
            'warranty_terms' => $so['warranty_required'],
            'installation_terms' => (int) $so['installation_required'] === 1 ? 'รวมติดตั้งและสอนการใช้งาน' : null,
        ]);
        $sort = 0;
        foreach ($selected as $m) {
            $insp = one("SELECT overall_condition FROM inspections WHERE device_id = ? AND status = 'COMPLETED' ORDER BY completed_at DESC LIMIT 1", [$m['device_id']]);
            db_insert('quotation_version_lines', [
                'quotation_version_id' => $vId, 'device_id' => $m['device_id'],
                'description' => mb_substr($m['brand'] . ' ' . $m['model'] . ($m['serial_number'] ? ' (S/N ' . $m['serial_number'] . ')' : '') . ' [' . $m['device_ref'] . ']', 0, 255),
                'device_condition' => $insp['overall_condition'] ?? null,
                'accessories' => $m['accessories'], 'qty' => 1, 'unit_price' => $m['list_price'] ?? '0.00', 'sort' => $sort += 10,
            ]);
        }
        quotation_recalc($vId);
        if ($so['status'] === 'MATCHING') so_set_status($so, 'QUOTING', [], 'ออกใบเสนอราคา ' . $ref);
        task_create([
            'title' => 'กรอกราคาและขออนุมัติใบเสนอราคา ' . $ref,
            'type' => 'QUOTATION_DRAFT', 'parent_type' => 'QUOTATION', 'parent_id' => $qId,
            'assignee_id' => $so['owner_id'], 'due_date' => add_days(today(), 1),
        ]);
        return $qId;
    });
}

function quotation_recalc(string $versionId): void
{
    $v = db_get('quotation_versions', $versionId);
    $sub = (float) val('SELECT COALESCE(SUM(qty * unit_price), 0) FROM quotation_version_lines WHERE quotation_version_id = ?', [$versionId]);
    $vat = round($sub * (float) $v['vat_rate'] / 100, 2);
    db_update('quotation_versions', $versionId, [
        'subtotal' => number_format($sub, 2, '.', ''), 'vat_amount' => number_format($vat, 2, '.', ''), 'total' => number_format($sub + $vat, 2, '.', ''),
    ]);
}

function qv_lock(string $versionId): array
{
    $v = db_lock('quotation_versions', $versionId, 'ใบเสนอราคา');
    $q = db_get('quotations', $v['quotation_id']);
    if ((int) $v['version_no'] !== (int) $q['current_version_no']) throw new AppError('ฉบับนี้ถูกแทนที่แล้ว — แก้ไขได้เฉพาะฉบับล่าสุด (BR-10)');
    $v['quotation'] = $q;
    $v['so'] = db_get('sales_opportunities', $q['sales_opportunity_id']);
    return $v;
}

/** บันทึกฉบับร่าง: $d['lines'][] = [device_id, description, device_condition, accessories, qty, unit_price] */
function qv_save(string $versionId, array $d): void
{
    require_cap('quotation.edit');
    tx(function () use ($versionId, $d) {
        $v = qv_lock($versionId);
        if ($v['status'] !== 'DRAFT') throw new AppError('แก้ไขได้เฉพาะฉบับร่าง — ถ้าต้องการเปลี่ยนให้ออกฉบับแก้ไข (revision)');
        if (in_array($v['so']['status'], SO_CLOSED, true)) throw new AppError('ดีลขายนี้ปิดแล้ว');
        $matched = array_column(all('SELECT device_id FROM device_matches WHERE sales_opportunity_id = ?', [$v['so']['id']]), 'device_id');
        $lines = [];
        foreach ((array) ($d['lines'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $desc = s($row['description'] ?? null);
            $price = v_money($row['unit_price'] ?? null, 'ราคาต่อหน่วย');
            if (!$desc && $price === null) continue;
            if (!$desc) throw new AppError('กรุณากรอกรายละเอียดของทุกรายการ');
            $dev = v_uuid($row['device_id'] ?? null, 'เครื่อง', false);
            if ($dev && !in_array($dev, $matched, true)) throw new AppError('เครื่องในใบเสนอราคาต้องเป็นเครื่องที่จับคู่ไว้กับดีลนี้');
            $lines[] = [
                'device_id' => $dev,
                'description' => v_maxlen($desc, 255, 'รายละเอียด'),
                'device_condition' => v_in($row['device_condition'] ?? null, CONDITIONS, 'สภาพเครื่อง', false),
                'accessories' => s($row['accessories'] ?? null),
                'qty' => v_int($row['qty'] ?? '1', 'จำนวน', true, 1, 1000),
                'unit_price' => $price ?? '0.00',
            ];
        }
        if (!$lines) throw new AppError('ใบเสนอราคาต้องมีอย่างน้อย 1 รายการ');
        $validUntil = v_date($d['valid_until'] ?? null, 'ยืนราคาถึง', true);
        if ($validUntil < today()) throw new AppError('วันยืนราคาต้องไม่เป็นวันที่ผ่านมาแล้ว');
        $vat = v_decimal($d['vat_rate'] ?? setting('vat_rate', 7), 'อัตรา VAT (%)', true);
        if ((float) $vat > 100) throw new AppError('อัตรา VAT ไม่ถูกต้อง');

        $old = all('SELECT device_id, description, qty, unit_price FROM quotation_version_lines WHERE quotation_version_id = ? ORDER BY sort', [$versionId]);
        q('DELETE FROM quotation_version_lines WHERE quotation_version_id = ?', [$versionId]); // แก้ได้เฉพาะฉบับร่าง
        $sort = 0;
        foreach ($lines as $l) db_insert('quotation_version_lines', $l + ['quotation_version_id' => $versionId, 'sort' => $sort += 10]);
        update_audited('quotation_versions', 'QUOTATION_VERSION', $versionId, [
            'vat_rate' => $vat,
            'warranty_terms' => s($d['warranty_terms'] ?? null),
            'payment_terms' => s($d['payment_terms'] ?? null),
            'delivery_terms' => s($d['delivery_terms'] ?? null),
            'installation_terms' => s($d['installation_terms'] ?? null),
            'valid_until' => $validUntil,
            'notes' => s($d['notes'] ?? null),
        ]);
        audit_log('QUOTATION_VERSION', $versionId, 'UPDATE', [['field' => 'lines', 'old' => $old, 'new' => $lines]]);
        quotation_recalc($versionId);
    });
}

/** รายการที่ราคาต่ำกว่าราคาขายต่ำสุดจาก valuation (ต้องให้ GM อนุมัติ) */
function qv_below_min_lines(string $versionId): array
{
    $out = [];
    foreach (all('SELECT * FROM quotation_version_lines WHERE quotation_version_id = ? AND device_id IS NOT NULL', [$versionId]) as $l) {
        $v = device_latest_valuation($l['device_id']);
        if ($v && (float) $l['unit_price'] < (float) $v['min_selling_price']) $out[] = $l['description'];
    }
    return $out;
}

function qv_request_approval(string $versionId): void
{
    require_cap('quotation.edit');
    tx(function () use ($versionId) {
        $v = qv_lock($versionId);
        if ($v['status'] !== 'DRAFT') throw new AppError('ขออนุมัติได้เฉพาะฉบับร่าง');
        if ((float) $v['total'] <= 0) throw new AppError('ใบเสนอราคายังไม่มีราคา');
        $below = qv_below_min_lines($versionId);
        update_audited('quotation_versions', 'QUOTATION_VERSION', $versionId, ['approval_requested_at' => now(), 'approval_requested_by' => current_user_id()]);
        tasks_close_for('QUOTATION', $v['quotation']['id'], ['QUOTATION_DRAFT']);
        task_create([
            'title' => 'อนุมัติใบเสนอราคา ' . $v['quotation']['ref_no'] . ' ฉบับที่ ' . $v['version_no'] . ($below ? ' (ต่ำกว่าราคาขายขั้นต่ำ)' : ''),
            'type' => 'QUOTATION_APPROVAL', 'parent_type' => 'QUOTATION', 'parent_id' => $v['quotation']['id'],
            'assignee_role' => $below ? 'GM' : 'SALES_DIRECTOR', 'due_date' => add_days(today(), 1), 'priority' => 'HIGH',
        ]);
        so_log($v['so'], 'ขออนุมัติใบเสนอราคา ' . $v['quotation']['ref_no'] . ' ฉบับที่ ' . $v['version_no'] . ' ยอดรวม ' . money($v['total']) . ' บาท');
    });
}

function qv_approve(string $versionId): void
{
    require_cap('quotation.approve');
    tx(function () use ($versionId) {
        $v = qv_lock($versionId);
        if ($v['status'] !== 'DRAFT') throw new AppError('อนุมัติได้เฉพาะฉบับร่าง');
        if ((float) $v['total'] <= 0) throw new AppError('ใบเสนอราคายังไม่มีราคา');
        $below = qv_below_min_lines($versionId);
        if ($below && !can('quotation.approve_below_min')) {
            throw new ForbiddenError('ราคาบางรายการต่ำกว่าราคาขายขั้นต่ำ (' . implode(', ', $below) . ') ต้องให้ GM อนุมัติ');
        }
        update_audited('quotation_versions', 'QUOTATION_VERSION', $versionId, ['status' => 'APPROVED', 'approved_by' => current_user_id(), 'approved_at' => now()], 'APPROVE',
            $below ? 'อนุมัติราคาต่ำกว่าขั้นต่ำ: ' . implode(', ', $below) : null);
        tasks_close_for('QUOTATION', $v['quotation']['id'], ['QUOTATION_APPROVAL', 'QUOTATION_DRAFT']);
        task_create([
            'title' => 'ส่งใบเสนอราคา ' . $v['quotation']['ref_no'] . ' ให้ลูกค้า',
            'type' => 'QUOTATION_SEND', 'parent_type' => 'QUOTATION', 'parent_id' => $v['quotation']['id'],
            'assignee_id' => $v['so']['owner_id'], 'due_date' => today(),
        ]);
        so_log($v['so'], 'อนุมัติใบเสนอราคา ' . $v['quotation']['ref_no'] . ' ฉบับที่ ' . $v['version_no']);
    });
}

function qv_send(string $versionId): void
{
    require_cap('quotation.edit');
    tx(function () use ($versionId) {
        $v = qv_lock($versionId);
        if ($v['status'] !== 'APPROVED') throw new AppError('ต้องได้รับอนุมัติก่อนส่งให้ลูกค้า');
        if ($v['valid_until'] < today()) throw new AppError('ใบเสนอราคาเลยวันยืนราคาแล้ว — ออกฉบับแก้ไขเพื่อกำหนดวันใหม่');
        update_audited('quotation_versions', 'QUOTATION_VERSION', $versionId, ['status' => 'SENT', 'sent_at' => now()], 'STATUS_CHANGE');
        tasks_close_for('QUOTATION', $v['quotation']['id'], ['QUOTATION_SEND']);
        so_log($v['so'], 'ส่งใบเสนอราคา ' . $v['quotation']['ref_no'] . ' ฉบับที่ ' . $v['version_no'] . ' ให้ลูกค้า (ยืนราคาถึง ' . d($v['valid_until']) . ')');
    });
}

/** ลูกค้าตอบรับ/ปฏิเสธ — ถ้าตอบรับ ระบบสร้างรายการรอรับมัดจำให้และตรวจเงื่อนไข WON */
function qv_respond(string $versionId, string $response, ?string $note): array
{
    require_cap('quotation.edit');
    v_in($response, ['ACCEPTED', 'REJECTED'], 'คำตอบของลูกค้า');
    return tx(function () use ($versionId, $response, $note) {
        $v = qv_lock($versionId);
        if ($v['status'] !== 'SENT') throw new AppError('บันทึกคำตอบได้เฉพาะใบเสนอราคาที่ส่งลูกค้าแล้ว');
        if ($response === 'ACCEPTED' && $v['valid_until'] < today()) {
            update_audited('quotation_versions', 'QUOTATION_VERSION', $versionId, ['status' => 'EXPIRED'], 'STATUS_CHANGE', 'เลยวันยืนราคา');
            throw new AppError('ใบเสนอราคาเลยวันยืนราคาแล้ว (หมดอายุ) — ออกฉบับแก้ไขใหม่');
        }
        update_audited('quotation_versions', 'QUOTATION_VERSION', $versionId, ['status' => $response, 'responded_at' => now(), 'response_note' => s($note)], 'STATUS_CHANGE');
        so_log($v['so'], 'ลูกค้า' . ($response === 'ACCEPTED' ? 'ตอบรับ' : 'ปฏิเสธ') . 'ใบเสนอราคา ' . $v['quotation']['ref_no'] . ' ฉบับที่ ' . $v['version_no'] . (s($note) ? "\n" . $note : ''));
        if ($response === 'REJECTED') return ['won' => false];

        if (!val("SELECT id FROM deposits WHERE sales_opportunity_id = ? AND status IN ('WAITING_DEPOSIT','DEPOSIT_RECEIVED')", [$v['so']['id']])) {
            $pct = (float) setting('deposit_default_pct', 30);
            if ($pct > 0) {
                $depId = insert_audited('deposits', 'DEPOSIT', [
                    'sales_opportunity_id' => $v['so']['id'], 'quotation_version_id' => $versionId,
                    'required_amount' => number_format(round((float) $v['total'] * $pct / 100, 2), 2, '.', ''),
                    'due_date' => add_days(today(), 7), 'status' => 'WAITING_DEPOSIT',
                ], 'สร้างอัตโนมัติ ' . $pct . '% ของยอดใบเสนอราคา');
                task_create([
                    'title' => 'ติดตามเงินมัดจำและทำสัญญา — ' . $v['so']['ref_no'],
                    'type' => 'DEPOSIT', 'parent_type' => 'SALES_OPPORTUNITY', 'parent_id' => $v['so']['id'],
                    'assignee_role' => 'SALES_COORDINATOR', 'due_date' => add_days(today(), 7), 'priority' => 'HIGH',
                ]);
            }
        }
        return won_evaluate($v['so']['id']);
    });
}

/** ออกฉบับแก้ไข: ฉบับเดิมเป็น SUPERSEDED (อ่านอย่างเดียว) ฉบับใหม่เป็นร่าง */
function quotation_revise(string $quotationId): string
{
    require_cap('quotation.edit');
    return tx(function () use ($quotationId) {
        $q = db_lock('quotations', $quotationId, 'ใบเสนอราคา');
        $so = so_lock_status($q['sales_opportunity_id'], ['QUOTING']);
        $cur = quotation_current_version($quotationId);
        if ($cur['status'] === 'DRAFT') throw new AppError('ฉบับปัจจุบันยังเป็นร่าง แก้ไขได้เลยไม่ต้องออกฉบับใหม่');
        if ($cur['status'] === 'ACCEPTED') throw new AppError('ลูกค้าตอบรับฉบับนี้แล้ว — ถ้าต้องการเปลี่ยนต้องให้ GM ยกเลิกการตอบรับก่อน');
        update_audited('quotation_versions', 'QUOTATION_VERSION', $cur['id'], ['status' => 'SUPERSEDED'], 'STATUS_CHANGE', 'ออกฉบับแก้ไข');
        $newNo = (int) $cur['version_no'] + 1;
        $copy = array_intersect_key($cur, array_flip(['vat_rate', 'warranty_terms', 'payment_terms', 'delivery_terms', 'installation_terms', 'notes']));
        $vId = db_insert('quotation_versions', $copy + [
            'quotation_id' => $quotationId, 'version_no' => $newNo, 'status' => 'DRAFT',
            'valid_until' => max($cur['valid_until'] ?? today(), add_days(today(), (int) setting('quotation_valid_days', 30))),
        ]);
        foreach (all('SELECT * FROM quotation_version_lines WHERE quotation_version_id = ? ORDER BY sort', [$cur['id']]) as $l) {
            db_insert('quotation_version_lines', array_intersect_key($l, array_flip(['device_id', 'description', 'device_condition', 'accessories', 'qty', 'unit_price', 'sort'])) + ['quotation_version_id' => $vId]);
        }
        quotation_recalc($vId);
        update_audited('quotations', 'QUOTATION', $quotationId, ['current_version_no' => $newNo]);
        so_log($so, 'ออกใบเสนอราคา ' . $q['ref_no'] . ' ฉบับแก้ไขที่ ' . $newNo);
        return $vId;
    });
}

/** ใบเสนอราคาที่ส่งแล้วแต่เลยวันยืนราคา → EXPIRED */
function quotation_expire_due(): void
{
    foreach (all("SELECT id FROM quotation_versions WHERE status = 'SENT' AND valid_until < ?", [today()]) as $r) {
        tx(function () use ($r) {
            update_audited('quotation_versions', 'QUOTATION_VERSION', $r['id'], ['status' => 'EXPIRED'], 'STATUS_CHANGE', 'เลยวันยืนราคา (ระบบ)');
        });
    }
}

// ---------------------------------------------------------------- มัดจำ

function deposit_save(string $soId, ?string $depositId, array $d): string
{
    require_cap('deposit.edit');
    return tx(function () use ($soId, $depositId, $d) {
        $so = so_lock_status($soId, ['QUOTING']);
        $amount = v_money($d['required_amount'] ?? null, 'ยอดมัดจำที่ต้องชำระ', true, false);
        $due = v_date($d['due_date'] ?? null, 'กำหนดชำระ');
        if ($depositId) {
            $dep = db_lock('deposits', $depositId, 'รายการมัดจำ');
            if ($dep['sales_opportunity_id'] !== $soId || $dep['status'] !== 'WAITING_DEPOSIT') throw new AppError('แก้ไขได้เฉพาะรายการที่ยังรอรับมัดจำ');
            update_audited('deposits', 'DEPOSIT', $depositId, ['required_amount' => $amount, 'due_date' => $due, 'note' => s($d['note'] ?? null)]);
            return $depositId;
        }
        if (val("SELECT id FROM deposits WHERE sales_opportunity_id = ? AND status IN ('WAITING_DEPOSIT','DEPOSIT_RECEIVED')", [$soId])) {
            throw new AppError('ดีลนี้มีรายการมัดจำอยู่แล้ว');
        }
        $q = one('SELECT id FROM quotations WHERE sales_opportunity_id = ?', [$soId]);
        $id = insert_audited('deposits', 'DEPOSIT', [
            'sales_opportunity_id' => $soId, 'quotation_version_id' => $q ? quotation_current_version($q['id'])['id'] : null,
            'required_amount' => $amount, 'due_date' => $due, 'status' => 'WAITING_DEPOSIT', 'note' => s($d['note'] ?? null),
        ]);
        so_log($so, 'กำหนดมัดจำ ' . money($amount) . ' บาท' . ($due ? ' ภายใน ' . d($due) : ''));
        return $id;
    });
}

/** บันทึกรับมัดจำ ต้องแนบหลักฐานการโอน */
function deposit_receive(string $depositId, array $d, ?array $file): array
{
    require_cap('deposit.edit');
    return tx(function () use ($depositId, $d, $file) {
        $dep = db_lock('deposits', $depositId, 'รายการมัดจำ');
        if ($dep['status'] !== 'WAITING_DEPOSIT') throw new AppError('รายการนี้ไม่ได้อยู่ในสถานะรอรับมัดจำ');
        $so = so_lock_status($dep['sales_opportunity_id'], ['QUOTING']);
        $amount = v_money($d['received_amount'] ?? null, 'ยอดที่ได้รับ', true, false);
        if ((float) $amount + 0.001 < (float) $dep['required_amount']) throw new AppError('ยอดที่ได้รับ (' . money($amount) . ') น้อยกว่ามัดจำที่กำหนด (' . money($dep['required_amount']) . ')');
        $date = v_date($d['received_date'] ?? null, 'วันที่รับเงิน', true);
        if ($date > today()) throw new AppError('วันที่รับเงินต้องไม่เป็นวันในอนาคต');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            document_add('DEPOSIT', $depositId, $file, ['category' => 'PAYMENT_EVIDENCE', 'title' => 'หลักฐานการโอนมัดจำ ' . $so['ref_no']], true);
        }
        if (!document_count('DEPOSIT', $depositId, 'PAYMENT_EVIDENCE')) throw new AppError('กรุณาแนบหลักฐานการชำระเงิน (สลิป/ใบนำฝาก)');
        update_audited('deposits', 'DEPOSIT', $depositId, [
            'received_amount' => $amount, 'received_date' => $date, 'status' => 'DEPOSIT_RECEIVED', 'note' => s($d['note'] ?? null) ?? $dep['note'],
        ], 'STATUS_CHANGE');
        so_log($so, 'ได้รับมัดจำ ' . money($amount) . ' บาท เมื่อ ' . d($date));
        if ((int) setting('won_require_deposit_verified', 0) === 1) {
            task_create([
                'title' => 'ยืนยันการรับเงินมัดจำ — ' . $so['ref_no'],
                'type' => 'DEPOSIT_VERIFY', 'parent_type' => 'SALES_OPPORTUNITY', 'parent_id' => $so['id'],
                'assignee_role' => 'GM', 'due_date' => today(), 'priority' => 'HIGH',
            ]);
        }
        return won_evaluate($so['id']);
    });
}

function deposit_verify(string $depositId): array
{
    require_cap('deposit.verify');
    return tx(function () use ($depositId) {
        $dep = db_lock('deposits', $depositId, 'รายการมัดจำ');
        if ($dep['status'] !== 'DEPOSIT_RECEIVED' || $dep['verified_at']) throw new AppError('ยืนยันได้เฉพาะมัดจำที่ได้รับแล้วและยังไม่ได้ยืนยัน');
        update_audited('deposits', 'DEPOSIT', $depositId, ['verified_by' => current_user_id(), 'verified_at' => now()], 'APPROVE', 'ยืนยันยอดมัดจำ');
        tasks_close_for('SALES_OPPORTUNITY', $dep['sales_opportunity_id'], ['DEPOSIT_VERIFY']);
        $so = db_get('sales_opportunities', $dep['sales_opportunity_id']);
        so_log($so, 'ยืนยันยอดมัดจำแล้ว');
        return $so['status'] === 'QUOTING' ? won_evaluate($so['id']) : ['won' => false];
    });
}

function deposit_close(string $depositId, string $status, string $reason): void
{
    require_cap('record.void');
    v_in($status, ['REFUNDED', 'FORFEITED'], 'สถานะมัดจำ');
    $reason = s($reason);
    if (!$reason) throw new AppError('กรุณาระบุเหตุผล');
    tx(function () use ($depositId, $status, $reason) {
        $dep = db_lock('deposits', $depositId, 'รายการมัดจำ');
        if (!in_array($dep['status'], ['WAITING_DEPOSIT', 'DEPOSIT_RECEIVED'], true)) throw new AppError('รายการนี้ปิดไปแล้ว');
        $so = db_get('sales_opportunities', $dep['sales_opportunity_id']);
        if ($so['status'] === 'WON') throw new AppError('ดีลนี้ปิดการขายแล้ว — ต้องยกเลิกธุรกรรมขายก่อน');
        update_audited('deposits', 'DEPOSIT', $depositId, ['status' => $status, 'status_reason' => mb_substr($reason, 0, 255)], 'STATUS_CHANGE', $reason);
        so_log($so, label('status', $status) . ': ' . $reason);
    });
}

// ---------------------------------------------------------------- สัญญา

function contract_save(string $soId, ?string $contractId, array $d): string
{
    require_cap('contract.edit');
    return tx(function () use ($soId, $contractId, $d) {
        $so = so_lock_status($soId, ['QUOTING']);
        $q = one('SELECT * FROM quotations WHERE sales_opportunity_id = ?', [$soId]);
        if (!$q) throw new AppError('ต้องมีใบเสนอราคาก่อนทำสัญญา');
        $qv = one("SELECT qv.* FROM quotation_versions qv WHERE qv.quotation_id = ? AND qv.status = 'ACCEPTED' LIMIT 1", [$q['id']]) ?? quotation_current_version($q['id']);
        $data = [
            'contract_no' => v_maxlen(s($d['contract_no'] ?? null), 100, 'เลขที่สัญญา'),
            'price' => v_money($d['price'] ?? null, 'มูลค่าสัญญา', false, false) ?? $qv['total'],
            'payment_terms' => s($d['payment_terms'] ?? null) ?? $qv['payment_terms'],
            'warranty_terms' => s($d['warranty_terms'] ?? null) ?? $qv['warranty_terms'],
            'delivery_terms' => s($d['delivery_terms'] ?? null) ?? $qv['delivery_terms'],
            'installation_terms' => s($d['installation_terms'] ?? null) ?? $qv['installation_terms'],
        ];
        if ($contractId) {
            $c = db_lock('contracts', $contractId, 'สัญญา');
            if ($c['sales_opportunity_id'] !== $soId || $c['status'] !== 'DRAFT') throw new AppError('แก้ไขได้เฉพาะสัญญาฉบับร่าง');
            if (!$data['contract_no']) $data['contract_no'] = $c['contract_no'];
            update_audited('contracts', 'CONTRACT', $contractId, $data + ['quotation_version_id' => $qv['id']]);
            return $contractId;
        }
        if (val("SELECT id FROM contracts WHERE sales_opportunity_id = ? AND status <> 'VOID'", [$soId])) throw new AppError('ดีลนี้มีสัญญาอยู่แล้ว');
        $ref = next_ref('CTR');
        $data['contract_no'] = $data['contract_no'] ?? $ref;
        $id = insert_audited('contracts', 'CONTRACT', $data + ['ref_no' => $ref, 'sales_opportunity_id' => $soId, 'quotation_version_id' => $qv['id'], 'status' => 'DRAFT']);
        so_log($so, 'ร่างสัญญา ' . $data['contract_no'] . ' มูลค่า ' . money($data['price']) . ' บาท');
        return $id;
    });
}

function contract_sign(string $contractId, array $d, ?array $file): array
{
    require_cap('contract.edit');
    return tx(function () use ($contractId, $d, $file) {
        $c = db_lock('contracts', $contractId, 'สัญญา');
        if ($c['status'] !== 'DRAFT') throw new AppError('สัญญานี้ไม่ได้อยู่ในสถานะร่าง');
        $so = so_lock_status($c['sales_opportunity_id'], ['QUOTING']);
        $date = v_date($d['signed_date'] ?? null, 'วันที่ลงนาม', true);
        if ($date > today()) throw new AppError('วันที่ลงนามต้องไม่เป็นวันในอนาคต');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            document_add('CONTRACT', $contractId, $file, ['category' => 'CONTRACT', 'title' => 'สัญญาที่ลงนามแล้ว ' . $c['contract_no']], true);
        }
        if (!document_count('CONTRACT', $contractId, 'CONTRACT')) throw new AppError('กรุณาแนบไฟล์สัญญาที่ลงนามแล้ว');
        update_audited('contracts', 'CONTRACT', $contractId, ['status' => 'CONTRACT_SIGNED', 'signed_date' => $date], 'STATUS_CHANGE');
        so_log($so, 'ลงนามสัญญา ' . $c['contract_no'] . ' เมื่อ ' . d($date));
        return won_evaluate($so['id']);
    });
}

function contract_void(string $contractId, string $reason): void
{
    require_cap('record.void');
    $reason = s($reason);
    if (!$reason) throw new AppError('กรุณาระบุเหตุผล');
    tx(function () use ($contractId, $reason) {
        $c = db_lock('contracts', $contractId, 'สัญญา');
        if ($c['status'] === 'VOID') throw new AppError('สัญญานี้ถูกยกเลิกแล้ว');
        $so = db_get('sales_opportunities', $c['sales_opportunity_id']);
        if ($so['status'] === 'WON') throw new AppError('ดีลนี้ปิดการขายแล้ว — ต้องยกเลิกธุรกรรมขายก่อน');
        update_audited('contracts', 'CONTRACT', $contractId, ['status' => 'VOID', 'voided_at' => now(), 'voided_by' => current_user_id(), 'void_reason' => mb_substr($reason, 0, 255)], 'VOID', $reason);
        so_log($so, 'ยกเลิกสัญญา ' . $c['contract_no'] . ': ' . $reason);
    });
}

// ---------------------------------------------------------------- WON (BR-11)

/** เงื่อนไข WON ตามการตั้งค่า: คืนรายการที่ยังขาด */
function won_missing(string $soId): array
{
    $m = [];
    $q = one('SELECT id FROM quotations WHERE sales_opportunity_id = ?', [$soId]);
    if ((int) setting('won_require_quotation_accepted', 1) === 1) {
        if (!$q || !val("SELECT id FROM quotation_versions WHERE quotation_id = ? AND status = 'ACCEPTED'", [$q['id']])) $m[] = 'ลูกค้าตอบรับใบเสนอราคา';
    }
    if ((int) setting('won_require_deposit_received', 1) === 1) {
        if (!val("SELECT id FROM deposits WHERE sales_opportunity_id = ? AND status = 'DEPOSIT_RECEIVED'", [$soId])) $m[] = 'ได้รับเงินมัดจำ';
        elseif ((int) setting('won_require_deposit_verified', 0) === 1 && !val("SELECT id FROM deposits WHERE sales_opportunity_id = ? AND status = 'DEPOSIT_RECEIVED' AND verified_at IS NOT NULL", [$soId])) $m[] = 'GM ยืนยันยอดมัดจำ';
    }
    if ((int) setting('won_require_contract_signed', 1) === 1) {
        if (!val("SELECT id FROM contracts WHERE sales_opportunity_id = ? AND status = 'CONTRACT_SIGNED'", [$soId])) $m[] = 'ลงนามสัญญา';
    }
    if (!$q) $m[] = 'ใบเสนอราคา';
    return $m;
}

/**
 * ตรวจเงื่อนไข WON หลังทุกเหตุการณ์ที่เกี่ยวข้อง (ตอบรับใบเสนอราคา / รับมัดจำ / ลงนามสัญญา)
 * ถ้าครบ: สร้าง Sales Transaction + checklist + จองเครื่อง ใน transaction เดียวกัน
 * คืน ['won' => bool, 'missing' => [], 'blocked' => ?string, 'transaction_id' => ?string]
 */
function won_evaluate(string $soId): array
{
    return tx(function () use ($soId) {
        $so = db_lock('sales_opportunities', $soId, 'ดีลขาย');
        if ($so['status'] !== 'QUOTING') return ['won' => false, 'missing' => [], 'blocked' => null];
        $missing = won_missing($soId);
        if ($missing) return ['won' => false, 'missing' => $missing, 'blocked' => null];

        $q = one('SELECT * FROM quotations WHERE sales_opportunity_id = ?', [$soId]);
        $qv = one("SELECT * FROM quotation_versions WHERE quotation_id = ? AND status = 'ACCEPTED' LIMIT 1", [$q['id']]) ?? quotation_current_version($q['id']);
        $lines = all('SELECT l.*, dv.ref_no AS device_ref, dv.commercial_status, dv.owned_by_amn FROM quotation_version_lines l
                      LEFT JOIN devices dv ON dv.id = l.device_id WHERE l.quotation_version_id = ?', [$qv['id']]);
        // เครื่องต้องยังว่าง (ไม่ถูกจอง/ขายให้ดีลอื่น)
        foreach ($lines as $l) {
            if (!$l['device_id']) continue;
            $taken = one("SELECT t.ref_no FROM sales_transactions t JOIN quotation_version_lines ql ON ql.quotation_version_id = t.quotation_version_id
                          WHERE ql.device_id = ? AND t.status <> 'CANCELLED' LIMIT 1", [$l['device_id']]);
            if ($taken) return ['won' => false, 'missing' => [], 'blocked' => 'เครื่อง ' . $l['device_ref'] . ' ถูกขาย/จองในธุรกรรม ' . $taken['ref_no'] . ' แล้ว'];
        }
        $contract = one("SELECT id FROM contracts WHERE sales_opportunity_id = ? AND status = 'CONTRACT_SIGNED' LIMIT 1", [$soId]);
        $trxId = insert_audited('sales_transactions', 'SALES_TRANSACTION', [
            'ref_no' => next_ref('TRX'),
            'sales_opportunity_id' => $soId,
            'quotation_version_id' => $qv['id'],
            'contract_id' => $contract['id'] ?? null,
            'buyer_org_id' => $so['buyer_org_id'],
            'total_price' => $qv['total'],
            'status' => 'IN_PREPARATION',
            'installation_required' => $so['installation_required'],
            'won_at' => now6(),
        ]);
        $trxRef = val('SELECT ref_no FROM sales_transactions WHERE id = ?', [$trxId]);
        checklist_create_for($trxId, $trxRef);
        foreach ($lines as $l) {
            if (!$l['device_id']) continue;
            $inv = one("SELECT * FROM inventory WHERE device_id = ? AND status = 'IN_STOCK' LIMIT 1", [$l['device_id']]);
            if ($inv) update_audited('inventory', 'INVENTORY', $inv['id'], ['status' => 'RESERVED', 'reserved_for_transaction_id' => $trxId], 'STATUS_CHANGE', 'จองให้ ' . $trxRef);
            if (in_array($l['commercial_status'], ['IN_INVENTORY', 'PURCHASED'], true)) {
                update_audited('devices', 'DEVICE', $l['device_id'], ['commercial_status' => 'RESERVED'], 'STATUS_CHANGE', 'จองให้ ' . $trxRef);
            }
        }
        so_set_status($so, 'WON', ['closed_at' => now()], 'ปิดการขายได้ → ธุรกรรม ' . $trxRef . ' มูลค่า ' . money($qv['total']) . ' บาท');
        tasks_close_for('SALES_OPPORTUNITY', $soId);
        tasks_close_for('QUOTATION', $q['id']);
        lead_refresh_status($so['lead_id']);
        return ['won' => true, 'missing' => [], 'blocked' => null, 'transaction_id' => $trxId];
    });
}

function sales_mark_lost(string $soId, string $reason): void
{
    require_cap('lead.edit');
    tx(function () use ($soId, $reason) {
        $so = so_lock_status($soId, ['NEW_BUYER_LEAD', 'REQUIREMENT_DEFINED', 'MATCHING', 'QUOTING']);
        $reason = s($reason);
        if (!$reason) throw new AppError('กรุณาระบุเหตุผลที่ดีลไม่สำเร็จ');
        so_set_status($so, 'LOST', ['closed_at' => now(), 'lost_reason' => mb_substr($reason, 0, 255)], 'เหตุผล: ' . $reason);
        tasks_cancel_for('SALES_OPPORTUNITY', $soId);
        $q = one('SELECT id FROM quotations WHERE sales_opportunity_id = ?', [$soId]);
        if ($q) tasks_cancel_for('QUOTATION', $q['id']);
        lead_refresh_status($so['lead_id']);
    });
}

function so_view_data(string $id): array
{
    $so = so_get($id);
    quotation_expire_due();
    $d = ['so' => $so];
    $d['buyer'] = db_get('organizations', $so['buyer_org_id']);
    $d['contact'] = $so['contact_id'] ? db_find('contacts', $so['contact_id']) : null;
    $d['lead'] = db_get('leads', $so['lead_id']);
    $d['matches'] = all('SELECT m.*, dv.ref_no AS device_ref, dv.brand, dv.model, dv.serial_number, dv.commercial_status, dv.technical_status,
        i.ref_no AS inv_ref, i.list_price, i.status AS inv_status, o.ref_no AS opp_ref, o.status AS opp_status
        FROM device_matches m JOIN devices dv ON dv.id = m.device_id LEFT JOIN inventory i ON i.id = m.inventory_id
        LEFT JOIN device_opportunities o ON o.id = m.device_opportunity_id WHERE m.sales_opportunity_id = ? ORDER BY m.created_at', [$id]);
    $d['quotation'] = can('quotation.view') ? one('SELECT * FROM quotations WHERE sales_opportunity_id = ?', [$id]) : null;
    $d['qv'] = $d['quotation'] ? quotation_current_version($d['quotation']['id']) : null;
    $d['deposits'] = can('deposit.view') ? all('SELECT * FROM deposits WHERE sales_opportunity_id = ? ORDER BY created_at DESC', [$id]) : [];
    $d['contracts'] = can('contract.view') ? all('SELECT * FROM contracts WHERE sales_opportunity_id = ? ORDER BY created_at DESC', [$id]) : [];
    $d['transaction'] = one('SELECT * FROM sales_transactions WHERE sales_opportunity_id = ?', [$id]);
    $d['won_missing'] = $so['status'] === 'QUOTING' ? won_missing($id) : [];
    $d['activities'] = activities_for('SALES_OPPORTUNITY', $id);
    $d['documents'] = documents_for('SALES_OPPORTUNITY', $id);
    $d['tasks'] = tasks_for_parent('SALES_OPPORTUNITY', $id);
    return $d;
}
