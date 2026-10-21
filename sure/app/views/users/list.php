<div class="page-head">
  <div><h1>บัญชีผู้ใช้</h1><div class="sub">แต่ละคนมีชื่อผู้ใช้/รหัสผ่านของตัวเอง · Manager เห็นและทำได้ทุกอย่าง · พนักงานเห็นตามสิทธิ์ SURE ที่กำหนด</div></div>
  <div class="actions"><a class="btn primary" href="<?= e(url('users.new')) ?>">+ เพิ่มผู้ใช้</a></div>
</div>
<div class="card flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ชื่อ</th><th>ชื่อผู้ใช้</th><th>อีเมล</th><th>ประเภทบัญชี</th><th>สิทธิ์ SURE</th><th>เข้าระบบล่าสุด</th><th>สถานะ</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr><td><a href="<?= e(url('users.edit', ['id' => $u['id']])) ?>"><b><?= e($u['name']) ?></b></a></td><td class="mono"><?= e($u['username']) ?></td><td><?= e($u['email'] ?? '—') ?></td>
        <td><?= $u['role'] === ROLE_MANAGER ? '<span class="badge purple">Manager</span>' : '<span class="badge blue">พนักงาน</span>' ?></td>
        <td><?php if ($u['role'] === ROLE_MANAGER): ?>ทั้งหมด<?php else: ?><?= $u['can_view'] ? 'ดู' : '<span class="muted">ไม่มีสิทธิ์</span>' ?><?= $u['can_view'] && $u['can_edit'] ? ' + เพิ่ม/แก้ไข' : '' ?><?php endif; ?></td>
        <td class="nowrap"><?= e(dt($u['last_login_at'])) ?></td>
        <td><?= $u['active'] ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิดแล้ว</span>' ?><?= $u['must_change_password'] ? ' <span class="badge amber">รอเปลี่ยนรหัส</span>' : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2>สิทธิ์แต่ละประเภท</h2>
  <ul class="list-plain">
    <li><b>Manager</b> — เห็นและทำได้ทุกอย่าง: ต้นทุนเครื่อง, กำไรรายการขาย, สรุป, คอลัมน์สรุปลูกค้า, export ทุกตาราง, แก้รายการตัวเลือก + VAT, ลบแถว, จัดการบัญชีผู้ใช้</li>
    <li><b>พนักงาน + สิทธิ์ดู</b> — ดูตาราง ลูกค้า สาขา ติดตามลูกค้า เครื่อง การขาย รับชำระเงิน บริการ และหน้าค้นหา · export ตารางเหล่านี้ (ไม่มีต้นทุน/กำไร/GP)</li>
    <li><b>พนักงาน + สิทธิ์เพิ่ม/แก้ไข</b> — เพิ่ม/แก้ไขตารางข้างต้นได้ · ลบแถวไม่ได้ (ใช้การเปลี่ยนสถานะแทน)</li>
    <li><b>พนักงานที่ไม่มีสิทธิ์ SURE</b> — เข้าสู่ระบบได้แต่ไม่เห็นเมนูและเปิดหน้าใดไม่ได้</li>
  </ul>
</div>
