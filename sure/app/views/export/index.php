<div class="page-head"><div><h1>Export</h1><div class="sub">ดาวน์โหลดเป็นไฟล์ CSV (เปิดด้วย Excel / Google Sheets ได้) · คอลัมน์ตามสิทธิ์ของบัญชีนี้</div></div></div>
<div class="card flush">
  <table class="table">
    <thead><tr><th>ตาราง</th><th class="num">จำนวนแถว</th><th></th></tr></thead>
    <tbody>
    <?php $ds = ds(); foreach ($tables as $key => $d): ?>
      <tr><td><b><?= e($d['title']) ?></b><?= $d['mgr'] ? ' <span class="badge gray">Manager เท่านั้น</span>' : '' ?></td>
        <td class="num"><?= number_format(count($ds[$key])) ?></td>
        <td class="right"><a class="btn sm" href="<?= e(url('export.csv', ['t' => $key])) ?>">ดาวน์โหลด CSV</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
