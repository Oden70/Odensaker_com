<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Hämta användarens uppgifter
$stmt = $pdo->prepare("SELECT username, email, role, fornamn, efternamn FROM nv_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Uppdatera uppgifter
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = $_POST['username'];
    $newEmail = $_POST['email'];
    $newPassword = $_POST['password'];

    // Uppdatera användarnamn och e-post
    $stmt = $pdo->prepare("UPDATE nv_users SET username = ?, email = ? WHERE id = ?");
    $stmt->execute([$newUsername, $newEmail, $user_id]);

    // Uppdatera lösenord om det är ifyllt
    if (!empty($newPassword)) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE nv_users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $user_id]);
    }

    $success = "Profilen har uppdaterats!";
    // Hämta uppdaterade uppgifter
    $stmt = $pdo->prepare("SELECT username, email, role, fornamn, efternamn FROM nv_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
include 'toppen.php';
?>

<div class="container" style="max-width: 1100px; margin: 0 auto;">
    <div class="page-header" style="background: #e9ecef; border: 1px solid #bbb; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001; text-align:center;">
        <h2 style="margin:0;">Min profil</h2>
    </div>
    <div class="page-section" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #0001;">
        <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Användarnamn:</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>E-post:</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Förnamn:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['fornamn']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label>Efternamn:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['efternamn']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label>Roll:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['role']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label>Nytt lösenord (valfritt):</label>
                    <input type="password" name="password" class="form-control" placeholder="Lämna tomt för att behålla nuvarande">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100">Uppdatera profil</button>
                </div>
            </div>
        </form>
        <script>
        document.querySelector("form").addEventListener("submit", function(e) {
            const confirmed = confirm("Är du säker på att du vill uppdatera din profil?");
            if (!confirmed) {
                e.preventDefault();
            }
        });
        </script>
    </div>
</div>
<?php
include 'botten.php';
?>