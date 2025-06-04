<?php
session_start();
require_once '../includes/db.php';

// Hantera språkval via GET
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Ladda språkfil korrekt
$lang_code = $_SESSION['lang'] ?? 'sv';
$lang_file = dirname(__DIR__) . '/languages/' . $lang_code . '.php';
if (file_exists($lang_file)) {
    require $lang_file;
} else {
    require dirname(__DIR__) . '/languages/sv.php';
}
require_once '../includes/language.php';

function send_2fa_code($method, $to, $code) {
    // Skicka kod via e-post eller sms (pseudo)
    if ($method === 'email') {
        $headers = "From: no-reply@odensaker.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        mail($to, "Din 2FA-kod", "Din kod är: $code", $headers);
    } elseif ($method === 'sms') {
        // Här ska du anropa en SMS-tjänst, t.ex. Twilio eller liknande
        // send_sms($to, "Din kod är: $code");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM boka_users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Spara användarinfo temporärt i session
        $_SESSION['2fa_user_id'] = $user['id'];
        $_SESSION['2fa_method'] = $user['twofa_method'];
        $_SESSION['2fa_to'] = ($user['twofa_method'] === 'sms') ? $user['phone'] : $user['email'];
        $_SESSION['2fa_code'] = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['2fa_time'] = time();

        // Skicka kod
        if ($user['twofa_method'] === 'both') {
            send_2fa_code('email', $user['email'], $_SESSION['2fa_code']);
            send_2fa_code('sms', $user['phone'], $_SESSION['2fa_code']);
        } else {
            send_2fa_code($user['twofa_method'], $_SESSION['2fa_to'], $_SESSION['2fa_code']);
        }
        header("Location: verify_2fa.php");
        exit;
    } else {
        $error = t('login_failed') ?? 'Felaktigt användarnamn eller lösenord.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('login') ?></title>
    <link rel="stylesheet" href="../includes/main.css">
</head>
<body>
<div class="login-container">
    <h2><?= t('login') ?></h2>
    <?php if (!empty($error)): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="login-form">
        <label><?= t('username') ?>
            <input type="text" name="username" required>
        </label>
        <label><?= t('password') ?>
            <input type="password" name="password" required>
        </label>
        <button type="submit"><?= t('login') ?></button>
        <div style="margin-top:1.5em;text-align:right;">
            <label for="lang-select" style="font-weight:400;"><?= t('change_language') ?? 'Ändra språk' ?>:</label>
            <select id="lang-select" name="lang" onchange="window.location.search='?lang='+this.value" style="padding:0.4em 1em;border-radius:5px;border:1px solid #bdbdbd;margin-left:0.5em;">
                <option value="sv" <?= ($_SESSION['lang'] ?? 'sv') === 'sv' ? 'selected' : '' ?>>Svenska</option>
                <option value="en" <?= ($_SESSION['lang'] ?? 'sv') === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </div>
    </form>
</div>
</body>
</html>
