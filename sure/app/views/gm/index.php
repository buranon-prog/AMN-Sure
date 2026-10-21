<?php
$count = function ($fn) use ($users) { return count(array_filter($users, $fn)); };
$presenceBadge = function ($p) {
    if ($p === 'online') return '<span class="presence on">ออนไลน์</span>';
    if ($p === 'idle') return '<span class="presence idle">เปิดค้างไว้</span>';
    return '<span class="presence off">ออฟไลน์</span>';
};
$roleBadge = function ($r) {
    if ((int) $r['is_gm'] === 1) return '<span class="badge red">GM</span>';
    return $r['role'] === ROLE_MANAGER ? '<span class="badge purple">Manager</span>' : '<span class="badge blue">พนักงาน</span>';
};
$mySid = $_SESSION['sid'] ?? '';
?>
<div class="page-head" data-autorefresh="30">
  <div><h1>ผู้ใช้ออนไลน์</h1><div class="sub">GM เท่านั้น · ข้อมูล ณ <?= e(date('d/m/Y H:i:s')) ?> · รีเฟรชเองทุก 30 วินาที · "ออนไลน์" = ใช้งานภายใน <?= ONLINE_MINUTES ?> นาที</div></div>
  <div class="actions"><a class="btn" href="<?= e(url('gm')) ?>">รีเฟรช</a><a class="btn" href="<?= e(url('users')) ?>">บัญชีผู้ใช้</a></div>
</div>

<div class="grid-4 kpis">
  <div class="stat"><div class="l">ออนไลน์ตอนนี้</div><div class="n"><?= $count(function ($u) { return $u['presence'] === 'online'; }) ?></div><div class="s">คน</div></div>
  <div class="stat"><div class="l">เปิดค้างไว้ (ไม่ได้ใช้งาน)</div><div class="n"><?= $count(function ($u) { return $u['presence'] === 'idle'; }) ?></div><div class="s">ระบบให้ออกเองเมื่อไม่ได้ใช้งาน <?= (int) (session_idle_minutes() / 60) ?> ชั่วโมง</div></div>
  <div class="stat"><div class="l">บัญชีที่ใช้งานได้</div><div class="n"><?= $count(function ($u) { return (int) $u['active'] === 1; }) ?></div><div class="s">จากทั้งหมด <?= count($users) ?> บัญชี</div></div>
  <div class="stat<?= $count(function ($u) { return $u['locked']; }) ? ' alert' : '' ?>"><div class="l">ล็อกจากใส่รหัสผิด</div><div class="n"><?= $count(function ($u) { return $u['locked']; }) ?></div><div class="s">ปลดล็อกเองได้หลัง 15 นาที</div></div>
</div>

<div class="card flush">
  <div class="card-head"><h2>ทุกบัญชี</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ออนไลน์</th><th>ชื่อ</th><th>ประเภท</th><th>สถานะบัญชี</th><th class="num">เครื่องที่เปิดอยู่</th><th>ใช้งานล่าสุด</th><th>เข้าระบบล่าสุด</th><th>คุมบัญชี</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $self = $u['id'] === current_user_id(); ?>
      <tr>
        <td><?= $presenceBadge($u['presence']) ?></td>
        <td><b><?= e($u['name']) ?></b><?= $self ? ' <span class="muted">(คุณ)</span>' : '' ?><div class="muted small"><?= e($u['email'] ?? '—') ?></div></td>
        <td><?= $roleBadge($u) ?></td>
        <td><?= (int) $u['active'] === 1 ? '<span class="badge green">ใช้งาน</span>' : '<span class="badge gray">ปิดแล้ว</span>' ?><?= (int) $u['active'] === 1 && $u['activated_at'] === null ? ' <span class="badge amber">รอเปิดใช้งาน</span>' : '' ?><?= $u['locked'] ? ' <span class="badge red">ล็อกอยู่</span>' : '' ?></td>
        <td class="num"><?= (int) $u['devices'] ?></td>
        <td class="nowrap" title="<?= e(dt($u['last_seen_at'])) ?>"><?= e(ago($u['last_seen_at'])) ?></td>
        <td class="nowrap"><?= e(dt($u['last_login_at'])) ?></td>
        <td><div class="gm-actions">
          <?php if ($self): ?><span class="muted small">—</span><?php else: ?>
            <?php if ((int) $u['devices'] > 0): ?>
              <form method="post" action="<?= e(url('gm.logout_all')) ?>" data-confirm="ให้ <?= e($u['name']) ?> ออกจากระบบทุกเครื่อง?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button class="btn sm warn" type="submit">ออกจากระบบทุกเครื่อง</button></form>
            <?php endif; ?>
            <?php if ($u['locked']): ?>
              <form method="post" action="<?= e(url('gm.unlock')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button class="btn sm" type="submit">ปลดล็อก</button></form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('gm.active')) ?>"<?= (int) $u['active'] === 1 ? ' data-confirm="ปิดบัญชี ' . e($u['name']) . '? ผู้ใช้จะออกจากระบบทุกเครื่องทันที และเข้าสู่ระบบไม่ได้จนกว่าจะเปิดบัญชี"' : '' ?>><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>">
              <?php if ((int) $u['active'] === 1): ?><input type="hidden" name="active" value="0"><button class="btn sm danger" type="submit">ปิดบัญชี</button>
              <?php else: ?><input type="hidden" name="active" value="1"><button class="btn sm" type="submit">เปิดบัญชี</button><?php endif; ?>
            </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card flush">
  <div class="card-head"><h2>เครื่องที่เข้าสู่ระบบอยู่ (<?= count($sessions) ?>)</h2></div>
  <?php if (!$sessions): ?><p class="muted pad">ไม่มีใครเข้าสู่ระบบอยู่</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ออนไลน์</th><th>ชื่อ</th><th>อุปกรณ์</th><th>IP</th><th>เข้าสู่ระบบเมื่อ</th><th>ใช้งานล่าสุด</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sessions as $s): ?>
      <tr>
        <td><?= $presenceBadge($s['presence']) ?></td>
        <td><b><?= e($s['name']) ?></b> <?= $roleBadge($s) ?><div class="muted small"><?= e($s['email'] ?? '—') ?></div></td>
        <td><?= e(ua_label($s['user_agent'])) ?></td>
        <td class="nowrap"><?= e($s['ip']) ?></td>
        <td class="nowrap"><?= e(dt($s['created_at'])) ?></td>
        <td class="nowrap" title="<?= e(dt($s['last_seen_at'])) ?>"><?= e(ago($s['last_seen_at'])) ?></td>
        <td><?php if ($s['id'] === $mySid): ?><span class="muted small">เครื่องนี้</span><?php else: ?>
          <form method="post" action="<?= e(url('gm.kick')) ?>" data-confirm="ให้ <?= e($s['name']) ?> ออกจากระบบบนเครื่องนี้?"><?= csrf_field() ?><input type="hidden" name="sid" value="<?= e($s['id']) ?>"><button class="btn sm warn" type="submit">ให้ออกจากระบบ</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="card flush">
  <div class="card-head"><h2>ประวัติการเข้าสู่ระบบล่าสุด</h2></div>
  <?php if (!$activity): ?><p class="muted pad">ยังไม่มีประวัติ</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>เวลา</th><th>ชื่อ / อีเมล</th><th>เหตุการณ์</th><th>รายละเอียด</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($activity as $a): ?>
      <tr>
        <td class="nowrap"><?= e(date('d/m/Y H:i:s', (int) strtotime($a['at']))) ?></td>
        <td><?= $a['name'] ? '<b>' . e($a['name']) . '</b> ' : '' ?><span class="muted small"><?= e($a['email']) ?></span></td>
        <td><?php if ($a['kind'] === 'login'): ?><span class="badge green">เข้าสู่ระบบ</span><?php elseif ($a['kind'] === 'end'): ?><span class="badge gray">ออกจากระบบ</span><?php else: ?><span class="badge red">เข้าไม่สำเร็จ</span><?php endif; ?></td>
        <td><?= e($a['detail']) ?></td>
        <td class="nowrap"><?= e($a['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
