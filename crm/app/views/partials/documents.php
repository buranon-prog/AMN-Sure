<?php
/** @var string $parentType @var string $parentId @var array $documents @var ?string $defaultCategory */
$canAdd = parent_can_edit($parentType);
?>
<div class="card" id="documents">
  <div class="card-head"><h2>เอกสาร / รูป / วิดีโอ</h2></div>
  <?php if ($documents): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ชื่อ</th><th>ประเภท</th><th>ขนาด</th><th>โดย</th><th>เมื่อ</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($documents as $doc): ?>
        <tr>
          <td>
            <?php if ($doc['url']): ?>
              <a href="<?= e($doc['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($doc['title']) ?> ↗</a>
            <?php else: ?>
              <a href="<?= e(url('docs.download', ['id' => $doc['id']])) ?>" target="_blank"><?= e($doc['title']) ?></a>
            <?php endif; ?>
            <?php if (($doc['parent_type'] ?? $parentType) !== $parentType || ($doc['parent_id'] ?? $parentId) !== $parentId): ?>
              <div class="muted"><a href="<?= e(parent_url($doc['parent_type'], $doc['parent_id'])) ?>"><?= e(parent_title($doc['parent_type'], $doc['parent_id'])) ?></a></div>
            <?php endif; ?>
          </td>
          <td><?= e(label('doc_category', $doc['category'])) ?></td>
          <td class="nowrap"><?= $doc['url'] ? 'ลิงก์' : e(human_size($doc['size_bytes'] !== null ? (int) $doc['size_bytes'] : null)) ?></td>
          <td><?= e($doc['uploader'] ?? '—') ?></td>
          <td class="nowrap"><?= e(dt($doc['created_at'])) ?></td>
          <td class="right">
            <?php if (parent_can_edit($doc['parent_type'])): ?>
              <?= post_button('docs.archive', ['id' => $doc['id']], 'ลบ', ['class' => 'sm danger', 'confirm' => 'ลบเอกสารนี้? (ระบบยังเก็บประวัติไว้)']) ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty-state">ยังไม่มีเอกสาร</div>
  <?php endif; ?>
  <?php if ($canAdd): ?>
    <form method="post" action="<?= e(url('docs.upload')) ?>" enctype="multipart/form-data" class="no-print" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="parent_type" value="<?= e($parentType) ?>">
      <input type="hidden" name="parent_id" value="<?= e($parentId) ?>">
      <div class="form-grid three">
        <div class="field"><label for="f_file">ไฟล์ (สูงสุด <?= e(setting('max_upload_mb', 10)) ?> MB)</label><input type="file" name="file" id="f_file"></div>
        <?= f_input('url', 'หรือ ลิงก์ (Google Drive / YouTube)', null, ['type' => 'url', 'placeholder' => 'https://…']) ?>
        <?= f_select('category', 'ประเภท', labels('doc_category'), $defaultCategory ?? 'PHOTO') ?>
        <?= f_input('title', 'ชื่อเอกสาร (ไม่บังคับ)', null, ['class' => 'full']) ?>
      </div>
      <button class="btn sm" type="submit">แนบเอกสาร</button>
    </form>
  <?php endif; ?>
</div>
