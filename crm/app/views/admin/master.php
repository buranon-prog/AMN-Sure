<div class="page-head"><div><h1>ข้อมูลหลัก (Master data)</h1><div class="sub">รายการให้เลือกในฟอร์ม และ template ของการตรวจเครื่อง / เช็กลิสต์ / QC</div></div></div>
<div class="subnav">
  <?php foreach (MASTER_TYPES as $k => $v): ?><a class="<?= $type === $k ? 'active' : '' ?>" href="<?= e(url('admin.master', ['type' => $k])) ?>"><?= e($v) ?></a><?php endforeach; ?>
</div>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ลำดับ</th><th>รหัส</th><th>ชื่อ</th><?php if (in_array($type, ['INSPECTION_ITEM', 'CHECKLIST_ITEM'], true)): ?><th>หมวด</th><th>บังคับ</th><?php endif; ?><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $m = json_decode((string) $r['meta'], true) ?: []; ?>
      <tr>
        <td><?= (int) $r['sort'] ?></td><td class="mono"><?= e($r['code']) ?></td><td><?= e($r['label']) ?></td>
        <?php if ($type === 'INSPECTION_ITEM'): ?><td><?= e(label('insp_category', $m['category'] ?? '')) ?></td><td><?= !empty($m['mandatory']) ? '✅' : '—' ?></td><?php endif; ?>
        <?php if ($type === 'CHECKLIST_ITEM'): ?><td><?= e(label('checklist_section', $m['section'] ?? '')) ?></td><td><?= !empty($m['mandatory']) ? '✅' : '—' ?></td><?php endif; ?>
        <td><?= $r['active'] ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิด</span>' ?></td>
        <td class="right">
          <details><summary class="btn sm">แก้ไข</summary>
            <form method="post" action="<?= e(url('admin.master_save')) ?>" style="min-width:280px;margin-top:6px;text-align:left">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="type" value="<?= e($type) ?>">
              <div class="field"><label>ชื่อ</label><input type="text" name="label" value="<?= e($r['label']) ?>" required></div>
              <div class="field"><label>ลำดับ</label><input type="number" name="sort" value="<?= (int) $r['sort'] ?>"></div>
              <?php if ($type === 'INSPECTION_ITEM'): ?>
                <div class="field"><label>หมวด</label><select name="category"><?php foreach (labels('insp_category') as $k => $v): ?><option value="<?= e($k) ?>" <?= ($m['category'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
              <?php elseif ($type === 'CHECKLIST_ITEM'): ?>
                <div class="field"><label>หมวด</label><select name="section"><?php foreach (labels('checklist_section') as $k => $v): ?><option value="<?= e($k) ?>" <?= ($m['section'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
              <?php endif; ?>
              <?php if (in_array($type, ['INSPECTION_ITEM', 'CHECKLIST_ITEM'], true)): ?>
                <div class="field check"><label><input type="hidden" name="mandatory" value="0"><input type="checkbox" name="mandatory" value="1" <?= !empty($m['mandatory']) ? 'checked' : '' ?>> บังคับ</label></div>
              <?php endif; ?>
              <div class="field check"><label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" <?= $r['active'] ? 'checked' : '' ?>> ใช้งาน</label></div>
              <button class="btn sm primary" type="submit">บันทึก</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<form method="post" action="<?= e(url('admin.master_save')) ?>" class="card">
  <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>">
  <h2>เพิ่มรายการ: <?= e(MASTER_TYPES[$type]) ?></h2>
  <div class="form-grid three">
    <?= f_input('label', 'ชื่อ', null, ['required' => true]) ?>
    <?= f_input('code', 'รหัส (เว้นว่างได้)', null, ['hint' => 'A-Z 0-9 _']) ?>
    <?= f_input('sort', 'ลำดับ', (string) ((count($rows) + 1) * 10), ['type' => 'number']) ?>
    <?php if ($type === 'INSPECTION_ITEM'): ?><?= f_select('category', 'หมวด', labels('insp_category'), 'PARTS') ?><?php endif; ?>
    <?php if ($type === 'CHECKLIST_ITEM'): ?><?= f_select('section', 'หมวด', labels('checklist_section'), 'COMMERCIAL') ?><?php endif; ?>
    <?php if (in_array($type, ['INSPECTION_ITEM', 'CHECKLIST_ITEM'], true)): ?><?= f_checkbox('mandatory', 'บังคับ', false) ?><?php endif; ?>
  </div>
  <input type="hidden" name="active" value="1">
  <button class="btn primary" type="submit">เพิ่ม</button>
  <p class="hint">การเปลี่ยน template มีผลกับการตรวจ/เช็กลิสต์ที่สร้างใหม่เท่านั้น ของเดิมไม่เปลี่ยน</p>
</form>
