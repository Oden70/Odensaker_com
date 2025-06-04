<?php
require_once '../includes/db.php';
require_once '../includes/lang.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';

if (!$token || !$password) die("Ogiltig begäran.");

$stmt = $pdo->prepare("SELECT id FROM lms_users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);

if ($user = $stmt->fetch()) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE lms_users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
        ->execute([$hash, $user['id']]);

    echo t('reset_done') . ' <a href="login.php">Login</a>';
} else {
    echo t('reset_invalid');
}
