<?php
require_once 'includes/db.php';
require_once 'includes/language.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($first_name && $last_name && $email) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Uppdatera endast kolumner som finns i databasen (utan 'role')
            $stmt = $pdo->prepare("UPDATE lms_users SET first_name = ?, last_name = ?, email = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $hash, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE lms_users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $user_id]);
        }
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $msg = "<div class='alert alert-success'>" . t('profile_updated') . "</div>";
    } else {
        $msg = "<div class='alert alert-danger'>" . t('fill_all_fields') . "</div>";
    }
}

// Ändra SELECT så att den bara hämtar kolumner som finns i databasen
$stmt = $pdo->prepare("SELECT first_name, last_name, email FROM lms_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'sv') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('profile') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="profile-container" style="max-width:420px;margin:40px auto 0 auto;background:#fff;border-radius:10px;box-shadow:0 4px 24px #0001;padding:2em 2em 1.5em 2em;">
    <h2 style="text-align:center; color:#1a237e; margin-bottom:1.5em;"><?= t('profile') ?></h2>
    <?= $msg ?>
    <form method="post" class="login-form" style="margin-top:1.5em;">
        <label style="font-weight:500; color:#333; display:block; margin-bottom:0.5em;">
            <?= t('first_name') ?>
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
        </label>
        <label style="font-weight:500; color:#333; display:block; margin-bottom:0.5em;">
            <?= t('last_name') ?>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
        </label>
        <label style="font-weight:500; color:#333; display:block; margin-bottom:0.5em;">
            <?= t('email') ?>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </label>
        <label style="font-weight:500; color:#333; display:block; margin-bottom:0.5em;">
            <?= t('new_password') ?> 
            <input type="password" name="password" placeholder="<?= t('leave_blank_no_change') ?>">
        </label>
        <label style="font-weight:500; color:#333; display:block; margin-bottom:0.5em;">
            <?= t('role') ?>
            <input type="text" value="<?= htmlspecialchars($_SESSION['role'] ?? '') ?>" readonly style="background:#eee;">
        </label>
        <button type="submit" style="width:100%;padding:0.8em;background:#1a237e;color:#fff;border:none;border-radius:5px;font-size:1.1em;font-weight:600;cursor:pointer;transition:background 0.2s;"><?= t('save') ?></button>
    </form>
</div>
</body>
</html>
