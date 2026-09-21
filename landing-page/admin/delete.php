<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

checkCsrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    $stmt = getDb()->prepare('SELECT image_path FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    $del = getDb()->prepare('DELETE FROM products WHERE id = ?');
    $del->execute([$id]);

    if ($row && !empty($row['image_path'])) {
        $filePath = __DIR__ . '/../' . $row['image_path'];
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}

header('Location: index.php?deleted=1');
exit;
