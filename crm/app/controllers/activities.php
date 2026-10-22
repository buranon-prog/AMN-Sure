<?php

function c_activities_post_add(): void
{
    $type = (string) post('parent_type', '');
    $id = (string) post('parent_id', '');
    if (!isset(PARENT_TYPES[$type]) || !is_uuid($id)) throw new NotFoundError('ไม่พบข้อมูล');
    activity_add($type, $id, $_POST);
    flash('success', 'บันทึกกิจกรรมแล้ว');
    header('Location: ' . parent_url($type, $id) . '#activities', true, 303);
    throw new RedirectSignal();
}
