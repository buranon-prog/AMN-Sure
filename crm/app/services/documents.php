<?php
// เอกสาร/รูป/วิดีโอ ผูกกับ record ใดก็ได้ — ไฟล์เก็บใน storage/ และดาวน์โหลดผ่านการตรวจสิทธิ์ของ record แม่เท่านั้น

const DOC_CATEGORIES = ['PHOTO', 'VIDEO', 'CONTRACT', 'PAYMENT_EVIDENCE', 'INVOICE', 'INSPECTION_REPORT', 'QUOTATION', 'OTHER'];

/**
 * แนบไฟล์หรือลิงก์
 * $file = รายการจาก $_FILES (หรือ null), $d: category, title, url
 */
function document_add(string $parentType, string $parentId, ?array $file, array $d, bool $skipPermission = false): string
{
    $row = $skipPermission ? parent_row($parentType, $parentId) : parent_require_edit($parentType, $parentId);
    $category = v_in($d['category'] ?? 'OTHER', DOC_CATEGORIES, 'ประเภทเอกสาร');
    $title = v_maxlen(s($d['title'] ?? null), 255, 'ชื่อเอกสาร');
    $url = s($d['url'] ?? null);
    $stored = store_upload($file);
    if (!$stored && !$url) throw new AppError('กรุณาเลือกไฟล์ หรือใส่ลิงก์ของเอกสาร');
    if ($url) {
        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) throw new AppError('ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://');
        v_maxlen($url, 1000, 'ลิงก์');
    }
    $ctx = parent_context($parentType, $row);
    return tx(function () use ($parentType, $parentId, $ctx, $category, $title, $url, $stored) {
        $data = [
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'organization_id' => $ctx['organization_id'],
            'device_id' => $ctx['device_id'],
            'category' => $category,
            'title' => $title ?? ($stored['file_name'] ?? $url),
            'url' => $stored ? null : $url,
        ] + ($stored ?? []);
        $id = insert_audited('documents', 'DOCUMENT', $data);
        log_activity($parentType, $parentId, 'SYSTEM', 'แนบเอกสาร: ' . $data['title'], $ctx);
        return $id;
    });
}

function document_get_for_download(string $id): array
{
    $doc = db_get('documents', $id, 'เอกสาร');
    parent_require_view($doc['parent_type'], $doc['parent_id']);
    if ($doc['archived_at']) throw new NotFoundError('เอกสารนี้ถูกลบออกแล้ว');
    return $doc;
}

function document_archive(string $id, ?string $reason): void
{
    $doc = db_get('documents', $id, 'เอกสาร');
    parent_require_edit($doc['parent_type'], $doc['parent_id']);
    // เอกสารการเงิน/สัญญา เก็บถาวร — ลบได้เฉพาะผู้มีสิทธิ์ void
    if (in_array($doc['category'], ['CONTRACT', 'PAYMENT_EVIDENCE', 'INVOICE'], true)) require_cap('record.void');
    update_audited('documents', 'DOCUMENT', $id, ['archived_at' => now(), 'archived_by' => current_user_id(), 'archive_reason' => s($reason) ?? 'ลบโดยผู้ใช้'], 'ARCHIVE');
}

function documents_for(string $parentType, string $parentId): array
{
    return all('SELECT d.*, u.name AS uploader FROM documents d LEFT JOIN users u ON u.id = d.created_by
                WHERE d.parent_type = ? AND d.parent_id = ? AND d.archived_at IS NULL ORDER BY d.created_at DESC', [$parentType, $parentId]);
}

/** เอกสารทั้งหมดที่เกี่ยวกับลูกค้า — กรองตามสิทธิ์ดูของ record แม่แต่ละประเภท */
function documents_for_org(string $orgId): array
{
    $rows = all('SELECT d.*, u.name AS uploader FROM documents d LEFT JOIN users u ON u.id = d.created_by
                 WHERE d.organization_id = ? AND d.archived_at IS NULL ORDER BY d.created_at DESC LIMIT 300', [$orgId]);
    return array_values(array_filter($rows, function ($r) { return isset(PARENT_TYPES[$r['parent_type']]) && can_any(...PARENT_TYPES[$r['parent_type']][1]); }));
}

function documents_for_device(string $deviceId): array
{
    $rows = all('SELECT d.*, u.name AS uploader FROM documents d LEFT JOIN users u ON u.id = d.created_by
                 WHERE d.device_id = ? AND d.archived_at IS NULL ORDER BY d.created_at DESC LIMIT 300', [$deviceId]);
    return array_values(array_filter($rows, function ($r) { return isset(PARENT_TYPES[$r['parent_type']]) && can_any(...PARENT_TYPES[$r['parent_type']][1]); }));
}

function document_count(string $parentType, string $parentId, ?string $category = null): int
{
    $sql = 'SELECT COUNT(*) FROM documents WHERE parent_type = ? AND parent_id = ? AND archived_at IS NULL';
    $p = [$parentType, $parentId];
    if ($category) { $sql .= ' AND category = ?'; $p[] = $category; }
    return (int) val($sql, $p);
}
