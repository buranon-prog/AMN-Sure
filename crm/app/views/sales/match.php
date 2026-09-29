<?php $fin = can('finance.view'); ?>
<div class="crumbs"><a href="<?= e(url('sales')) ?>">ดีลขาย</a> › <a href="<?= e(url('sales.view', ['id' => $so['id']])) ?>"><?= e($so['ref_no']) ?></a> › จับคู่เครื่อง</div>
<div class="page-head">
  <div><h1>จับคู่เครื่องให้ <?= e($buyer['name']) ?></h1>
    <div class="sub">ต้องการ: <?= e(trim(($so['req_brand'] ?? '') . ' ' . ($so['req_model'] ?? '') . ' ' . ($so['req_technology'] ?? '')) ?: ($so['interested_device'] ?? '—')) ?> · งบ <?= money0($so['budget_max']) ?> บาท · สภาพ <?= e(label('condition', $so['preferred_condition'])) ?></div></div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="sales.match"><input type="hidden" name="id" value="<?= e($so['id']) ?>">
  <div class="field"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="ค้นหาเองด้วยยี่ห้อ รุ่น serial DEV- (เว้นว่าง = ตามความต้องการ)"></div>
  <button class="btn" type="submit">ค้นหา</button>
</form>

<?php foreach (['inventory' => ['A) ของในสต็อก AMN Sure', 'INVENTORY'], 'seller' => ['B) เครื่องที่ผู้ขายกำลังเสนอ (ยังไม่ได้ซื้อ)', 'SELLER_OPPORTUNITY']] as $key => $def): $rows = $cands[$key]; ?>
<div class="card flush">
  <div class="card-head"><h2><?= e($def[0]) ?> (<?= count($rows) ?>)</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เครื่อง</th><th>ปี / การใช้งาน</th><th>สภาพ</th><th class="num"><?= $key === 'inventory' ? 'ราคาตั้งขาย' : 'ผู้ขายต้องการ' ?></th><?php if ($fin): ?><th class="num">ขายเป้าหมาย / ต่ำสุด 🔒</th><?php endif; ?><th>งบลูกค้า</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">ไม่พบเครื่องที่ตรงเงื่อนไข</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('devices.view', ['id' => $r['id']])) ?>" target="_blank"><b><?= e($r['brand']) ?></b> <?= e($r['model']) ?></a>
          <div class="muted"><?= e($r['ref_no']) ?><?= $r['serial_number'] ? ' · S/N ' . e($r['serial_number']) : '' ?> · <?= e($key === 'inventory' ? $r['inv_ref'] : $r['opp_ref'] . ' ' . $r['seller_name']) ?></div></td>
        <td><?= e($r['manufacture_year'] ?? '—') ?><div class="muted"><?= $r['usage_value'] !== null ? e(number_format((float) $r['usage_value']) . ' ' . label('usage_unit', $r['usage_unit'])) : '' ?></div></td>
        <td><?= badge('technical', $r['technical_status']) ?><?= $key === 'seller' ? ' ' . badge('status', $r['opp_status']) : '' ?></td>
        <td class="num"><?= money($key === 'inventory' ? $r['list_price'] : $r['asking_price']) ?></td>
        <?php if ($fin): ?><td class="num"><?= money($r['target_selling_price']) ?><div class="muted"><?= money($r['min_selling_price']) ?></div></td><?php endif; ?>
        <td><?= $r['in_budget'] === null ? '—' : ($r['in_budget'] ? '<span class="badge green">อยู่ในงบ</span>' : '<span class="badge amber">เกินงบ</span>') ?></td>
        <td class="right">
          <?php if (can('sales.edit')): ?>
          <form method="post" action="<?= e(url('sales.match_add')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($so['id']) ?>"><input type="hidden" name="source" value="<?= e($def[1]) ?>"><input type="hidden" name="ref_id" value="<?= e($r['ref_id']) ?>">
            <button class="btn sm primary" type="submit">จับคู่</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endforeach; ?>
<p><a class="btn" href="<?= e(url('sales.view', ['id' => $so['id']])) ?>">← กลับไปหน้าดีล</a></p>
