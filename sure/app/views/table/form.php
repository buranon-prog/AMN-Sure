<?php
/** @var string $t @var array $def @var ?array $row @var bool $isNew @var bool $editable @var array $ctx */
$fields = visible_fields($t);
$pkVal = $row[$def['pk']] ?? null;
?>
<div class="crumbs"><a href="<?= e(url($def['route'])) ?>"><?= e($def['title']) ?></a> › <?= $isNew ? 'เพิ่ม' : e((string) $pkVal) ?></div>
<div class="page-head">
  <div>
    <h1><?= $isNew ? 'เพิ่ม' . e($def['title']) : e($def['title']) . ' <span class="ref">' . e((string) $pkVal) . '</span>' ?></h1>
    <?php if (!$isNew): ?><div class="sub audit"><?= audit_line($row) ?></div><?php endif; ?>
    <?php if (!$isNew && !$editable): ?><div class="sub"><span class="badge gray">ดูอย่างเดียว</span></div><?php endif; ?>
  </div>
  <div class="actions"><a class="btn" href="<?= e(url($def['route'])) ?>">กลับไปรายการ</a></div>
</div>

<?php if ($editable): ?>
<form method="post" action="<?= e(url($def['route'] . '.save')) ?>" data-warn-unsaved>
  <?= csrf_field() ?>
  <?php if (!$isNew): ?><input type="hidden" name="_pk" value="<?= e((string) $pkVal) ?>"><?php endif; ?>
<?php else: ?>
<div class="readonly-record">
<?php endif; ?>
  <?php foreach ($def['groups'] as $g):
      $keys = array_values(array_filter($g[1], function ($k) use ($fields) { return isset($fields[$k]); }));
      if (!$keys) continue;
      $mgrOnly = true;
      foreach ($keys as $k) { if (!$fields[$k]['mgr']) { $mgrOnly = false; break; } }
      $legend = $g[0] !== '' ? $g[0] : ($mgrOnly && !$def['mgr'] ? 'Manager เท่านั้น' : ''); ?>
    <fieldset class="card<?= $mgrOnly && !$def['mgr'] ? ' mgr' : '' ?>">
      <?php if ($legend !== ''): ?><legend><?= e($legend) ?></legend><?php endif; ?>
      <div class="form-grid">
        <?php foreach ($keys as $k): ?>
          <?= form_field($t, $k, $fields[$k], $row, $isNew, $editable, $ctx) ?>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
<?php if ($editable): ?>
    <div class="form-actions sticky">
      <button class="btn primary" type="submit"><?= $isNew ? 'บันทึก' : 'บันทึกการแก้ไข' ?></button>
      <a class="btn" href="<?= e(url($def['route'])) ?>">ยกเลิก</a>
    </div>
</form>
<?php else: ?>
</div>
<?php endif; ?>

<?php if (!$isNew && is_manager()): ?>
  <form method="post" action="<?= e(url($def['route'] . '.delete')) ?>" class="card danger delete-box" data-confirm="ลบ <?= e($def['title']) ?> <?= e((string) $pkVal) ?> ถาวร? (กู้คืนไม่ได้)">
    <?= csrf_field() ?><input type="hidden" name="_pk" value="<?= e((string) $pkVal) ?>">
    <div><b>ลบแถวนี้</b> <span class="muted">— เฉพาะ Manager · ถ้ามีข้อมูลอื่นอ้างถึงจะลบไม่ได้</span></div>
    <button class="btn danger" type="submit">ลบ</button>
  </form>
<?php endif; ?>
