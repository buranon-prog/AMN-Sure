<div class="page-head">
  <div><h1>ลีด / ติดตาม</h1><div class="sub">ดีลซื้อ (ลูกค้าขายเครื่องให้เรา) และดีลขาย (ลูกค้าซื้อเครื่องจากเรา) ที่ต้องติดตาม</div></div>
  <div class="actions"><?php if (can('lead.edit')): ?>
    <a class="btn primary" href="<?= e(url('leads.new', ['type' => 'SELLER'])) ?>">+ ลีดผู้ขาย</a>
    <a class="btn primary" href="<?= e(url('leads.new', ['type' => 'BUYER'])) ?>">+ ลีดผู้ซื้อ</a>
  <?php endif; ?></div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="leads">
  <div class="field"><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="เลขดีล, ลูกค้า, ยี่ห้อ/รุ่น"></div>
  <div class="field"><select name="kind"><option value="">ทั้งดีลซื้อและดีลขาย</option><option value="DO" <?= $f['kind'] === 'DO' ? 'selected' : '' ?>>ดีลซื้อ (ผู้ขาย)</option><option value="SO" <?= $f['kind'] === 'SO' ? 'selected' : '' ?>>ดีลขาย (ผู้ซื้อ)</option></select></div>
  <div class="field"><select name="owner"><option value="">ทุกคน</option><option value="me" <?= $f['owner'] === 'me' ? 'selected' : '' ?>>ของฉัน</option>
    <?php foreach (user_options() as $id => $name): ?><option value="<?= e($id) ?>" <?= $f['owner'] === $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
  <div class="field"><select name="state"><option value="active" <?= $f['state'] === 'active' ? 'selected' : '' ?>>กำลังดำเนินการ</option><option value="closed" <?= $f['state'] === 'closed' ? 'selected' : '' ?>>ปิดแล้ว</option><option value="all" <?= $f['state'] === 'all' ? 'selected' : '' ?>>ทั้งหมด</option></select></div>
  <label class="field check"><label><input type="checkbox" name="overdue" value="1" <?= $f['overdue'] ? 'checked' : '' ?>> เฉพาะ OVERDUE</label></label>
  <button class="btn" type="submit">กรอง</button>
</form>
<div class="card flush">
  <?php partial('followup_rows', ['rows' => $page['rows'], 'showOwner' => true]); ?>
</div>
<?= pager($page) ?>
