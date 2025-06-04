<?php
session_start();
require_once '../includes/lang.php';

// Om ingen kod har genererats, skicka tillbaka till login
if (!isset($_SESSION['2fa_code'], $_SESSION['2fa_expires'], $_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Kolla om formuläret har skickats
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_code = $_POST['code'] ?? '';

    if (time() > $_SESSION['2fa_expires']) {
        $error = t('2fa_expired') ?? 'Code expired. Please log in again.';
        session_destroy();
    } elseif ($input_code == $_SESSION['2fa_code']) {
        // Rensa 2FA-data och gå vidare till dashboard
        unset($_SESSION['2fa_code'], $_SESSION['2fa_expires']);
        header("Location: ../dashboard.php");
        exit;
    } else {
        $error = t('2fa_invalid') ?? 'Invalid code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('2fa_title') ?></title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 400px;
            margin: 50px auto;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            font-size: 1.2em;
            text-align: center;
        }
        button {
            margin-top: 20px;
            padding: 10px;
            width: 100%;
        }
        .error {
            color: red;
            margin-top: 15px;
        }
    </style>
</head>
<body>

<h2><?= t('2fa_title') ?></h2>
<p><?= t('2fa_instruction') ?></p>

<form method="POST">
    <input type="text" name="code" placeholder="123456" maxlength="6" required>
    <button type="submit"><?= t('submit') ?></button>
</form>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

</body>
</html>
