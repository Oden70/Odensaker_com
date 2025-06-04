<?php
require 'db.php';
require 'functions.php';
$pageTitle = "Glömt lösenord";
$fullTitle = $pageTitle . " | Konferenssystem";
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Kontrollera om e-post finns
    $stmt = $pdo->prepare("SELECT id FROM nv_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Skapa token
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Spara token i databasen (skapa tabell om den inte finns)
        $pdo->prepare("CREATE TABLE IF NOT EXISTS nv_password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            token VARCHAR(64),
            expires_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES nv_users(id) ON DELETE CASCADE
        )")->execute();

        $stmt = $pdo->prepare("INSERT INTO nv_password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, $expires]);

        // Skicka återställningslänk och logga mejlet
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $resetLink = $protocol . $host . "/reset_password.php?token=$token";
        $subject = "Återställ ditt lösenord";
        $message = "Klicka på länken för att återställa ditt lösenord:\n$resetLink\n\nLänken är giltig i 1 timme.";
        mail($email, $subject, $message, "From: no-reply@odensaker.com");

        // Logga mejlet
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO nv_maillog (to_email, subject, body, sent_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$email, $subject, $message]);
        }

        $success = "En återställningslänk har skickats till din e-post.";
    } else {
        $error = "E-postadressen finns inte registrerad.";
    }
}
?>


<!-- HTML-formulär -->
<form method="POST">
    <h2>Glömt lösenord</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
    <input type="email" name="email" placeholder="Din e-postadress" required><br>
    <button type="submit" class="btn btn-primary">Skicka återställningslänk</button>
</form>
<?php
include 'footer.php';
?>