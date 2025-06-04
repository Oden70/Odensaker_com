<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/language.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roles = $_POST['roles'] ?? [];

    if ($first_name && $last_name && $username && $email && $password && count($roles) > 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Spara roller som kommaseparerad sträng
        $roles_str = implode(',', $roles);
        $stmt = $pdo->prepare("INSERT INTO lms_users (first_name, last_name, username, email, password_hash, roles) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$first_name, $last_name, $username, $email, $hash, $roles_str])) {
            $msg = "<div class='alert alert-success'>" . t('user_created') . "</div>";
        } else {
            $msg = "<div class='alert alert-danger'>" . t('user_create_error') . "</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>" . t('fill_all_fields') . "</div>";
    }
}

// Roller
$all_roles = [
    'superadmin' => t('role_superadmin'),
    'courseadmin' => t('role_courseadmin'),
    'participant' => t('role_participant'),
    'manager' => t('role_manager'),
    'instructor' => t('role_instructor')
];
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('add_user') ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="profile-container" style="max-width:500px;margin:40px auto;">
    <h2><?= t('add_user') ?></h2>
    <?= $msg ?>
    <form method="post" class="login-form">
        <label><?= t('first_name') ?>
            <input type="text" name="first_name" required>
        </label>
        <label><?= t('last_name') ?>
            <input type="text" name="last_name" required>
        </label>
        <label><?= t('username') ?>
            <input type="text" name="username" required>
        </label>
        <label><?= t('email') ?>
            <input type="email" name="email" required>
        </label>
        <label><?= t('password') ?>
            <input type="password" name="password" required>
        </label>
        <label><?= t('roles') ?></label>
        <div style="margin-bottom:1em;">
            <?php foreach ($all_roles as $role_key => $role_label): ?>
                <label style="display:inline-block;margin-right:1em;">
                    <input type="checkbox" name="roles[]" value="<?= $role_key ?>"> <?= $role_label ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit"><?= t('save') ?></button>
    </form>
</div>
</body>
</html>
