<div class="page-head">
  <div><h1>ผู้ใช้และสิทธิ์</h1><div class="sub">พนักงานแต่ละคนมีชื่อผู้ใช้/รหัสผ่านของตัวเอง สิ่งที่เห็นและทำได้ขึ้นกับ role</div></div>
  <div class="actions"><a class="btn primary" href="<?= e(url('admin.user_new')) ?>">+ เพิ่มผู้ใช้</a></div>
</div>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ชื่อ</th><th>ชื่อผู้ใช้</th><th>อีเมล</th><th>Role</th><th>เข้าระบบล่าสุด</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr><td><a href="<?= e(url('admin.user', ['id' => $u['id']])) ?>"><b><?= e($u['name']) ?></b></a></td><td class="mono"><?= e($u['username']) ?></td><td><?= e($u['email'] ?? '—') ?></td>
        <td><?php foreach (array_filter(explode(',', (string) $u['role_codes'])) as $rc): ?><span class="tag"><?= e(role_name($rc)) ?></span><?php endforeach; ?></td>
        <td class="nowrap"><?= e(dt($u['last_login_at'])) ?></td>
        <td><?= $u['active'] ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิดแล้ว</span>' ?><?= $u['must_change_password'] ? ' <span class="badge amber">รอเปลี่ยนรหัส</span>' : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2>สรุปสิทธิ์ของแต่ละ role</h2>
  <ul class="list-plain">
    <li><b><?= e(role_name('GM')) ?></b> — เห็นและทำได้ทุกอย่าง อนุมัติการซื้อ อนุมัติราคาต่ำกว่าขั้นต่ำ ยกเลิกธุรกรรม จัดการผู้ใช้</li>
    <li><b><?= e(role_name('MANAGEMENT')) ?></b> — ดูได้ทุกอย่างรวมข้อมูลการเงินและ audit log แต่แก้ไขไม่ได้</li>
    <li><b><?= e(role_name('SALES_DIRECTOR')) ?></b> — ความต้องการผู้ซื้อ จับคู่เครื่อง Valuation อนุมัติใบเสนอราคา เห็นข้อมูลการเงิน</li>
    <li><b><?= e(role_name('SALES_COORDINATOR')) ?></b> — ลีด ลูกค้า ขอตรวจเครื่อง บันทึกการซื้อ ใบเสนอราคา มัดจำ สัญญา (ไม่เห็นต้นทุน/GP)</li>
    <li><b><?= e(role_name('SALES_EXECUTIVE')) ?></b> — ลีด เจรจาราคา ส่งขออนุมัติซื้อ ใบเสนอราคา (เห็นเฉพาะราคาแนะนำให้ซื้อ)</li>
    <li><b><?= e(role_name('SERVICE_DIRECTOR')) ?></b> — งานช่างทั้งหมด + ทำ Cost Sheet เห็นข้อมูลการเงิน</li>
    <li><b><?= e(role_name('SERVICE_ENGINEER')) ?></b> — ตรวจเครื่อง ประวัติซ่อม/MA QC ส่งมอบ ติดตั้ง ใบงาน</li>
    <li><b><?= e(role_name('MARKETING')) ?></b> — ดูลูกค้า/เครื่อง/ดีล และทำเช็กลิสต์หมวดการตลาด</li>
    <li><b><?= e(role_name('ADMIN')) ?></b> — จัดการผู้ใช้ ข้อมูลหลัก ตั้งค่า (ไม่เห็นข้อมูลการเงินและดีล)</li>
  </ul>
  <p class="muted">คนเดียวมีได้หลาย role — สิทธิ์รวมกัน</p>
</div>
