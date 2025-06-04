<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/lang.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // ...hämta användare från ahs_users...
    $stmt = $pdo->prepare("SELECT * FROM ahs_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['lang'] = $user['lang'] ?? 'sv';
        $_SESSION['user_role'] = $user['role'] ?? ($user['is_admin'] ? 'admin' : 'user');
        // Om 2FA är aktiverat, skicka kod och visa kodformulär
        if ($user['use_2fa']) {
            // ...skicka kod via e-post...
            $_SESSION['pending_2fa'] = $user['id'];
            header('Location: 2fa.php');
            exit;
        }
        header('Location: portal.php');
        exit;
    } else {
        $error = 'Fel e-post eller lösenord.';
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title><?= lang('login_title') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="login-container enhanced-login">
    <div class="login-logo">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
            <circle cx="24" cy="24" r="24" fill="#1a237e"/>
            <text x="50%" y="56%" text-anchor="middle" fill="#fff" font-size="22" font-family="Arial" dy=".3em">AHS</text>
        </svg>
    </div>
    <h2><?= lang('login_title') ?></h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <label>
            <?= lang('email') ?>
            <input type="email" name="email" required autocomplete="username">
        </label>
        <div style="height:0.5em;"></div>
        <label>
            <?= lang('password') ?>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit"><?= lang('login') ?></button>
    </form>
    <div class="login-footer">
        <small>&copy; <?= date('Y') ?> Ärendehanteringssystem</small>
    </div>
</div>
</body>
</html>
