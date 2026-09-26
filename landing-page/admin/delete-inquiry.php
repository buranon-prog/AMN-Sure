<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inquiries.php');
    exit;
}

checkCsrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    $stmt = getDb()->prepare('DELETE FROM inquiries WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: inquiries.php?deleted=1');
exit;
