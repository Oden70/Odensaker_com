<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/mail.php';
require_once '../includes/language.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    die('Ogiltig begäran');
}

// Hämta användaren
$stmt = $pdo->prepare("SELECT * FROM lms_users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC); // Se till att du får en associativ array

if (!$user) {
    die(t('login') . " misslyckades.");
}

// Kontrollera att kolumnen heter 'password_hash' och inte 'password'
if (!isset($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    die(t('login') . " misslyckades.");
}

// Spara inloggad info i session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

if (!empty($user['2fa_enabled'])) {
    // Generera 6-siffrig kod
    $code = random_int(100000, 999999);
    $_SESSION['2fa_code'] = $code;
    $_SESSION['2fa_expires'] = time() + 300; // 5 minuters giltighet

    // Skicka kod via e-post
    $subject = t('2fa_title');
    $message = t('2fa_instruction') . "\n\n" . "Kod: $code";
    mail($email, $subject, $message, "From: no-reply@odensaker.com");

    header("Location: 2fa_verify.php");
    exit;
} else {
    // Ingen 2FA – skicka till dashboard
    header("Location: ../dashboard.php");
    exit;
}
?>