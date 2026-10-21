<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'เข้าสู่ระบบ') ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="auth">
<svg class="auth-rings" aria-hidden="true" viewBox="0 0 1200 1200" fill="none">
  <circle cx="600" cy="600" r="300" stroke="#F4EFE3" stroke-opacity="0.10"></circle>
  <circle cx="600" cy="600" r="420" stroke="#F4EFE3" stroke-opacity="0.07"></circle>
  <circle cx="600" cy="600" r="540" stroke="#F4EFE3" stroke-opacity="0.05"></circle>
  <circle cx="600" cy="600" r="420" stroke="#D4AF5A" stroke-opacity="0.75" stroke-width="2" stroke-dasharray="160 2480" stroke-dashoffset="-180"></circle>
  <circle cx="600" cy="600" r="540" stroke="#D4AF5A" stroke-opacity="0.4" stroke-width="1.5" stroke-dasharray="120 3270" stroke-dashoffset="-1900"></circle>
</svg>
<main class="auth-wrap">
  <div class="auth-brand">
    <span class="auth-logo" aria-hidden="true">S</span>
    <div class="wordmark"><?= e(APP_NAME) ?></div>
    <span class="goldrule" aria-hidden="true"></span>
  </div>
  <?php if (session_status() === PHP_SESSION_ACTIVE): foreach (take_flashes() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; endif; ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
