<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/lang.php';
session_start();

if (!isset($_SESSION['pending_2fa'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    // ...verifiera kod mot ahs_users...
    $stmt = $pdo->prepare("SELECT id FROM ahs_users WHERE id = ? AND twofa_code = ?");
    $stmt->execute([$_SESSION['pending_2fa'], $code]);
    if ($stmt->fetch()) {
        $_SESSION['user_id'] = $_SESSION['pending_2fa'];
        unset($_SESSION['pending_2fa']);
        header('Location: portal.php');
        exit;
    } else {
        $error = 'Fel kod.';
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title><?= lang('2fa_code') ?></title>
    <link rel="stylesheet" href="/arande-system/assets/style.css">
</head>
<body>
<div class="login-container">
    <h2><?= lang('2fa_code') ?></h2>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <label><?= lang('2fa_code') ?> <input type="text" name="code" maxlength="6" required></label><br>
        <button type="submit"><?= lang('login') ?></button>
    </form>
</div>
</body>
</html>
