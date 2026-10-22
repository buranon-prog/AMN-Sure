<?php
/** @var string $t @var array $def @var array $fields @var array $cols @var array $rows */
$route = $def['route'];
$canAdd = $def['mgr'] ? is_manager() : can_edit_sure();
$base = ['r' => $route, 'q' => $q, 'f' => $filters, 'cols' => $allCols ? 'all' : null];
$sortUrl = function ($k) use ($base, $sort, $dir) {
    $p = $base + ['sort' => $k, 'dir' => ($sort === $k && $dir === 'asc') ? 'desc' : 'asc'];
    return '?' . http_build_query(array_filter($p, function ($v) { return $v !== null && $v !== '' && $v !== []; }));
};
$toggle = $base;
$toggle['cols'] = $allCols ? null : 'all';
$toggle['sort'] = $sort;
$toggle['dir'] = $dir;
$toggleUrl = '?' . http_build_query(array_filter($toggle, function ($v) { return $v !== null && $v !== '' && $v !== []; }));
?>
<div class="page-head">
  <div>
    <h1><?= e($def['title']) ?></h1>
    <?php if ($t === 'T'): ?>
      <div class="sub"><span class="badge <?= $uncosted > 0 ? 'amber' : 'green' ?>">ยังไม่ใส่ต้นทุน <?= (int) $uncosted ?> เครื่อง</span></div>
    <?php endif; ?>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(url('export.csv', ['t' => $t])) ?>">Export CSV</a>
    <?php if ($canAdd): ?><a class="btn primary" href="<?= e(url($route . '.new')) ?>">+ เพิ่ม</a><?php endif; ?>
  </div>
</div>

<form class="filters card" method="get" action="">
  <input type="hidden" name="r" value="<?= e($route) ?>">
  <?php if ($allCols): ?><input type="hidden" name="cols" value="all"><?php endif; ?>
  <div class="field"><label for="q">ค้นหา</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="พิมพ์คำค้น…"></div>
  <?php foreach ($filterOptions as $k => $vals): ?>
    <div class="field">
      <label for="flt_<?= e($k) ?>"><?= e($fields[$k]['label']) ?></label>
      <select id="flt_<?= e($k) ?>" name="f[<?= e($k) ?>]" data-autosubmit>
        <option value="">ทั้งหมด</option>
        <?php foreach ($vals as $v): ?><option value="<?= e($v) ?>"<?= ($filters[$k] ?? '') === $v ? ' selected' : '' ?>><?= e(fmt_value($fields[$k], $v)) ?></option><?php endforeach; ?>
      </select>
    </div>
  <?php endforeach; ?>
  <div class="field"><button class="btn" type="submit">ค้นหา</button> <?php if ($q !== null || $filters): ?><a class="btn link" href="<?= e(url($route)) ?>">ล้าง</a><?php endif; ?></div>
</form>

<div class="card flush">
  <div class="card-head">
    <span class="muted">ทั้งหมด <?= number_format($pager['total']) ?> รายการ</span>
    <a class="btn sm" href="<?= e($toggleUrl) ?>"><?= $allCols ? 'แสดงเฉพาะคอลัมน์หลัก' : 'แสดงทุกคอลัมน์' ?></a>
  </div>
  <div class="table-wrap"><table class="table list">
    <thead><tr>
      <?php foreach ($cols as $k): $f = $fields[$k]; ?>
        <th class="<?= is_numeric_type($f) ? 'num' : '' ?><?= $f['how'] === 'auto' ? ' autocol' : '' ?>"><a href="<?= e($sortUrl($k)) ?>"><?= e($f['label']) ?><?= $sort === $k ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' ?></a></th>
      <?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td class="empty" colspan="<?= count($cols) ?>">ไม่พบข้อมูล</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $pk = $r[$def['pk']]; ?>
      <tr>
        <?php foreach ($cols as $i => $k): $f = $fields[$k]; $html = fmt_html($t, $k, $f, $r[$k] ?? null);
            // ข้อความยาว (ชื่อ ที่อยู่ หมวด) ตัดบรรทัดได้ ส่วนรหัส วันที่ ตัวเลข ป้ายสถานะ อยู่บรรทัดเดียว
            $long = !is_numeric_type($f) && $f['type'] !== 'date' && mb_strlen((string) ($r[$k] ?? '')) > 18; ?>
          <td class="<?= is_numeric_type($f) ? 'num' : '' ?><?= $f['type'] === 'textarea' ? ' clip' : ($long ? ' wrap' : '') ?>">
            <?php if ($i === 0): ?><a href="<?= e(rec_url($t, $pk)) ?>"><b><?= $html === '' ? '—' : $html ?></b></a><?php else: ?><?= $html ?><?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="pad"><?= pager($pager) ?></div>
</div>
