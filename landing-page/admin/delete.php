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
    $stmt = getDb()->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();

    $del = getDb()->prepare('DELETE FROM products WHERE id = ?');
    $del->execute([$id]); // product_images rows are removed via ON DELETE CASCADE

    foreach ($images as $row) {
        $filePath = __DIR__ . '/../' . $row['image_path'];
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}

header('Location: index.php?deleted=1');
exit;
