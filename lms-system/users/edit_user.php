<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/language.php';

$user_id = (int)($_GET['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM lms_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<div class='alert alert-danger'>" . t('user_not_found') . "</div>";
    return;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roles = $_POST['roles'] ?? [];
    $password = $_POST['password'] ?? '';

    if ($first_name && $last_name && $username && $email && count($roles) > 0) {
        $roles_str = implode(',', $roles);
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE lms_users SET first_name=?, last_name=?, username=?, email=?, password_hash=?, roles=? WHERE id=?");
            $stmt->execute([$first_name, $last_name, $username, $email, $hash, $roles_str, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE lms_users SET first_name=?, last_name=?, username=?, email=?, roles=? WHERE id=?");
            $stmt->execute([$first_name, $last_name, $username, $email, $roles_str, $user_id]);
        }
        $msg = "<div class='alert alert-success'>" . t('user_updated') . "</div>";
        // Hämta uppdaterad användare
        $stmt = $pdo->prepare("SELECT * FROM lms_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $msg = "<div class='alert alert-danger'>" . t('fill_all_fields') . "</div>";
    }
}

$all_roles = [
    'superadmin' => t('role_superadmin'),
    'courseadmin' => t('role_courseadmin'),
    'participant' => t('role_participant'),
    'manager' => t('role_manager'),
    'instructor' => t('role_instructor')
];
$user_roles = explode(',', $user['roles']);
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('edit') ?>: <?= htmlspecialchars($user['username']) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="profile-container" style="max-width:500px;margin:40px auto;">
    <h2><?= t('edit') ?>: <?= htmlspecialchars($user['username']) ?></h2>
    <?= $msg ?>
    <form method="post" class="login-form">
        <label><?= t('first_name') ?>
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
        </label>
        <label><?= t('last_name') ?>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
        </label>
        <label><?= t('username') ?>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </label>
        <label><?= t('email') ?>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </label>
        <label><?= t('password') ?> (<?= t('leave_blank_no_change') ?>)
            <input type="password" name="password" placeholder="<?= t('leave_blank_no_change') ?>">
        </label>
        <label><?= t('roles') ?></label>
        <div style="margin-bottom:1em;">
            <?php foreach ($all_roles as $role_key => $role_label): ?>
                <label style="display:inline-block;margin-right:1em;">
                    <input type="checkbox" name="roles[]" value="<?= $role_key ?>" <?= in_array($role_key, $user_roles) ? 'checked' : '' ?>> <?= $role_label ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit"><?= t('save') ?></button>
    </form>
</div>
</body>
</html>
