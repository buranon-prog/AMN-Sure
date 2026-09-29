<?php
$u = current_user();
$r = (string) ($_GET['r'] ?? 'dashboard');
$mod = explode('.', $r)[0];
$openTasks = $u ? my_open_task_count() : 0;
$pendingApprovals = ($u && can('acquisition.approve')) ? (int) val("SELECT COUNT(*) FROM approvals WHERE decision = 'PENDING'") : 0;

// [module, ชื่อเมนู, route, สิทธิ์ที่ต้องมี (อย่างใดอย่างหนึ่ง), badge]
$nav = [
    'ภาพรวม' => [
        ['dashboard', 'หน้าหลัก', 'dashboard', [], 0],
        ['tasks', 'งานของฉัน', 'tasks', [], $openTasks],
        ['search', 'ค้นหา', 'search', [], 0],
    ],
    'ลูกค้า' => [
        ['customers', 'ลูกค้า / องค์กร', 'customers', ['customer.view'], 0],
        ['contacts', 'ผู้ติดต่อ', 'contacts', ['customer.view'], 0],
        ['leads', 'ลีด / ติดตาม', 'leads', ['lead.view'], 0],
    ],
    'เครื่อง' => [
        ['devices', 'เครื่องทั้งหมด', 'devices', ['device.view'], 0],
        ['inventory', 'สต็อก', 'inventory', ['inventory.view'], 0],
    ],
    'ฝั่งซื้อ' => [
        ['acq', 'ดีลซื้อ (จากผู้ขาย)', 'acq', ['lead.view'], 0],
        ['approvals', 'อนุมัติการซื้อ', 'approvals', ['acquisition.approve', 'task.view_all', 'acquisition.submit'], $pendingApprovals],
    ],
    'ฝั่งขาย' => [
        ['sales', 'ดีลขาย (ผู้ซื้อ)', 'sales', ['sales.view'], 0],
        ['quotations', 'ใบเสนอราคา', 'quotations', ['quotation.view'], 0],
        ['trx', 'ธุรกรรมขาย / ส่งมอบ', 'trx', ['transaction.view'], 0],
    ],
    'บริการ' => [
        ['jobs', 'ใบงานช่าง', 'jobs', ['job.view'], 0],
        ['cases', 'เคสบริการหลังขาย', 'cases', ['service_case.view'], 0],
    ],
    'ระบบ' => [
        ['admin_users', 'ผู้ใช้และสิทธิ์', 'admin.users', ['admin.users'], 0],
        ['admin_master', 'ข้อมูลหลัก', 'admin.master', ['admin.master_data'], 0],
        ['admin_settings', 'ตั้งค่า', 'admin.settings', ['admin.settings'], 0],
        ['audit', 'Audit log', 'audit', ['audit.view'], 0],
    ],
];
$activeKey = $mod === 'admin' ? 'admin_' . (explode('.', $r)[1] ?? 'users') : $mod;
if ($activeKey === 'admin_user' || $activeKey === 'admin_user_new') $activeKey = 'admin_users';
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(setting('company_name', 'AMN Sure')) ?> CRM</title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <a href="<?= e(url('dashboard')) ?>"><span class="logo">A</span><span><?= e(setting('company_name', 'AMN Sure')) ?><small>CRM · ระบบหลังบ้าน</small></span></a>
    </div>
    <nav>
      <?php foreach ($nav as $group => $items):
          $visible = array_filter($items, function ($it) { return !$it[3] || can_any(...$it[3]); });
          if (!$visible) continue; ?>
        <div class="nav-group"><?= e($group) ?></div>
        <?php foreach ($visible as $it): ?>
          <a href="<?= e(url($it[2])) ?>" class="<?= $activeKey === $it[0] ? 'active' : '' ?>">
            <?= e($it[1]) ?><?php if ($it[4] > 0): ?><span class="count"><?= (int) $it[4] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </aside>
  <label for="nav-toggle" class="nav-backdrop" aria-hidden="true"></label>
  <div class="main">
    <header class="topbar">
      <label for="nav-toggle" class="hamburger" title="เมนู">☰</label>
      <form class="topsearch" method="get" action="">
        <input type="hidden" name="r" value="search">
        <input type="search" name="q" value="<?= e($mod === 'search' ? ($_GET['q'] ?? '') : '') ?>" placeholder="ค้นหา: คลินิก, ผู้ติดต่อ, เบอร์โทร, รุ่น, Serial, DEV-, QTN-, TRX-…" aria-label="ค้นหา">
      </form>
      <div class="usermenu">
        <details>
          <summary><span class="avatar"><?= e(mb_substr($u['name'], 0, 1)) ?></span><span class="uname"><?= e($u['name']) ?></span></summary>
          <div class="menu">
            <div class="menu-head"><?= e($u['name']) ?><small><?= e(implode(', ', array_map('role_name', $u['roles']))) ?></small></div>
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
