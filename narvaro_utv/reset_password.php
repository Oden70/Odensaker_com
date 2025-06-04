<?php
require 'db.php';

$token = $_GET['token'] ?? null;
$valid = false;

if ($token) {
    // Kontrollera om token är giltig
    $stmt = $pdo->prepare("SELECT * FROM nv_password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $valid = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPassword = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($newPassword === $confirmPassword && strlen($newPassword) >= 6) {
                // Uppdatera lösenord
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE nv_users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$passwordHash, $reset['user_id']]);

                // Ta bort token
                $stmt = $pdo->prepare("DELETE FROM nv_password_resets WHERE id = ?");
                $stmt->execute([$reset['id']]);

                $success = "Ditt lösenord har uppdaterats. Du kan nu logga in.";
                $valid = false;
            } else {
                $error = "Lösenorden matchar inte eller är för korta (minst 6 tecken).";
            }
        }
    } else {
        $error = "Ogiltig eller utgången återställningslänk.";
    }
} else {
    $error = "Ingen token angiven.";
}

$pageTitle = "Återställ lösenord";
$fullTitle = $pageTitle . " | Konferenssystem";
?>

<!-- HTML-formulär -->
<?php if (isset($success)): ?>
    <p style="color:green;"><?= $success ?></p>
    <a href="login.php">Till inloggning</a>
<?php elseif ($valid): ?>
    <form method="POST">
        <h2>Återställ lösenord</h2>
        <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <input type="password" name="password" class="form-control" placeholder="Nytt lösenord" required><br>
        <input type="password" name="confirm_password" class="form-control" placeholder="Bekräfta lösenord" required><br>
        <button type="submit" class="btn btn-primary">Spara nytt lösenord</button>
    </form>
<?php else: ?>
    <p style="color:red;"><?= $error ?? "Något gick fel." ?></p>
<?php endif; ?>
