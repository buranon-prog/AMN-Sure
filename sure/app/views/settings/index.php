<?php
$lists = option_lists();
// รายชื่อพนักงานขึ้นก่อน (ใช้ใน dropdown Sales / วิศวกร / ผู้บันทึก)
$order = array_merge(['staff', 'staffrole'], array_values(array_diff(array_keys($lists), ['staff', 'staffrole'])));
$card = function (string $key) use ($lists, $rows, $canEdit) {
    ob_start(); ?>
  <div class="card flush" id="list-<?= e($key) ?>">
    <div class="card-head"><h2><?= e($lists[$key]) ?></h2><span class="ref"><?= e($key) ?></span></div>
    <table class="table options">
      <?php if (!$canEdit): ?><thead><tr><th class="num">ลำดับ</th><th>ค่า</th></tr></thead><?php endif; ?>
      <tbody>
      <?php foreach ($rows[$key] ?? [] as $o): $sys = (int) $o['is_system'] === 1; ?>
        <tr>
          <?php if ($canEdit): ?>
            <td colspan="3">
              <form method="post" action="<?= e(url('settings.update')) ?>" class="opt-row">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <input type="number" name="sort" value="<?= (int) $o['sort'] ?>" class="sort" aria-label="ลำดับ">
                <input type="text" name="value" value="<?= e($o['value']) ?>" maxlength="255" aria-label="ค่า"<?= $sys ? ' readonly title="ค่าระบบ — เปลี่ยนชื่อไม่ได้"' : '' ?>>
                <?php if ($sys): ?><span class="badge gray" title="คำที่ logic ใช้ — ลบ/เปลี่ยนชื่อไม่ได้">🔒 ค่าระบบ</span><?php endif; ?>
                <button class="btn sm" type="submit">บันทึก</button>
                <?php if (!$sys): ?><button class="btn sm danger" type="submit" formaction="<?= e(url('settings.delete')) ?>" data-confirm-click="ลบ &quot;<?= e($o['value']) ?>&quot; ออกจากรายการ? (ข้อมูลเดิมที่ใช้ค่านี้ยังอยู่)">ลบ</button><?php endif; ?>
              </form>
            </td>
          <?php else: ?>
            <td class="num"><?= (int) $o['sort'] ?></td>
            <td><?= e($o['value']) ?><?= $sys ? ' <span class="badge gray" title="คำที่ logic ใช้ — ลบ/เปลี่ยนชื่อไม่ได้">🔒 ค่าระบบ</span>' : '' ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($canEdit): ?>
      <form method="post" action="<?= e(url('settings.add')) ?>" class="opt-row add pad">
        <?= csrf_field() ?><input type="hidden" name="list" value="<?= e($key) ?>">
        <input type="number" name="sort" placeholder="ลำดับ" class="sort" aria-label="ลำดับ">
        <input type="text" name="value" placeholder="เพิ่มค่าใหม่…" maxlength="255" required aria-label="ค่าใหม่">
        <button class="btn sm primary" type="submit">+ เพิ่ม</button>
      </form>
    <?php endif; ?>
  </div>
<?php return ob_get_clean(); };
?>
<div class="page-head">
  <div><h1>รายการตัวเลือก &amp; ค่าตั้งต้น</h1>
    <div class="sub"><?= $canEdit ? 'แก้ไขได้ (Manager) · ค่าที่มี 🔒 คือคำที่ระบบใช้คำนวณ ลบ/เปลี่ยนชื่อไม่ได้' : 'ดูอย่างเดียว — แก้ไขได้เฉพาะ Manager' ?></div></div>
</div>

<div class="grid-3">
  <div class="card">
    <h2>อัตรา VAT</h2>
    <?php if ($canEdit): ?>
      <form method="post" action="<?= e(url('settings.vat')) ?>" class="opt-row">
        <?= csrf_field() ?>
        <input type="text" name="vat" value="<?= e(setting('vat_rate', '7')) ?>" inputmode="decimal" class="sort" aria-label="อัตรา VAT"> <b>%</b>
        <button class="btn sm primary" type="submit">บันทึก</button>
      </form>
      <p class="hint">ใช้คำนวณ VAT = ROUND(ราคาขาย × อัตรา VAT, 2) ของทุกรายการขาย</p>
    <?php else: ?>
      <div class="n big"><?= e(setting('vat_rate', '7')) ?>%</div>
    <?php endif; ?>
  </div>
  <div class="span-2"><?= $card('staff') ?></div>
</div>

<div class="grid-2">
  <?php foreach ($order as $key): if ($key === 'staff') continue; ?>
    <?= $card($key) ?>
  <?php endforeach; ?>
</div>

<details class="card">
  <summary><b>จังหวัด → ภาค (77 จังหวัด — ค่าคงที่ ใช้คำนวณ "ภาค")</b></summary>
  <div class="grid-3 mt">
    <?php $byRegion = [];
    foreach (provinces() as $p => $rg) $byRegion[$rg][] = $p;
    foreach (regions() as $rg): ?>
      <div><h3><?= e($rg) ?> <span class="muted">(<?= count($byRegion[$rg]) ?>)</span></h3><p><?= e(implode(', ', $byRegion[$rg])) ?></p></div>
    <?php endforeach; ?>
  </div>
</details>
