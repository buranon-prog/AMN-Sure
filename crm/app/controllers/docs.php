<?php

function c_docs_post_upload(): void
{
    $type = (string) post('parent_type', '');
    $id = (string) post('parent_id', '');
    if (!isset(PARENT_TYPES[$type]) || !is_uuid($id)) throw new NotFoundError('ไม่พบข้อมูล');
    document_add($type, $id, $_FILES['file'] ?? null, $_POST);
    flash('success', 'แนบเอกสารแล้ว');
    header('Location: ' . parent_url($type, $id) . '#documents', true, 303);
    throw new RedirectSignal();
}

function c_docs_download(): void
{
    $doc = document_get_for_download((string) get('id', ''));
    if ($doc['url']) {
        header('Location: ' . $doc['url'], true, 302);
        throw new RedirectSignal();
    }
    send_file(storage_path((string) $doc['storage_key']), (string) $doc['file_name'], (string) $doc['mime_type']);
}

function c_docs_post_archive(): void
{
    $doc = db_get('documents', (string) post('id', ''), 'เอกสาร');
    document_archive($doc['id'], post('reason'));
    flash('success', 'ลบเอกสารแล้ว');
    header('Location: ' . parent_url($doc['parent_type'], $doc['parent_id']) . '#documents', true, 303);
    throw new RedirectSignal();
}
