<?php
/** แสดงค่าใน audit ให้อ่านง่าย */
if (!function_exists('audit_val')) {
function audit_val($v): string
{
    if ($v === null || $v === '') return '∅';
    if (is_array($v)) {
        $s = json_encode($v, JSON_UNESCAPED_UNICODE);
        return mb_strlen($s) > 300 ? mb_substr($s, 0, 300) . '…' : $s;
    }
    $s = (string) $v;
    return mb_strlen($s) > 200 ? mb_substr($s, 0, 200) . '…' : $s;
}
}
?>
<div class="page-head"><div><h1>Audit log</h1><div class="sub">ประวัติการเปลี่ยนแปลงทั้งหมด (ค่าเก่า → ค่าใหม่) แก้ไขหรือลบไม่ได้</div></div></div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="audit">
  <div class="field"><select name="entity"><option value="">ทุกประเภทข้อมูล</option><?php foreach (labels('entity') as $k => $v): ?><option value="<?= e($k) ?>" <?= get('entity') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="field"><select name="action"><option value="">ทุกการกระทำ</option><?php foreach (labels('audit_action') as $k => $v): ?><option value="<?= e($k) ?>" <?= get('action') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div class="field"><select name="user"><option value="">ทุกผู้ใช้</option><?php foreach (user_options() as $id => $n): ?><option value="<?= e($id) ?>" <?= get('user') === $id ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
  <div class="field"><input type="date" name="from" value="<?= e(get('from')) ?>" aria-label="จากวันที่"></div>
  <div class="field"><input type="date" name="to" value="<?= e(get('to')) ?>" aria-label="ถึงวันที่"></div>
  <button class="btn" type="submit">กรอง</button>
</form>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>ข้อมูล</th><th>การกระทำ</th><th>การเปลี่ยนแปลง</th></tr></thead>
    <tbody>
    <?php if (!$page['rows']): ?><tr><td colspan="5" class="empty">ไม่มีรายการ</td></tr><?php endif; ?>
    <?php foreach ($page['rows'] as $a): $changes = json_decode((string) $a['changes'], true) ?: []; ?>
      <tr>
        <td class="nowrap"><?= e(date('d/m/Y H:i:s', strtotime($a['created_at']))) ?></td>
        <td><?= e($a['user_name'] ?? 'ระบบ') ?><div class="muted mono"><?= e($a['ip'] ?? '') ?></div></td>
        <td><?= e(label('entity', $a['entity_type'])) ?>
          <?php if ($a['entity_id'] && isset(PARENT_TYPES[$a['entity_type']])): ?><div><a href="<?= e(parent_url($a['entity_type'], $a['entity_id'])) ?>"><?= e(parent_title($a['entity_type'], $a['entity_id']) ?: 'เปิด') ?></a></div><?php endif; ?>
          <?php if ($a['entity_id']): ?><div><a class="muted" href="<?= e(url('audit', ['entity_id' => $a['entity_id']])) ?>">ประวัติทั้งหมดของรายการนี้</a></div><?php endif; ?></td>
        <td><?= e(label('audit_action', $a['action'])) ?><?= $a['note'] ? '<div class="muted">' . e($a['note']) . '</div>' : '' ?></td>
        <td>
          <?php foreach ($changes as $c): if (!is_array($c) || !isset($c['field'])) continue; ?>
            <div><span class="mono"><?= e($c['field']) ?></span>: <span class="muted"><?= e(audit_val($c['old'] ?? null)) ?></span> → <b><?= e(audit_val($c['new'] ?? null)) ?></b></div>
          <?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?= pager($page) ?>
