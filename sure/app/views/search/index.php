<?php
$F = function (string $t, string $k, $v) { return fmt_html($t, $k, tdef($t)['fields'][$k], $v); };
$kv = function (string $label, string $html) { return '<dt>' . e($label) . '</dt><dd>' . ($html === '' ? '<span class="muted">—</span>' : $html) . '</dd>'; };
$link = function (string $t, ?string $pk) { return $pk ? '<a href="' . e(rec_url($t, $pk)) . '">' . e($pk) . '</a>' : ''; };
$list = $mode === 'device' ? $ds['D'] : $ds['C'];
?>
<div class="page-head"><div><h1>ค้นหา</h1><div class="sub">ใส่หรือเลือกรหัส แล้วกดค้นหา</div></div></div>

<nav class="subnav">
  <a href="<?= e(url('search', ['mode' => 'device'])) ?>" class="<?= $mode === 'device' ? 'active' : '' ?>">ค้นหาเครื่อง</a>
  <a href="<?= e(url('search', ['mode' => 'customer'])) ?>" class="<?= $mode === 'customer' ? 'active' : '' ?>">ค้นหาลูกค้า</a>
</nav>

<form class="card filters" method="get" action="">
  <input type="hidden" name="r" value="search"><input type="hidden" name="mode" value="<?= e($mode) ?>">
  <div class="field grow">
    <label for="code"><?= $mode === 'device' ? 'รหัสเครื่อง' : 'รหัสลูกค้า' ?></label>
    <input type="search" id="code" name="code" list="codes" value="<?= e($code) ?>" placeholder="<?= $mode === 'device' ? 'เช่น SURE26001' : 'เช่น SURE001' ?>" autocomplete="off">
    <datalist id="codes">
      <?php foreach ($list as $id => $r): ?><option value="<?= e($id) ?>"><?= e($mode === 'device' ? dev_name($r) . ' · ' . $r['serial'] : $r['name']) ?></option><?php endforeach; ?>
    </datalist>
  </div>
  <div class="field"><button class="btn primary" type="submit">ค้นหา</button></div>
</form>

<?php if ($notFound): ?>
  <div class="flash warning">ไม่พบ<?= $mode === 'device' ? 'รหัสเครื่อง' : 'รหัสลูกค้า' ?> "<?= e($code) ?>"</div>
<?php endif; ?>

<?php if ($mode === 'device' && !empty($dev)): $s = $sale; $o = $owner; $b = $branch; ?>
  <div class="grid-3">
    <div class="card">
      <h2>ข้อมูลเครื่อง <?= $link('D', $dev['id']) ?></h2>
      <dl class="kv">
        <?= $kv('ยี่ห้อ/รุ่น', e(dev_name($dev))) ?>
        <?= $kv('Serial', e($dev['serial'])) ?>
        <?= $kv('หมวด', e($dev['cat'])) ?>
        <?= $kv('ปีที่ผลิต', e($dev['year'] ?? '')) ?>
        <?= $kv('เกรด', e($dev['grade'])) ?>
        <?= $kv('ประเภทการได้มา', e($dev['acq'])) ?>
        <?= $kv('รับเครื่องจาก', $dev['seller'] ? $link('C', $dev['seller']) . ' ' . e($dev['sellername']) : '') ?>
        <?= $kv('สถานะปัจจุบัน', $F('D', 'status', $dev['status'])) ?>
        <?= $kv('ราคาตั้งขาย', $F('D', 'listprice', $dev['listprice'])) ?>
        <?= $kv('ที่เก็บเครื่อง', e($dev['loc'])) ?>
      </dl>
    </div>
    <div class="card">
      <h2>เจ้าของ</h2>
      <dl class="kv">
        <?= $kv('รหัส/ชื่อลูกค้า', $o ? $link('C', $o['id']) . ' ' . e($o['name']) : '') ?>
        <?= $kv('ผู้ติดต่อหลัก', e($o['contact'] ?? '')) ?>
        <?= $kv('เบอร์', e($o['phone'] ?? '')) ?>
        <?= $kv('สาขาที่ติดตั้ง', $b ? $link('B', $b['id']) . ' ' . e($b['bname']) : '') ?>
        <?= $kv('ผู้ติดต่อสาขา/เบอร์', $b ? e(trim(($b['contact'] ?? '') . ' / ' . ($b['phone'] ?? ''), ' /')) : '') ?>
        <?= $kv('จังหวัด/ภาค', $b ? e($b['prov'] . ' / ' . $b['region']) : '') ?>
        <?= $kv('สถานที่ติดตั้ง', e($s['site'] ?? '')) ?>
        <?= $kv('Sales ผู้ดูแล', e($o['sales'] ?? '')) ?>
        <?= $kv('Google Maps สาขา', $b ? $F('B', 'maps', $b['maps']) : '') ?>
      </dl>
    </div>
    <div class="card">
      <h2>การขาย/ประกัน</h2>
      <dl class="kv">
        <?= $kv('เลขที่การขาย', $link('S', $dev['soid'])) ?>
        <?= $kv('วันที่ขาย', e(fmt_value(['type' => 'date'], $dev['saledate']))) ?>
        <?= $kv('ราคาขาย', $F('D', 'saleprice', $dev['saleprice'])) ?>
        <?= $kv('ยอดคงค้าง', $s ? $F('S', 'outstanding', $s['outstanding']) : '') ?>
        <?= $kv('วันที่ส่งมอบ', e(fmt_value(['type' => 'date'], $dev['delivdate']))) ?>
        <?= $kv('วันหมดประกัน', e(fmt_value(['type' => 'date'], $dev['wend']))) ?>
        <?= $kv('MA ถึงวันที่', e(fmt_value(['type' => 'date'], $dev['maend']))) ?>
        <?= $kv('สถานะความคุ้มครอง', $F('D', 'cover', $dev['cover'])) ?>
        <?= $kv('จำนวนงานบริการ', e((string) $dev['nsvc'])) ?>
        <?= $kv('บริการล่าสุด', e(fmt_value(['type' => 'date'], $dev['lastsvc']))) ?>
      </dl>
    </div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>ประวัติบริการล่าสุด 5 งาน</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เลขที่งานบริการ</th><th>วันที่แจ้ง / เปิดงาน</th><th>ประเภทงาน</th><th>อาการ / รายละเอียดงาน</th><th>วิศวกรผู้รับผิดชอบ</th><th>สถานะงาน</th><th>ความคุ้มครอง ณ วันแจ้ง</th></tr></thead>
      <tbody>
      <?php if (!$services): ?><tr><td colspan="7" class="empty">ยังไม่มีงานบริการ</td></tr><?php endif; ?>
      <?php foreach ($services as $v): ?>
        <tr><td><?= $link('V', $v['id']) ?></td><td class="nowrap"><?= e(d($v['date'])) ?></td><td><?= e($v['type']) ?></td><td class="pre"><?= e($v['detail']) ?></td>
          <td><?= e($v['eng']) ?></td><td><?= $F('V', 'jstatus', $v['jstatus']) ?></td><td><?= $F('V', 'cover', $v['cover']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>

<?php if ($mode === 'customer' && !empty($cust)): $c = $cust; $g = $summary; ?>
  <div class="grid-3">
    <div class="card">
      <h2>ข้อมูลลูกค้า <?= $link('C', $c['id']) ?></h2>
      <dl class="kv">
        <?= $kv('ชื่อลูกค้า / ชื่อกิจการ', e($c['name'])) ?>
        <?= $kv('ประเภทลูกค้า', e($c['ctype'])) ?>
        <?= $kv('ชื่อนิติบุคคล (ออกใบกำกับภาษี)', e($c['legal'])) ?>
        <?= $kv('เลขผู้เสียภาษี 13 หลัก', e($c['taxid'])) ?>
        <?= $kv('ผู้ติดต่อหลัก / ผู้ตัดสินใจ', e($c['contact'])) ?>
        <?= $kv('ตำแหน่ง', e($c['pos'])) ?>
        <?= $kv('วันที่เป็นลูกค้า', e(d($c['since']))) ?>
        <?= $kv('สถานะลูกค้า', $F('C', 'status', $c['status'])) ?>
      </dl>
    </div>
    <div class="card">
      <h2>ติดต่อ &amp; ที่อยู่</h2>
      <dl class="kv">
        <?= $kv('เบอร์โทรหลัก', e($c['phone'])) ?>
        <?= $kv('เบอร์โทรสำรอง', e($c['phone2'])) ?>
        <?= $kv('LINE ID', e($c['line'])) ?>
        <?= $kv('อีเมล', e($c['email'])) ?>
        <?= $kv('Facebook / IG / TikTok', e($c['social'])) ?>
        <?= $kv('ลิงก์โฟลเดอร์เอกสาร (Google Drive)', $F('C', 'docs', $c['docs'])) ?>
        <?= $kv('ที่อยู่', e(full_address($c))) ?>
        <?= $kv('ภาค', e($c['region'])) ?>
        <?= $kv('จำนวนสาขา', e((string) $g['nbranch'])) ?>
        <?= $kv('รหัสสาขาถัดไป', '<b>' . e($nextBranch) . '</b>' . (can_edit_sure() ? ' <a class="btn sm" href="' . e(url('branches.new', ['cust' => $c['id']])) . '">+ เพิ่มสาขา</a>' : '')) ?>
      </dl>
    </div>
    <div class="card">
      <h2>สรุปกับ SURE</h2>
      <dl class="kv">
        <?= $kv('ความสัมพันธ์', e($g['rel'])) ?>
        <?= $kv('เครื่องที่ซื้อ', e((string) $g['nbuy'])) ?>
        <?= $kv('ยอดซื้อรวม', e(money($g['amt']))) ?>
        <?= $kv('ซื้อล่าสุด', e(fmt_value(['type' => 'date'], $g['last']))) ?>
        <?= $kv('ยอดค้างชำระ', e(money($g['due']))) ?>
        <?= $kv('เครื่องที่ขายให้ SURE', e((string) $g['nsell'])) ?>
        <?= $kv('ติดต่อล่าสุด', e(fmt_value(['type' => 'date'], $g['lastct']))) ?>
        <?= $kv('นัดติดตามถัดไป', e(fmt_value(['type' => 'date'], $g['nextfu']))) ?>
        <?= $kv('รู้จัก SURE จาก', e($c['source'])) ?>
        <?= $kv('หมายเหตุ', '<span class="pre">' . e($c['note']) . '</span>') ?>
      </dl>
    </div>
  </div>

  <div class="card flush">
    <div class="card-head"><h2>สาขา</h2><span class="muted">สูงสุด 10 สาขา</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>รหัสสาขา</th><th>ชื่อสาขา</th><th>จังหวัด/ภาค</th><th>ที่อยู่</th><th>ผู้ติดต่อ/เบอร์</th><th class="num">จำนวนเครื่องที่ติดตั้ง</th></tr></thead>
      <tbody>
      <?php if (!$branches): ?><tr><td colspan="6" class="empty">ยังไม่มีสาขา</td></tr><?php endif; ?>
      <?php foreach ($branches as $b): ?>
        <tr><td><?= $link('B', $b['id']) ?></td><td><?= e($b['bname']) ?><?= $b['status'] === SURE_BRANCH_CLOSED ? ' <span class="badge gray">(ปิดสาขา)</span>' : '' ?></td>
          <td><?= e($b['prov'] . ' / ' . $b['region']) ?></td><td><?= e(full_address($b)) ?></td>
          <td><?= e(trim(($b['contact'] ?? '') . ' / ' . ($b['phone'] ?? ''), ' /')) ?></td><td class="num"><?= (int) ($installed[$b['id']] ?? 0) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card flush">
    <div class="card-head"><h2>เครื่องที่ซื้อ</h2><span class="muted">สูงสุด 10 เครื่อง ไม่รวมยกเลิก</span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>รหัสเครื่อง</th><th>ยี่ห้อ/รุ่น</th><th>สาขาที่ติดตั้ง</th><th>วันที่ส่งมอบ</th><th>ความคุ้มครอง</th></tr></thead>
      <tbody>
      <?php if (!$bought): ?><tr><td colspan="5" class="empty">ยังไม่มีการซื้อ</td></tr><?php endif; ?>
      <?php foreach ($bought as $s): $dv = $ds['D'][$s['dev']] ?? []; ?>
        <tr><td><?= $link('D', $s['dev']) ?></td><td><?= e($s['devname']) ?></td><td><?= e($s['branchname']) ?></td>
          <td><?= $s['deliv'] ? e(d($s['deliv'])) : '<span class="badge gray">รอส่งมอบ</span>' ?></td>
          <td><?= $F('D', 'cover', $dv['cover'] ?? null) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card flush">
    <div class="card-head"><h2>ประวัติการติดต่อล่าสุด 5 รายการ</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>วันที่</th><th>ช่องทาง</th><th>เรื่อง · สาขา</th><th>รายละเอียด</th><th>สถานะ</th><th>นัดถัดไป</th></tr></thead>
      <tbody>
      <?php if (!$contacts): ?><tr><td colspan="6" class="empty">ยังไม่มีประวัติการติดต่อ</td></tr><?php endif; ?>
      <?php foreach ($contacts as $a): ?>
        <tr><td class="nowrap"><a href="<?= e(rec_url('A', $a['id'])) ?>"><?= e(d($a['date'])) ?></a></td><td><?= e($a['channel']) ?></td>
          <td><?= e($a['topic']) ?><?= $a['branchname'] ? ' · ' . e($a['branchname']) : '' ?></td><td class="pre"><?= e($a['detail']) ?></td>
          <td><?= $F('A', 'result', $a['result']) ?></td><td class="nowrap"><?= e(fmt_value(['type' => 'date'], $a['nextdate'])) ?> <?= $F('A', 'due', $a['due']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
