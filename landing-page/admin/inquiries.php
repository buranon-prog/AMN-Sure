<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$stmt = getDb()->query('SELECT * FROM inquiries ORDER BY created_at DESC');
$inquiries = $stmt->fetchAll();

$pageTitle = 'ผู้สนใจสินค้า';
require __DIR__ . '/_layout_head.php';
?>

<div class="topbar">
  <h1>ผู้สนใจสินค้า</h1>
  <nav>
    <a href="index.php">&larr; จัดการสินค้า</a>
    <a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>

<?php if (!empty($_GET['deleted'])): ?>
  <div class="alert alert-success">ลบรายการเรียบร้อยแล้ว</div>
<?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
    <strong>รายชื่อผู้สนใจทั้งหมด (<?= count($inquiries) ?>)</strong>
  </div>

  <?php if (empty($inquiries)): ?>
    <p style="color:var(--text-light);">ยังไม่มีผู้สนใจติดต่อเข้ามาจากหน้าเว็บ</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>ชื่อ</th>
          <th>คลินิก/หน่วยงาน</th>
          <th>เบอร์โทร</th>
          <th>สินค้าที่สนใจ</th>
          <th>วันที่</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($inquiries as $inq): ?>
          <tr>
            <td><?= h($inq['name']) ?></td>
            <td><?= h($inq['clinic_name'] ?? '') ?: '&mdash;' ?></td>
            <td><a href="tel:<?= h($inq['phone']) ?>"><?= h($inq['phone']) ?></a></td>
            <td><?= h($inq['interested_product'] ?? '') ?: '&mdash;' ?></td>
            <td style="color:var(--text-light);font-size:13px;"><?= h(date('d/m/Y H:i', strtotime($inq['created_at']))) ?></td>
            <td>
              <form method="post" action="delete-inquiry.php" onsubmit="return confirm('ยืนยันลบรายการนี้?');">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int) $inq['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_foot.php'; ?>
