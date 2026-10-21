<?php
$months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$n = function ($v) { return number_format((int) $v); };
$neg = function ($v) { return $v < 0 ? ' neg' : ''; };
$tiles = [
    ['ลูกค้าทั้งหมด', $n($k['customers']), ''],
    ['ลูกค้าใหม่', $n($k['new_customers']), 'ปีที่สรุป ' . $year . ' · ตามวันที่เป็นลูกค้า'],
    ['เครื่องในระบบ', $n($k['devices']), ''],
    ['เครื่องพร้อมขาย', $n($k['ready']), ''],
    ['ขายได้ (เครื่อง)', $n($k['sold']), 'ปีที่สรุป ' . $year . ' · ไม่รวมยกเลิก'],
    ['ยอดขาย ก่อน VAT', money($k['sales']), 'ปีที่สรุป ' . $year],
    ['กำไรขั้นต้น', money($k['gp']), 'ปีที่สรุป ' . $year, $neg($k['gp'])],
    ['GP %', pct($k['gpp']), 'ปีที่สรุป ' . $year, $neg($k['gpp'] ?? 0)],
    ['ขายแล้ว – รอส่งมอบ', $n($k['waiting']), ''],
    ['ยอดค้างชำระรวม', money($k['outstanding']), ''],
    ['มูลค่าสต็อก ตามต้นทุน', money($k['stock_value']), 'รอตรวจสภาพ / กำลังซ่อม / พร้อมขาย / จองแล้ว / รอระบุ · ไม่รวมฝากขาย'],
    ['รับเงินเดือนนี้', money($k['received_month']), ''],
    ['ประกัน / MA ใกล้หมด ≤30 วัน', $n($k['expiring']), ''],
    ['งานบริการค้าง', $n($k['svc_open']), 'ไม่ใช่ ปิดงาน / ยกเลิก'],
    ['นัดติดตาม เลยกำหนด / วันนี้', $n($k['fu_due']), ''],
    ['ลูกค้ามุ่งหวัง (Lead)', $n($k['leads']), ''],
];
?>
<div class="page-head">
  <div><h1>สรุป</h1><div class="sub">Manager เท่านั้น</div></div>
  <form method="get" action="" class="actions">
    <input type="hidden" name="r" value="dashboard">
    <label for="year" class="muted">ปีที่สรุป</label>
    <select id="year" name="year" data-autosubmit>
      <?php foreach ($years as $yy): ?><option value="<?= (int) $yy ?>"<?= (int) $yy === $year ? ' selected' : '' ?>><?= (int) $yy ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn" type="submit">แสดง</button></noscript>
  </form>
</div>

<div class="grid-4 kpis">
  <?php foreach ($tiles as $tl): ?>
    <div class="stat"><div class="l"><?= e($tl[0]) ?></div><div class="n<?= $tl[3] ?? '' ?>"><?= e($tl[1]) ?></div><?php if ($tl[2] !== ''): ?><div class="s"><?= e($tl[2]) ?></div><?php endif; ?></div>
  <?php endforeach; ?>
</div>

<div class="grid-3">
  <div class="card flush">
    <div class="card-head"><h2>เครื่องตามสถานะปัจจุบัน</h2></div>
    <table class="table"><tbody>
      <?php foreach ($byStatus as $st => $c): $b = badge_class($st); ?>
        <tr><td><?= $b ? '<span class="badge ' . $b . '">' . e($st) . '</span>' : e($st) ?></td><td class="num"><?= $n($c) ?></td></tr>
      <?php endforeach; ?>
    </tbody><tfoot><tr><td>รวม</td><td class="num"><?= $n($k['devices']) ?></td></tr></tfoot></table>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>ลูกค้าตามภาค</h2></div>
    <table class="table"><tbody>
      <?php foreach ($byRegion as $rg => $c): ?><tr><td><?= e($rg) ?></td><td class="num"><?= $n($c) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot><tr><td>รวม</td><td class="num"><?= $n(array_sum($byRegion)) ?></td></tr></tfoot></table>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>ช่องทางที่ลูกค้ารู้จัก SURE</h2></div>
    <table class="table"><tbody>
      <?php foreach ($bySource as $src => $c): ?><tr><td><?= e($src) ?></td><td class="num"><?= $n($c) ?></td></tr><?php endforeach; ?>
      <?php if ($noSource): ?><tr><td class="muted">ไม่ระบุ</td><td class="num"><?= $n($noSource) ?></td></tr><?php endif; ?>
    </tbody><tfoot><tr><td>รวม</td><td class="num"><?= $n(array_sum($bySource) + $noSource) ?></td></tr></tfoot></table>
  </div>
</div>

<div class="grid-2">
  <div class="card flush">
    <div class="card-head"><h2>ยอดขายรายเดือน <?= (int) $year ?></h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เดือน</th><th class="num">เครื่อง</th><th class="num">ยอดขาย</th><th class="num">กำไรขั้นต้น</th><th class="num">รับเงิน</th></tr></thead>
      <tbody>
      <?php $tot = ['count' => 0, 'sales' => 0, 'gp' => 0, 'received' => 0];
      foreach ($monthly as $m => $r): foreach ($tot as $kk => $_) $tot[$kk] += $r[$kk]; ?>
        <tr><td><?= e($months[$m]) ?></td><td class="num"><?= $n($r['count']) ?></td><td class="num"><?= e(money($r['sales'])) ?></td>
          <td class="num<?= $neg($r['gp']) ?>"><?= e(money($r['gp'])) ?></td><td class="num<?= $neg($r['received']) ?>"><?= e(money($r['received'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>รวม</td><td class="num"><?= $n($tot['count']) ?></td><td class="num"><?= e(money($tot['sales'])) ?></td><td class="num"><?= e(money($tot['gp'])) ?></td><td class="num"><?= e(money($tot['received'])) ?></td></tr></tfoot>
    </table></div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>ผลงานพนักงานขาย <?= (int) $year ?></h2><span class="muted">ตาม Sales ผู้ปิดการขาย</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Sales ผู้ปิดการขาย</th><th class="num">เครื่อง</th><th class="num">ยอดขาย</th><th class="num">กำไรขั้นต้น</th></tr></thead>
      <tbody>
      <?php if (!$bySales): ?><tr><td colspan="4" class="empty">ยังไม่มีการขายในปีนี้</td></tr><?php endif; ?>
      <?php foreach ($bySales as $name => $r): ?>
        <tr><td><?= e($name) ?></td><td class="num"><?= $n($r['count']) ?></td><td class="num"><?= e(money($r['sales'])) ?></td><td class="num<?= $neg($r['gp']) ?>"><?= e(money($r['gp'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
