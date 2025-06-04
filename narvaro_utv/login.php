<?php
session_start();
require 'db.php';
require 'functions.php';
$pageTitle = "Logga in";
$fullTitle = $pageTitle . " | Konferenssystem";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM nv_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Generera 6-siffrig kod
        $code = generateCode();
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Spara kod i databasen
        $stmt = $pdo->prepare("INSERT INTO nv_2fa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $code, $expires]);

        // Skicka kod via e-post och visa fel om det misslyckas
        $mailResult = sendCodeEmail(
            $user['email'],
            $code,
            "no-reply@odensaker.com"
        );

        if ($mailResult === true) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_expires'] = time() + 2 * 60 * 60; // 2 timmar från nu
            header("Location: verify_code.php");
            exit;
        } else {
            $error = "Kunde inte skicka kod till e-post. Kontrollera e-postinställningar.<br><pre>" . htmlspecialchars(print_r($mailResult, true)) . "</pre>";
        }
    } else {
        $error = "Felaktigt användarnamn eller lösenord.";
    }
}
include 'toppen.php';
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logga in | Konferenssystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h2>Logga in</h2>
        <p style="color:#888;">Vänligen ange dina uppgifter</p>
    </div>
    <?php if (isset($error)) echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>'; ?>
    <form method="POST">
        <input type="text" name="username" class="form-control" placeholder="Användarnamn" required autofocus>
        <input type="password" name="password" class="form-control" placeholder="Lösenord" required>
        <button type="submit" class="btn btn-primary">Logga in</button>
    </form>
    <div class="login-footer">
        <a href="forgot_password.php">Glömt lösenord?</a>
    </div>
</div>
</body>
</html>
<?php
include 'botten.php';
?>