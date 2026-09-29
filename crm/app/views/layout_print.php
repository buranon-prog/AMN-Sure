<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? '') ?></title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<style>
  body { background: #fff; }
  .doc { max-width: 820px; margin: 24px auto; padding: 0 16px; }
  .doc h1 { font-size: 24px; }
  .doc .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #1c2430; padding-bottom: 12px; margin-bottom: 16px; }
  .doc table.table th { background: #f1f3f6; color: #1c2430; }
  .doc .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 48px; text-align: center; }
  .doc .sign div { border-top: 1px solid #999; padding-top: 6px; }
  @media print { .no-print { display: none; } .doc { margin: 0; } }
</style>
</head>
<body>
<div class="no-print" style="text-align:center;padding:10px;background:#f3f5f8"><button class="btn primary" data-print>พิมพ์ / บันทึกเป็น PDF</button></div>
<div class="doc"><?= $content ?></div>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
