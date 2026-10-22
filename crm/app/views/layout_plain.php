<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'เข้าสู่ระบบ') ?> · <?= e(app_installed() ? setting('company_name', 'AMN Sure') : 'AMN Sure') ?> CRM</title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="plain">
<main class="plain-wrap<?= !empty($wide) ? ' wide' : '' ?>">
  <?php if (session_status() === PHP_SESSION_ACTIVE): foreach (take_flashes() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; endif; ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
