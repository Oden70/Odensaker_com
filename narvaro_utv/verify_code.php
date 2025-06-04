<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT * FROM nv_2fa_codes WHERE user_id = ? AND code = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
    $stmt->execute([$user_id, $code]);
    $validCode = $stmt->fetch();

    if ($validCode) {
        // Kod är giltig – logga in användaren
        $_SESSION['authenticated'] = true;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Ogiltig eller utgången kod.";
    }
}
include 'toppen.php';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifiera kod</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h2>Verifiera kod</h2>
        <p style="color:#888;">En engångskod har skickats till din e-post</p>
    </div>
    <?php if (isset($error)) echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>'; ?>
    <form method="POST">
        <input type="text" name="code" class="form-control" placeholder="Ange 6-siffrig kod" required autofocus maxlength="6" pattern="[0-9]{6}">
        <button type="submit" class="btn btn-primary">Verifiera</button>
    </form>
    <div class="login-footer">
        <a href="login.php">Tillbaka till inloggning</a>
    </div>
</div>
</body>
</html>
<?php
include 'botten.php';
?>