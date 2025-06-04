<?php
require_once '../includes/db.php';
require_once '../includes/mail.php';
require_once '../includes/lang.php';

$email = $_POST['email'] ?? '';
$stmt = $pdo->prepare("SELECT id FROM lms_users WHERE email = ?");
$stmt->execute([$email]);

if ($user = $stmt->fetch()) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare("UPDATE lms_users SET reset_token = ?, reset_expires = ? WHERE id = ?")
        ->execute([$token, $expires, $user['id']]);

    // Dynamiskt skapa path till reset_password.php
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $link = "$protocol://$host$path/reset_password.php?token=$token";

    sendPasswordResetEmail($email, $link);
}

echo t('reset_sent');
?>