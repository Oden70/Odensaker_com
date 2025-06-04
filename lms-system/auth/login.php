<?php
session_start();
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
require_once '../includes/language.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM lms_users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['roles'] = $user['roles'];
            header("Location: ../dashboard.php");
            exit;
        } else {
            $_SESSION['error'] = t('login_failed') ?? 'Felaktigt användarnamn eller lösenord.';
            header("Location: login.php");
            exit;
        }
    } else {
        $_SESSION['error'] = t('fill_all_fields') ?? 'Fyll i alla fält.';
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('login') ?? 'Logga in' ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 60px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #0001;
            padding: 2em 2em 1.5em 2em;
        }
        .login-container h2 {
            text-align: center;
            color: #1a237e;
            margin-bottom: 1.5em;
        }
        .login-form label {
            font-weight: 500;
            color: #333;
            display: block;
            margin-bottom: 0.5em;
        }
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 0.7em;
            margin-top: 0.2em;
            margin-bottom: 1em;
            border: 1px solid #bdbdbd;
            border-radius: 5px;
            font-size: 1em;
            background: #fafbfc;
            transition: border 0.2s;
            box-sizing: border-box;
        }
        .login-form input[type="text"]:focus,
        .login-form input[type="password"]:focus {
            border: 1.5px solid #1a237e;
            outline: none;
            background: #fff;
        }
        .login-form button[type="submit"] {
            width: 100%;
            padding: 0.8em;
            background: #1a237e;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-form button[type="submit"]:hover {
            background: #3949ab;
        }
        .alert {
            max-width: 400px;
            margin: 1em auto;
            padding: 1em 1.5em;
            border-radius: 6px;
            font-size: 1.05em;
            background: #e3f2fd;
            color: #0d47a1;
            border: 1px solid #90caf9;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2><?= t('login') ?? 'Logga in' ?></h2>
        <form action="login.php" method="post" class="login-form">
            <label><?= t('username') ?? 'Användarnamn' ?>
                <input type="text" name="username" required>
            </label>
            <label><?= t('password') ?? 'Lösenord' ?>
                <input type="password" name="password" required>
            </label>
            <button type="submit"><?= t('login') ?? 'Logga in' ?></button>
        </form>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
