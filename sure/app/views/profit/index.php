<?php
$SF = tdef('S')['fields'];
$F = function (string $k, $v) use ($SF) { return fmt_html('S', $k, $SF[$k], $v); };
?>
<div class="page-head"><div><h1>กำไรรายการขาย</h1><div class="sub">Manager เท่านั้น · รวมด้านบนไม่นับรายการที่ยกเลิก</div></div>
  <div class="actions"><a class="btn" href="<?= e(url('export.csv', ['t' => 'S'])) ?>">Export CSV</a></div></div>

<div class="grid-4 kpis">
  <div class="stat"><div class="l">ยอดขายรวม</div><div class="n"><?= e(money($totals['sales'])) ?></div></div>
  <div class="stat"><div class="l">ต้นทุนรวม</div><div class="n"><?= e(money($totals['cost'])) ?></div></div>
  <div class="stat"><div class="l">กำไรขั้นต้นรวม</div><div class="n<?= $totals['gp'] < 0 ? ' neg' : '' ?>"><?= e(money($totals['gp'])) ?></div></div>
  <div class="stat"><div class="l">GP % รวม</div><div class="n<?= ($totals['gpp'] ?? 0) < 0 ? ' neg' : '' ?>"><?= e(pct($totals['gpp'])) ?></div></div>
</div>

<div class="card flush">
  <div class="table-wrap"><table class="table list">
    <thead><tr>
      <th>เลขที่การขาย</th><th>วันที่ขาย</th><th>สถานะ</th><th>รหัสเครื่อง</th><th>ยี่ห้อ/รุ่น</th><th>ลูกค้า</th><th>Sales</th><th>ช่องทางการขาย</th>
      <th class="num">ราคาขาย ก่อน VAT</th><th class="num">ต้นทุนเครื่อง</th><th class="num">กำไรขั้นต้น</th><th class="num">GP %</th><th class="num">ยอดคงค้าง</th><th>สถานะชำระเงิน</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="14" class="empty">ยังไม่มีการขาย</td></tr><?php endif; ?>
    <?php foreach ($rows as $s):
        $cls = $s['status'] === SURE_CANCELLED ? 'row-cancelled' : ($s['cost'] === null ? 'row-nocost' : (($s['gp'] ?? 0) < 0 ? 'row-loss' : '')); ?>
      <tr class="<?= $cls ?>">
        <td><a href="<?= e(rec_url('S', $s['id'])) ?>"><b><?= e($s['id']) ?></b></a></td>
        <td class="nowrap"><?= e(d($s['date'])) ?></td>
        <td><?= $F('status', $s['status']) ?></td>
        <td><a href="<?= e(rec_url('D', $s['dev'])) ?>"><?= e($s['dev']) ?></a></td>
        <td><?= e($s['devname']) ?></td>
        <td><?= e($s['custname']) ?></td>
        <td><?= e($s['sales']) ?></td>
        <td><?= e($s['channel']) ?></td>
        <td class="num"><?= $F('price', $s['price']) ?></td>
        <td class="num"><?= $F('cost', $s['cost']) ?></td>
        <td class="num"><?= $F('gp', $s['gp']) ?></td>
        <td class="num"><?= $F('gpp', $s['gpp']) ?></td>
        <td class="num"><?= $F('outstanding', $s['outstanding']) ?></td>
        <td><?= $F('paystatus', $s['paystatus']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<p class="muted legend"><span class="sw sw-gray"></span> ยกเลิก · <span class="sw sw-amber"></span> ต้นทุนว่าง · <span class="sw sw-red"></span> กำไรติดลบ</p>
