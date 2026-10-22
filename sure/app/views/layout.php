<?php
$u = current_user();
$r = (string) ($_GET['r'] ?? 'home');
$mod = explode('.', $r)[0];
$view = can_view_sure();
$mgr = is_manager();
// [route, ชื่อเมนู, แสดงเมื่อ]
$nav = [
    'SURE' => [
        ['search', 'ค้นหา', $view],
        ['customers', 'ลูกค้า', $view],
        ['branches', 'สาขา', $view],
        ['followups', 'ติดตามลูกค้า', $view],
        ['devices', 'เครื่อง', $view],
        ['sales', 'การขาย', $view],
        ['payments', 'รับชำระเงิน', $view],
        ['services', 'บริการ', $view],
    ],
    'Manager' => [
        ['dashboard', 'สรุป', $mgr],
        ['profit', 'กำไรรายการขาย', $mgr],
        ['costs', 'ต้นทุนเครื่อง', $mgr],
    ],
    'ระบบ' => [
        ['settings', 'รายการตัวเลือก & ค่าตั้งต้น', $view],
        ['export', 'Export', $view],
        ['users', 'บัญชีผู้ใช้', $mgr],
        ['gm', 'ผู้ใช้ออนไลน์ (GM)', is_gm()],
    ],
];
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <a href="<?= e(url('home')) ?>"><span class="logo">S</span><span><?= e(APP_NAME) ?><small>ลูกค้า · เครื่อง · การขาย · บริการ</small></span></a>
    </div>
    <nav>
      <?php foreach ($nav as $group => $items):
          $visible = array_filter($items, function ($it) { return $it[2]; });
          if (!$visible) continue; ?>
        <div class="nav-group"><?= e($group) ?></div>
        <?php foreach ($visible as $it): ?>
          <a href="<?= e(url($it[0])) ?>" class="<?= $mod === $it[0] ? 'active' : '' ?>"><?= e($it[1]) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </aside>
  <label for="nav-toggle" class="nav-backdrop" aria-hidden="true"></label>
  <div class="main">
    <header class="topbar">
      <label for="nav-toggle" class="hamburger" title="เมนู">☰</label>
      <?php if ($view): ?>
      <form class="topsearch" method="get" action="">
        <input type="hidden" name="r" value="search">
        <input type="search" name="code" value="" placeholder="ค้นหา: รหัสเครื่อง (SURE26001) หรือ รหัสลูกค้า (SURE001)" aria-label="ค้นหา">
      </form>
      <?php endif; ?>
      <div class="usermenu">
        <details>
          <summary><span class="avatar"><?= e(mb_substr($u['name'], 0, 1)) ?></span><span class="uname"><?= e($u['name']) ?></span></summary>
          <div class="menu">
            <div class="menu-head"><?= e($u['name']) ?><small><?= e(role_name($u['role'], $u['is_gm'] === 1)) ?></small></div>
            <a href="<?= e(url('auth.password')) ?>">เปลี่ยนรหัสผ่าน</a>
            <form method="post" action="<?= e(url('auth.logout')) ?>"><?= csrf_field() ?><button type="submit">ออกจากระบบ</button></form>
          </div>
        </details>
      </div>
    </header>
    <main class="content">
      <?php foreach (take_flashes() as $f): ?>
        <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
