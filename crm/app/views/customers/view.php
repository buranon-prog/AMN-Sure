<?php $o = $d['org']; ?>
<div class="crumbs"><a href="<?= e(url('customers')) ?>">ลูกค้า</a> › <?= e($o['name']) ?></div>
<div class="page-head">
  <div>
    <h1><?= e($o['name']) ?></h1>
    <div class="sub"><span class="ref"><?= e($o['ref_no']) ?></span> <?= badge('org_type', $o['type']) ?>
      <?php if ($o['archived_at']): ?><span class="badge red">เก็บเข้าคลัง: <?= e($o['archive_reason']) ?></span><?php endif; ?>
      <span>ผู้ดูแล: <?= e($d['owner_name']) ?></span></div>
  </div>
  <div class="actions">
    <?php if (can('lead.edit') && !$o['archived_at']): ?>
      <a class="btn" href="<?= e(url('leads.new', ['type' => 'SELLER', 'org' => $o['id']])) ?>">+ ลีดผู้ขาย</a>
      <a class="btn" href="<?= e(url('leads.new', ['type' => 'BUYER', 'org' => $o['id']])) ?>">+ ลีดผู้ซื้อ</a>
    <?php endif; ?>
    <?php if (can('customer.edit')): ?><a class="btn primary" href="<?= e(url('customers.edit', ['id' => $o['id']])) ?>">แก้ไข</a><?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>ข้อมูลลูกค้า</h2>
    <dl class="kv">
      <dt>โทรศัพท์</dt><dd><?= e($o['phone'] ?? '—') ?></dd>
      <dt>LINE</dt><dd><?= e($o['line_id'] ?? '—') ?></dd>
      <dt>อีเมล</dt><dd><?= e($o['email'] ?? '—') ?></dd>
      <dt>ที่อยู่</dt><dd class="pre"><?= e(trim(($o['address'] ?? '') . ' ' . ($o['province'] ?? '')) ?: '—') ?></dd>
      <dt>เลขผู้เสียภาษี</dt><dd><?= e($o['tax_id'] ?? '—') ?><?= $o['branch'] ? ' · สาขา ' . e($o['branch']) : '' ?></dd>
      <?php if ($o['notes']): ?><dt>หมายเหตุ</dt><dd class="pre"><?= e($o['notes']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card highlight">
    <h2>สถานะการติดตาม</h2>
    <dl class="kv">
      <dt>กิจกรรมล่าสุด</dt><dd><?= e(dt($d['last_activity'])) ?></dd>
      <dt>Next action</dt><dd><?php if ($d['next']): ?><a href="<?= e($d['next']['url']) ?>"><span class="ref"><?= e($d['next']['ref']) ?></span></a> <?= e($d['next']['action'] ?? '—') ?><?php else: ?>—<?php endif; ?></dd>
      <dt>วันติดตามถัดไป</dt><dd><?= $d['next'] ? followup_badge($d['next']['date']) : '—' ?></dd>
      <dt>เครื่องที่ลูกค้ามี</dt><dd><?= count($d['devices_owned']) ?> เครื่อง</dd>
      <dt>ดีลซื้อ / ดีลขาย</dt><dd><?= count($d['seller_opps']) ?> / <?= count($d['buyer_opps']) ?></dd>
    </dl>
  </div>
</div>

<div class="subnav no-print">
  <a href="#contacts">ผู้ติดต่อ</a><a href="#deals">ลีดและดีล</a><a href="#devices">เครื่อง</a><a href="#sales">ใบเสนอราคา/ธุรกรรม</a><a href="#service">บริการ</a><a href="#activities">Timeline</a><a href="#documents">เอกสาร</a>
</div>

<div class="card flush" id="contacts">
  <div class="card-head"><h2>ผู้ติดต่อ</h2><?php if (can('customer.edit')): ?><a class="btn sm" href="<?= e(url('contacts.new', ['org' => $o['id']])) ?>">+ เพิ่มผู้ติดต่อ</a><?php endif; ?></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ชื่อ</th><th>ตำแหน่ง</th><th>โทร</th><th>LINE</th><th>อีเมล</th></tr></thead>
    <tbody>
    <?php if (!$d['contacts']): ?><tr><td colspan="5" class="empty">ยังไม่มีผู้ติดต่อ</td></tr><?php endif; ?>
    <?php foreach ($d['contacts'] as $c): ?>
      <tr><td><a href="<?= e(url('contacts.view', ['id' => $c['id']])) ?>"><?= e($c['name']) ?></a><?= $c['is_primary'] ? ' <span class="badge blue">หลัก</span>' : '' ?></td>
        <td><?= e($c['position'] ?? '—') ?></td><td><?= e($c['phone'] ?? '—') ?></td><td><?= e($c['line_id'] ?? '—') ?></td><td><?= e($c['email'] ?? '—') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div id="deals">
<?php if (can('lead.view')): ?>
<div class="card flush">
  <div class="card-head"><h2>ประวัติการขายเครื่องให้ AMN Sure (ดีลซื้อ)</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>เครื่อง</th><th>ผู้รับผิดชอบ</th><th>วันติดตาม</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$d['seller_opps']): ?><tr><td colspan="5" class="empty">ไม่มี</td></tr><?php endif; ?>
    <?php foreach ($d['seller_opps'] as $x): $active = !in_array($x['status'], DO_CLOSED, true); ?>
      <tr class="<?= $active && $x['next_follow_up_date'] && $x['next_follow_up_date'] < today() ? 'overdue' : '' ?>">
        <td><a href="<?= e(url('acq.view', ['id' => $x['id']])) ?>"><span class="ref"><?= e($x['ref_no']) ?></span></a><div class="muted"><?= e(d($x['created_at'])) ?></div></td>
        <td><a href="<?= e(url('devices.view', ['id' => $x['device_id']])) ?>"><?= e($x['brand'] . ' ' . $x['model']) ?></a><div class="muted"><?= e($x['device_ref']) ?><?= $x['serial_number'] ? ' · S/N ' . e($x['serial_number']) : '' ?></div></td>
        <td><?= e($x['owner_name']) ?></td>
        <td class="nowrap"><?= followup_badge($x['next_follow_up_date'], $active) ?></td>
        <td><?= badge('status', $x['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php if (can('sales.view')): ?>
<div class="card flush">
  <div class="card-head"><h2>ดีลที่ลูกค้าซื้อจาก AMN Sure (ดีลขาย)</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ดีล</th><th>ความต้องการ</th><th>ผู้รับผิดชอบ</th><th>วันติดตาม</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$d['buyer_opps']): ?><tr><td colspan="5" class="empty">ไม่มี</td></tr><?php endif; ?>
    <?php foreach ($d['buyer_opps'] as $x): $active = !in_array($x['status'], SO_CLOSED, true); ?>
      <tr class="<?= $active && $x['next_follow_up_date'] && $x['next_follow_up_date'] < today() ? 'overdue' : '' ?>">
        <td><a href="<?= e(url('sales.view', ['id' => $x['id']])) ?>"><span class="ref"><?= e($x['ref_no']) ?></span></a><div class="muted"><?= e(d($x['created_at'])) ?></div></td>
        <td><?= e(trim(($x['req_brand'] ?? '') . ' ' . ($x['req_model'] ?? '')) ?: $x['interested_device']) ?><?= $x['budget_max'] !== null ? '<div class="muted">งบ ' . money0($x['budget_max']) . '</div>' : '' ?></td>
        <td><?= e($x['owner_name']) ?></td>
        <td class="nowrap"><?= followup_badge($x['next_follow_up_date'], $active) ?></td>
        <td><?= badge('status', $x['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php if (can('lead.view') && $d['leads']): ?>
<div class="card flush">
  <div class="card-head"><h2>ลีดทั้งหมด</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ลีด</th><th>ประเภท</th><th>แหล่งที่มา</th><th>ผู้รับผิดชอบ</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php $sources = master_options('LEAD_SOURCE'); foreach ($d['leads'] as $l): ?>
      <tr><td><a href="<?= e(url('leads.view', ['id' => $l['id']])) ?>"><span class="ref"><?= e($l['ref_no']) ?></span></a> <span class="muted"><?= e(d($l['created_at'])) ?></span></td>
        <td><?= e(label('lead_type', $l['type'])) ?></td><td><?= e($sources[$l['source']] ?? $l['source'] ?? '—') ?></td><td><?= e($l['owner_name']) ?></td><td><?= badge('status', $l['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
</div>

<div class="grid-2" id="devices">
  <div class="card flush">
    <div class="card-head"><h2>เครื่องที่ลูกค้ามีอยู่</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เครื่อง</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['devices_owned']): ?><tr><td colspan="2" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['devices_owned'] as $dv): ?>
        <tr><td><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><?= e($dv['brand'] . ' ' . $dv['model']) ?></a><div class="muted"><?= e($dv['ref_no']) ?><?= $dv['serial_number'] ? ' · S/N ' . e($dv['serial_number']) : '' ?></div></td>
          <td><?= badge('commercial', $dv['commercial_status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card flush">
    <div class="card-head"><h2>เครื่องที่เคยเป็นของลูกค้า</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เครื่อง</th><th>ช่วงเวลา</th></tr></thead>
      <tbody>
      <?php if (!$d['devices_previous']): ?><tr><td colspan="2" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['devices_previous'] as $dv): ?>
        <tr><td><a href="<?= e(url('devices.view', ['id' => $dv['id']])) ?>"><?= e($dv['brand'] . ' ' . $dv['model']) ?></a><div class="muted"><?= e($dv['ref_no']) ?></div></td>
          <td class="nowrap"><?= e(d($dv['from_date'])) ?> – <?= e(d($dv['to_date'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div id="sales">
<?php if (can('quotation.view') || can('transaction.view')): ?>
<div class="grid-2">
  <?php if (can('quotation.view')): ?>
  <div class="card flush">
    <div class="card-head"><h2>ใบเสนอราคา</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เลขที่</th><th class="num">ยอดรวม</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['quotations']): ?><tr><td colspan="3" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['quotations'] as $qt): ?>
        <tr><td><a href="<?= e(url('quotations.view', ['id' => $qt['id']])) ?>"><span class="ref"><?= e($qt['ref_no']) ?></span></a> <span class="muted">ฉบับที่ <?= (int) $qt['current_version_no'] ?></span></td>
          <td class="num"><?= money($qt['total']) ?></td><td><?= badge('status', $qt['version_status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>
  <?php if (can('transaction.view')): ?>
  <div class="card flush">
    <div class="card-head"><h2>ธุรกรรมขาย</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>เลขที่</th><th>วันที่</th><th class="num">มูลค่า</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$d['transactions']): ?><tr><td colspan="4" class="empty">ไม่มี</td></tr><?php endif; ?>
      <?php foreach ($d['transactions'] as $t): ?>
        <tr><td><a href="<?= e(url('trx.view', ['id' => $t['id']])) ?>"><span class="ref"><?= e($t['ref_no']) ?></span></a></td><td><?= e(d($t['won_at'])) ?></td>
          <td class="num"><?= money($t['total_price']) ?></td><td><?= badge('status', $t['status']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($d['acquisitions']): ?>
<div class="card flush">
  <div class="card-head"><h2>เครื่องที่ AMN Sure ซื้อจากลูกค้ารายนี้</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เลขที่</th><th>เครื่อง</th><th>วันที่ซื้อ</th><?php if (can_any('negotiation.view', 'acquisition.create')): ?><th class="num">ราคาซื้อ</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($d['acquisitions'] as $a): ?>
      <tr><td><a href="<?= e(url('acq.view', ['id' => $a['device_opportunity_id']])) ?>"><span class="ref"><?= e($a['ref_no']) ?></span></a></td>
        <td><a href="<?= e(url('devices.view', ['id' => $a['device_id']])) ?>"><?= e($a['brand'] . ' ' . $a['model']) ?></a></td>
        <td><?= e(d($a['purchase_date'])) ?></td><?php if (can_any('negotiation.view', 'acquisition.create')): ?><td class="num"><?= money($a['purchase_price']) ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
</div>

<div id="service">
<?php if (can('service_case.view')): ?>
<div class="card flush">
  <div class="card-head"><h2>ประวัติบริการหลังการขาย</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เคส</th><th>เครื่อง</th><th>ปัญหา</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php if (!$d['service_cases']): ?><tr><td colspan="4" class="empty">ไม่มี</td></tr><?php endif; ?>
    <?php foreach ($d['service_cases'] as $sc): ?>
      <tr><td><a href="<?= e(url('cases.view', ['id' => $sc['id']])) ?>"><span class="ref"><?= e($sc['ref_no']) ?></span></a> <span class="muted"><?= e(d($sc['reported_at'])) ?></span></td>
        <td><?= e($sc['brand'] . ' ' . $sc['model']) ?></td><td><?= e(mb_substr($sc['issue'], 0, 120)) ?></td><td><?= badge('status', $sc['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
</div>

<?php partial('activity', ['parentType' => 'ORGANIZATION', 'parentId' => $o['id'], 'activities' => $d['activities']]); ?>
<?php partial('documents', ['parentType' => 'ORGANIZATION', 'parentId' => $o['id'], 'documents' => $d['documents'], 'defaultCategory' => 'OTHER']); ?>
