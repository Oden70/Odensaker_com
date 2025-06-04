<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/language.php';

if (!isset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_time'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);
    if ($code === $_SESSION['2fa_code'] && time() - $_SESSION['2fa_time'] < 600) {
        // Hämta användare
        $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE id = ?");
        $stmt->execute([$_SESSION['2fa_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sätt inloggningssession
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['lang'] = $user['language'] ?? 'sv';

        // Rensa 2FA-session
        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_time'], $_SESSION['2fa_method'], $_SESSION['2fa_to']);

        header("Location: ../dashboard.php");
        exit;
    } else {
        $error = t('invalid_2fa_code') ?? 'Felaktig eller utgången kod.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('login') ?></title>
    <link rel="stylesheet" href="main.css">
</head>
<body>
<div class="login-container">
    <h2><?= t('login') ?></h2>
    <p><?= t('enter_2fa_code') ?? 'Ange den 6-siffriga koden du fått via e-post eller sms.' ?></p>
    <?php if (!empty($error)): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="login-form">
        <label><?= t('2fa_code') ?? 'Kod' ?>
            <input type="text" name="code" maxlength="6" pattern="\d{6}" required>
        </label>
        <button type="submit"><?= t('login') ?></button>
    </form>
</div>
</body>
</html>
