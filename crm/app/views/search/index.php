<div class="page-head"><div><h1>ค้นหา</h1><div class="sub">คลินิก ผู้ติดต่อ เบอร์โทร รุ่น Serial Device ID เลขดีล ใบเสนอราคา สัญญา ธุรกรรม</div></div></div>
<form class="filters" method="get"><input type="hidden" name="r" value="search">
  <div class="field" style="flex:1"><input type="search" name="q" value="<?= e($q) ?>" placeholder="พิมพ์อย่างน้อย 2 ตัวอักษร" autofocus></div><button class="btn primary" type="submit">ค้นหา</button></form>
<?php if ($q !== ''): ?>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ประเภท</th><th>ผลลัพธ์</th><th>รายละเอียด</th></tr></thead>
    <tbody>
    <?php if (!$results): ?><tr><td colspan="3" class="empty">ไม่พบ "<?= e($q) ?>"</td></tr><?php endif; ?>
    <?php foreach ($results as $r): ?>
      <tr><td><span class="tag"><?= e($r['type']) ?></span></td><td><a href="<?= e($r['url']) ?>"><b><?= e($r['title']) ?></b></a></td><td class="muted"><?= e($r['sub']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
