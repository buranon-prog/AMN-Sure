<?php
// ใบงานช่าง (technical jobs) และเคสบริการหลังการขาย (service cases)

const JOB_TYPES = ['INSPECTION', 'REPAIR', 'REFURBISH', 'QC', 'DELIVERY', 'INSTALLATION', 'PM'];
const JOB_STATUSES = ['OPEN', 'SCHEDULED', 'IN_PROGRESS', 'DONE', 'CANCELLED'];
const CASE_STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];

function job_create(array $d): string
{
    require_cap('job.edit');
    $deviceId = v_uuid($d['device_id'] ?? null, 'เครื่อง');
    db_get('devices', $deviceId, 'เครื่อง');
    $type = v_in($d['type'] ?? null, ['REPAIR', 'REFURBISH', 'PM'], 'ประเภทงาน');
    $title = s($d['title'] ?? null);
    if (!$title) throw new AppError('กรุณากรอกชื่องาน');
    $engineer = v_uuid($d['assigned_engineer_id'] ?? null, 'ช่างผู้รับผิดชอบ', false);
    return tx(function () use ($deviceId, $type, $title, $engineer, $d) {
        $id = insert_audited('technical_jobs', 'TECHNICAL_JOB', [
            'ref_no' => next_ref('JOB'), 'type' => $type, 'device_id' => $deviceId,
            'parent_type' => 'DEVICE', 'parent_id' => $deviceId, 'title' => v_maxlen($title, 255, 'ชื่องาน'),
            'assigned_engineer_id' => $engineer, 'status' => $engineer ? 'SCHEDULED' : 'OPEN',
            'scheduled_date' => v_date($d['scheduled_date'] ?? null, 'วันนัด'),
        ]);
        if (in_array($type, ['REPAIR', 'REFURBISH'], true)) {
            update_audited('devices', 'DEVICE', $deviceId, ['technical_status' => 'UNDER_REPAIR'], 'STATUS_CHANGE', 'เปิดใบงาน' . label('job_type', $type));
        }
        task_create([
            'title' => label('job_type', $type) . ': ' . $title, 'type' => 'JOB', 'parent_type' => 'TECHNICAL_JOB', 'parent_id' => $id,
            'assignee_id' => $engineer, 'assignee_role' => $engineer ? null : 'SERVICE_ENGINEER',
            'due_date' => v_date($d['scheduled_date'] ?? null, 'วันนัด'),
        ]);
        log_activity('DEVICE', $deviceId, 'SYSTEM', 'เปิดใบงาน ' . label('job_type', $type) . ': ' . $title, ['device_id' => $deviceId]);
        return $id;
    });
}

/** อัปเดตใบงาน ถ้าปิดงานซ่อม/ปรับสภาพ จะบันทึกเข้าประวัติซ่อมของเครื่องอัตโนมัติ */
function job_update(string $id, array $d): void
{
    require_cap('job.edit');
    tx(function () use ($id, $d) {
        $job = db_lock('technical_jobs', $id, 'ใบงาน');
        if (in_array($job['status'], ['DONE', 'CANCELLED'], true)) throw new AppError('ใบงานนี้ปิดแล้ว');
        if ($job['type'] === 'INSPECTION') throw new AppError('ใบงานตรวจเครื่องจะปิดเองเมื่อบันทึกผลตรวจเสร็จ');
        $status = v_in($d['status'] ?? $job['status'], JOB_STATUSES, 'สถานะใบงาน');
        $data = [
            'status' => $status,
            'assigned_engineer_id' => v_uuid($d['assigned_engineer_id'] ?? null, 'ช่าง', false) ?? $job['assigned_engineer_id'],
            'scheduled_date' => v_date($d['scheduled_date'] ?? null, 'วันนัด') ?? $job['scheduled_date'],
            'hours' => v_decimal($d['hours'] ?? null, 'ชั่วโมงทำงาน') ?? $job['hours'],
            'parts_used' => s($d['parts_used'] ?? null) ?? $job['parts_used'],
            'result_note' => s($d['result_note'] ?? null) ?? $job['result_note'],
        ];
        if ($status === 'DONE') {
            if (!$data['result_note']) throw new AppError('กรุณาบันทึกผลการทำงานก่อนปิดใบงาน');
            $data['completed_at'] = now();
        }
        update_audited('technical_jobs', 'TECHNICAL_JOB', $id, $data, $status !== $job['status'] ? 'STATUS_CHANGE' : 'UPDATE');
        if ($status === 'DONE' || $status === 'CANCELLED') tasks_close_for('TECHNICAL_JOB', $id);
        if ($status === 'DONE' && $job['device_id'] && in_array($job['type'], ['REPAIR', 'REFURBISH', 'PM'], true)) {
            db_insert('device_service_history', [
                'device_id' => $job['device_id'], 'service_date' => today(),
                'type' => $job['type'] === 'PM' ? 'PM' : 'REPAIR',
                'description' => $job['title'] . "\n" . $data['result_note'],
                'parts_replaced' => $data['parts_used'], 'performed_by' => user_name($data['assigned_engineer_id'] ?? current_user_id()),
                'source' => 'INTERNAL', 'technical_job_id' => $id,
            ]);
            update_audited('devices', 'DEVICE', $job['device_id'], ['technical_status' => 'READY'], 'STATUS_CHANGE', 'ปิดใบงาน ' . $job['ref_no']);
            log_activity('DEVICE', $job['device_id'], 'SYSTEM', 'ปิดใบงาน ' . $job['ref_no'] . ': ' . $data['result_note'], ['device_id' => $job['device_id']]);
        }
    });
}

function case_save(?string $id, array $d): string
{
    require_cap('service_case.edit');
    $issue = s($d['issue'] ?? null);
    if (!$issue) throw new AppError('กรุณากรอกอาการ/ปัญหาที่ลูกค้าแจ้ง');
    return tx(function () use ($id, $d, $issue) {
        if ($id) {
            $c = db_lock('service_cases', $id, 'เคสบริการ');
            $status = v_in($d['status'] ?? $c['status'], CASE_STATUSES, 'สถานะเคส');
            if (in_array($status, ['RESOLVED', 'CLOSED'], true) && !s($d['resolution'] ?? null)) throw new AppError('กรุณาบันทึกวิธีแก้ไขก่อนปิดเคส');
            update_audited('service_cases', 'SERVICE_CASE', $id, [
                'issue' => $issue, 'status' => $status,
                'under_warranty' => !empty($d['under_warranty']) && $d['under_warranty'] !== '0' ? 1 : 0,
                'resolution' => s($d['resolution'] ?? null),
                'assigned_to' => v_uuid($d['assigned_to'] ?? null, 'ผู้รับผิดชอบ', false),
                'closed_at' => $status === 'CLOSED' ? ($c['closed_at'] ?? now()) : null,
            ]);
            return $id;
        }
        $deviceId = v_uuid($d['device_id'] ?? null, 'เครื่อง');
        $dv = db_get('devices', $deviceId, 'เครื่อง');
        $id = insert_audited('service_cases', 'SERVICE_CASE', [
            'ref_no' => next_ref('SC'), 'device_id' => $deviceId, 'organization_id' => $dv['current_owner_org_id'],
            'reported_at' => v_date($d['reported_at'] ?? null, 'วันที่แจ้ง') ?? today(), 'issue' => $issue,
            'under_warranty' => !empty($d['under_warranty']) && $d['under_warranty'] !== '0' ? 1 : 0,
            'status' => 'OPEN', 'assigned_to' => v_uuid($d['assigned_to'] ?? null, 'ผู้รับผิดชอบ', false),
        ]);
        log_activity('SERVICE_CASE', $id, 'SYSTEM', 'ลูกค้าแจ้งปัญหา: ' . $issue, ['organization_id' => $dv['current_owner_org_id'], 'device_id' => $deviceId]);
        task_create([
            'title' => 'เคสบริการ ' . $dv['brand'] . ' ' . $dv['model'] . ': ' . mb_substr($issue, 0, 80),
            'type' => 'SERVICE_CASE', 'parent_type' => 'SERVICE_CASE', 'parent_id' => $id,
            'assignee_id' => v_uuid($d['assigned_to'] ?? null, 'ผู้รับผิดชอบ', false),
            'assignee_role' => v_uuid($d['assigned_to'] ?? null, 'ผู้รับผิดชอบ', false) ? null : 'SERVICE_ENGINEER',
            'due_date' => add_days(today(), 2), 'priority' => 'HIGH',
        ]);
        return $id;
    });
}
