<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ladda rätt språkfil efter eventuell POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lang'])) {
    $_SESSION['lang'] = $_POST['lang'];
}
$lang_code = $_SESSION['lang'] ?? 'sv';
$lang_file = __DIR__ . '/languages/' . $lang_code . '.php';
if (file_exists($lang_file)) {
    require $lang_file;
} else {
    require __DIR__ . '/languages/sv.php';
}
require_once 'includes/language.php';

$user_id = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $personal_number = trim($_POST['personal_number'] ?? '');
    $lang = $_POST['lang'] ?? 'sv';
    $avatar = null;
    $role = $user['role'] ?? '';
    $status = $user['status'] ?? '';
    $password = $_POST['password'] ?? '';

    // Hantera avatar-uppladdning
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = __DIR__ . '/uploads/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = uniqid('avatar_', true) . '_' . basename($_FILES['avatar']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
            $avatar = 'uploads/' . $filename;
        }
    }

    // Hantera rolländring om superadmin
    if (($_SESSION['role'] ?? '') === 'superadmin' && isset($_POST['role'])) {
        $role = $_POST['role'];
    }
    // Hantera statusändring om superadmin
    if (($_SESSION['role'] ?? '') === 'superadmin' && isset($_POST['status'])) {
        $status = $_POST['status'];
    }

    // Bygg SQL och parametrar
    // Kontrollera om kolumnen 'language' finns i databasen innan du använder den
    // Om kolumnen saknas, ta bort 'language' från SQL och $params

    // Kolla om kolumnen finns
    $language_column_exists = false;
    try {
        $pdo->query("SELECT language FROM boka_users LIMIT 1");
        $language_column_exists = true;
    } catch (PDOException $e) {
        $language_column_exists = false;
    }

    if ($language_column_exists) {
        $sql = "UPDATE boka_users SET first_name=?, last_name=?, email=?, phone=?, address=?, zip_code=?, city=?, country=?, personal_number=?, language=?, role=?";
        $params = [$first_name, $last_name, $email, $phone, $address, $zip_code, $city, $country, $personal_number, $lang, $role];
    } else {
        $sql = "UPDATE boka_users SET first_name=?, last_name=?, email=?, phone=?, address=?, zip_code=?, city=?, country=?, personal_number=?, role=?";
        $params = [$first_name, $last_name, $email, $phone, $address, $zip_code, $city, $country, $personal_number, $role];
    }

    // Lägg till status om superadmin
    if (($_SESSION['role'] ?? '') === 'superadmin') {
        $sql .= ", status=?";
        $params[] = $status;
    }

    // Hantera lösenord om det är ifyllt
    if (!empty($password)) {
        $sql .= ", password_hash=?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($avatar) {
        $sql .= ", avatar=?";
        $params[] = $avatar;
        $_SESSION['avatar'] = $avatar;
    }
    $sql .= " WHERE id=?";
    $params[] = $user_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
    $_SESSION['lang'] = $lang;
    $msg = "<div class='alert alert-success'>" . t('profile_updated') . "</div>";
}

$stmt = $pdo->prepare("SELECT * FROM boka_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('profile') ?></title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .profile-content {
            max-width: 40vw;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #0001;
            padding: 2em;
        }
        .profile-content label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 500;
            text-align: left;
        }
        .profile-content input,
        .profile-content select {
            width: 100%;
            padding: 0.7em;
            margin-bottom: 1em;
            border: 1px solid #bdbdbd;
            border-radius: 5px;
            font-size: 1em;
            background: #fafbfc;
            text-align: left;
        }
        .profile-content button {
            width: 100%;
            padding: 0.8em;
            background: #1a237e;
            color: #fff !important;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .profile-content button:hover {
            background: #3949ab;
            color: #fff !important;
        }
        .avatar-preview img {
            max-width: 80px;
            max-height: 80px;
            display: block;
            margin-bottom: 8px;
            border-radius: 50%;
        }
        .profile-content span[readonly] {
            background: #eee;
            border-radius: 5px;
            padding: 0.7em 0.5em;
            display: block;
            margin-bottom: 1em;
            color: #333;
            font-size: 1em;
        }
    </style>
</head>
<body>
<div class="profile-content">
    <h2><?= t('profile') ?></h2>
    <?= $msg ?>
    <form method="post" enctype="multipart/form-data" class="login-form">
        <label><?= t('username') ?>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly style="background:#eee;cursor:not-allowed;">
        </label>
        <label><?= t('role') ?></label>
        <?php
        $role_labels = [
            'superadmin' => 'Superadmin',
            'admin' => 'Administratör',
            'booker' => 'Bokare',
            'customer' => 'Kund'
        ];
        ?>
        <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
            <select name="role" style="min-width:180px;max-width:220px;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;">
                <?php foreach ($role_labels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($user['role'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <span readonly><?= $role_labels[$user['role']] ?? htmlspecialchars($user['role']) ?></span>
        <?php endif; ?>
        <label><?= t('password') ?>
            <input type="password" name="password" autocomplete="new-password" placeholder="<?= t('leave_blank_no_change') ?? 'Lämna tomt för att inte ändra' ?>">
        </label>
        <label><?= t('first_name') ?>
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
        </label>
        <label><?= t('last_name') ?>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
        </label>
        <label><?= t('email') ?>
            <input type="text" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </label>
        <label><?= t('phone') ?>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </label>
        <label><?= t('address') ?>
            <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
        </label>
        <label><?= t('zip_code') ?>
            <input type="text" name="zip_code" value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>">
        </label>
        <label><?= t('city') ?>
            <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
        </label>
        <label><?= t('country') ?>
            <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>">
        </label>
        <label><?= t('personal_number') ?>
            <input type="text" name="personal_number" value="<?= htmlspecialchars($user['personal_number'] ?? '') ?>">
        </label>
        <label><?= t('avatar') ?>
            <div class="avatar-preview">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                <?php endif; ?>
            </div>
            <input type="file" name="avatar" accept="image/*">
        </label>
        <label><?= t('language') ?></label>
        <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
            <select name="lang" style="min-width:180px;max-width:220px;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;">
                <option value="sv" <?= ($user['language'] ?? 'sv') === 'sv' ? 'selected' : '' ?>>Svenska</option>
                <option value="en" <?= ($user['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        <?php else: ?>
            <span readonly><?= ($user['language'] ?? 'sv') === 'en' ? 'English' : 'Svenska' ?></span>
        <?php endif; ?>

        <label><?= t('status') ?></label>
        <?php
        $status_labels = [
            'active' => t('status_active') ?? 'Aktiv',
            'inactive' => t('status_inactive') ?? 'Inaktiv',
            'archived' => t('status_archived') ?? 'Arkiverad'
        ];
        ?>
        <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
            <select name="status" style="min-width:180px;max-width:220px;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;font-size:1em;background:#fafbfc;">
                <?php foreach ($status_labels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($user['status'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <span readonly><?= $status_labels[$user['status']] ?? htmlspecialchars($user['status']) ?></span>
        <?php endif; ?>
        <button type="submit" style="background:#1a237e;color:#fff;border:none;border-radius:5px;font-size:1.1em;font-weight:600;padding:0.8em;width:100%;cursor:pointer;transition:background 0.2s;">
            <?= t('save') ?>
        </button>
    </form>
</div>
</body>
</html>
