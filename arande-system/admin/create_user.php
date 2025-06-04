<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/lang.php';
require_login();
require_superadmin();

require_once __DIR__ . '/../inc/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $company_id = $_POST['company_id'] ?? null;
    $role = $_POST['role'] ?? 'user';
    $lang = $_POST['lang'] ?? 'sv';
    $use_2fa = isset($_POST['use_2fa']) ? 1 : 0;
    $is_admin = ($role === 'admin' || $role === 'superadmin') ? 1 : 0;

    if (!$email || !$password) {
        $error = 'E-post och lösenord krävs.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM ahs_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'E-postadressen är redan registrerad.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO ahs_users (email, password_hash, company_id, lang, use_2fa, is_admin, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $hash, $company_id ?: null, $lang, $use_2fa, $is_admin, $role]);
            $success = 'Användare skapad!';
        }
    }
}

// Hämta företag för val
$companies = $pdo->query("SELECT id, name FROM ahs_companies ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Skapa användare</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .create-user-form { max-width: 420px; margin: 2em auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #0001; padding: 2em; }
        .create-user-form h2 { margin-bottom: 1.2em; color: #1a237e; }
        .create-user-form label { display: block; margin-bottom: 1em; color: #222; }
        .create-user-form input, .create-user-form select { width: 100%; padding: 0.6em; border-radius: 5px; border: 1px solid #bbb; margin-top: 0.2em; }
        .create-user-form button { width: 100%; padding: 0.8em; background: #1a237e; color: #fff; border: none; border-radius: 5px; font-size: 1.1em; font-weight: bold; cursor: pointer; margin-top: 1em; }
        .create-user-form button:hover { background: #3949ab; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 0.7em 1em; margin-bottom: 1em; }
        .error { color: #b00; background: #ffeaea; border: 1px solid #f5c2c7; border-radius: 5px; padding: 0.7em 1em; margin-bottom: 1em; }
    </style>
</head>
<body>
<div class="create-user-form">
    <h2>Skapa användare</h2>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <label>
            E-post
            <input type="email" name="email" required>
        </label>
        <label>
            Lösenord
            <input type="password" name="password" required>
        </label>
        <label>
            Företag
            <select name="company_id">
                <option value="">Ingen (superadmin/system)</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Roll
            <select name="role">
                <option value="user">Användare</option>
                <option value="admin">Admin</option>
                <option value="superadmin">Superadmin</option>
            </select>
        </label>
        <label>
            Språk
            <select name="lang">
                <option value="sv">Svenska</option>
                <option value="en">English</option>
            </select>
        </label>
        <label>
            <input type="checkbox" name="use_2fa" value="1"> Aktivera säkerhetskod (2FA)
        </label>
        <button type="submit">Skapa användare</button>
    </form>
</div>
</body>
</html>